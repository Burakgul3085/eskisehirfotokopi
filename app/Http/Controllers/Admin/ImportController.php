<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\CategoryMapper;
use App\Domain\Catalog\CategoryTree;
use App\Domain\Import\ImportBatchCreator;
use App\Domain\Import\ImportItemProcessor;
use App\Domain\Import\Isbn;
use App\Domain\Import\Ocr\OcrEngine;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessImportItem;
use App\Models\Category;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\Product;
use App\Support\Activity;
use App\Support\ClassificationInput;
use App\Support\SpecInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Toplu klasör yükleme (docs/01 §6). Tarayıcı kök klasörü okur ve kitap listesini gönderir (store); ardından her
 * dosyayı ayrı istekle yükler (upload). Kitabın dosyaları tamamlanınca kuyruğa girer ve arka planda işlenir.
 */
class ImportController extends Controller
{
    public function index(): View
    {
        $batches = ImportBatch::with('admin:id,name')->latest('id')->paginate(20);
        $counts = ImportItem::whereIn('batch_id', $batches->pluck('id'))
            ->selectRaw('batch_id, status, count(*) c')->groupBy('batch_id', 'status')->get()
            ->groupBy('batch_id')->map(fn ($rows) => $rows->pluck('c', 'status'));
        $reviewTotal = ImportItem::where('status', 'review')->count();
        $failedTotal = ImportItem::where('status', 'failed')->count();

        return view('admin.imports.index', compact('batches', 'counts', 'reviewTotal', 'failedTotal'));
    }

    public function create(): View
    {
        return view('admin.imports.create', [
            'maxMb' => config('catalog.import_max_mb'),
            'ocrReady' => app(OcrEngine::class)->available(),
            'categories' => app(CategoryTree::class)->flat()->where('depth', '<=', 1),
        ]);
    }

    public function store(Request $request, ImportBatchCreator $creator): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'books' => ['required', 'array', 'min:1', 'max:30000'],
            'books.*.publisher_folder' => ['nullable', 'string', 'max:255'],
            'books.*.folder' => ['required', 'string', 'max:255'],
            'books.*.info' => ['nullable', 'string', 'max:255'],
            'books.*.mockup' => ['nullable', 'string', 'max:255'],
            'books.*.cover' => ['nullable', 'string', 'max:255'],
            'books.*.imprint' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $creator->create($data['name'], array_values($data['books']), $request->user('admin')->id, defaultCategoryId: $data['category_id'] ?? null);
        Activity::log('import.started', $result['batch'], ['books' => count($data['books']), 'new' => $result['batch']->total_items]);

        return response()->json([
            'batch' => $result['batch']->id,
            'items' => array_values($result['items']),
            'status_url' => route('admin.imports.status', $result['batch']),
            'show_url' => route('admin.imports.show', $result['batch']),
        ]);
    }

    public function upload(Request $request, ImportBatch $batch, ImportItem $item): JsonResponse
    {
        abort_unless($item->batch_id === $batch->id, 404);
        $data = $request->validate([
            'role' => ['required', Rule::in(ImportItem::ROLES)],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.(config('catalog.import_max_mb') * 1024)],
        ]);
        if ($item->status !== 'waiting_files') {
            return response()->json(['status' => $item->status]); // yeniden deneme: zaten alınmış
        }

        $ext = strtolower($data['file']->getClientOriginalExtension()) ?: 'jpg';
        $path = $data['file']->storeAs($item->storageDir(), $data['role'].'.'.$ext, 'local');
        $item->forceFill([$data['role'].'_path' => $path]);
        if ($item->hasAllFiles()) {
            $item->status = 'queued';
        }
        $item->save();
        if ($item->status === 'queued') {
            ProcessImportItem::dispatch($item->id);
            $batch->refreshStatus();
        }

        return response()->json(['status' => $item->fresh()->status]);
    }

    public function status(ImportBatch $batch): JsonResponse
    {
        return response()->json(['status' => $batch->fresh()->status, 'counts' => $batch->counts()]);
    }

    public function show(Request $request, ImportBatch $batch): View
    {
        $status = $request->query('durum');
        $items = $batch->items()->with('product:id,title,slug,image_key')
            ->when($status && isset(ImportItem::STATUSES[$status]), fn ($q) => $q->where('status', $status))
            ->orderByRaw("FIELD(status, 'review', 'failed', 'processing', 'queued', 'waiting_files', 'imported', 'skipped')")
            ->orderBy('id')->paginate(50)->withQueryString();

        return view('admin.imports.show', [
            'batch' => $batch, 'items' => $items, 'counts' => $batch->counts(), 'status' => $status,
            'newCategories' => $this->newCategories($batch),
            'categories' => app(CategoryTree::class)->flat(),
            'specOptions' => Product::specOptions(),
        ]);
    }

    /**
     * Bu yüklemede açılan ve henüz "doğru" denmemiş kategoriler (silinmiş/birleştirilmiş olanlar düşer).
     *
     * @return Collection<int, Category>
     */
    private function newCategories(ImportBatch $batch): Collection
    {
        $ids = $batch->items()->pluck('parsed')->flatMap(fn ($p) => (is_array($p) ? $p : (json_decode((string) $p, true) ?: []))['new_categories'] ?? [])
            ->unique()->diff($batch->checked_categories ?? []);

        return Category::whereIn('id', $ids)->withCount('products')->orderBy('path')->get();
    }

    /** Yüklemedeki tüm ürünlerde bir bilginin müşteriye gösterimini değiştirir (ör. ISBN'i gizle). */
    public function specs(Request $request, ImportBatch $batch): RedirectResponse
    {
        $data = $request->validate([
            'spec' => ['required', 'string', 'max:80', 'regex:/^('.implode('|', array_keys(Product::SPECS)).'|ek:[a-z0-9 ]+)$/'],
            'state' => ['required', Rule::in(['show', 'hide', 'default'])],
        ]);
        $ids = $batch->items()->whereNotNull('product_id')->pluck('product_id');
        $n = Product::setSpecVisibility($ids, $data['spec'], ['show' => true, 'hide' => false, 'default' => null][$data['state']]);
        Activity::log('import.specs', null, ['batch' => $batch->id] + $data + ['count' => $n]);

        return back()->with('status', "{$n} üründe bilgi gösterimi güncellendi.");
    }

    public function checkCategory(ImportBatch $batch, Category $category): RedirectResponse
    {
        $batch->update(['checked_categories' => array_values(array_unique([...($batch->checked_categories ?? []), $category->id]))]);

        return back()->with('status', "“{$category->name}” doğru olarak işaretlendi.");
    }

    /** Yanlış açılmış kategoriyi doğru olana katar: ürünleri ve alt kategorileri taşınır, kendisi silinir. */
    public function mergeCategory(Request $request, ImportBatch $batch, Category $category, CategoryTree $tree): RedirectResponse
    {
        $data = $request->validate(['into' => ['required', 'integer', 'exists:categories,id', Rule::notIn([$category->id])]], [
            'into.required' => 'Birleştirilecek kategoriyi seçin.',
        ]);
        $into = Category::findOrFail($data['into']);
        if (str_starts_with($into->path, $category->path)) {
            return back()->withErrors(['into' => 'Kategori kendi alt kategorisine katılamaz.']);
        }
        $name = $category->fullName();
        $tree->merge($category, $into);
        Activity::log('categories.merged', $into, ['from' => $name]);

        return back()->with('status', "“{$name}”, “{$into->fullName()}” kategorisine katıldı.");
    }

    /** Kontrol ekranı: bilgi görselinden bir alanın okunduğu bölge (görselde ne yazdığını yan yana görmek için). */
    public function crop(ImportItem $item, string $field): Response
    {
        $box = $item->parsed['boxes'][$field] ?? null;
        abort_unless($box && $item->info_path && Storage::disk('local')->exists($item->info_path), 404);
        $img = @imagecreatefromstring(Storage::disk('local')->get($item->info_path));
        abort_unless($img !== false, 404);
        $scale = (float) ($item->parsed['box_scale'] ?? 1) ?: 1.0;
        [$x, $y, $w, $h] = array_map(fn ($v) => (int) round($v / $scale), $box);
        $padX = 14;
        $padY = 6;
        $crop = imagecrop($img, [
            'x' => max(0, $x - $padX), 'y' => max(0, $y - $padY),
            'width' => min(imagesx($img) - max(0, $x - $padX), $w + 2 * $padX),
            'height' => min(imagesy($img) - max(0, $y - $padY), $h + 2 * $padY),
        ]);
        abort_unless($crop !== false, 404);
        ob_start();
        imagepng($crop);

        return response(ob_get_clean(), 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=3600']);
    }

    public function review(ImportItem $item): View
    {
        $item->load('product.publisher', 'product.author', 'product.category', 'batch');
        $next = ImportItem::where('batch_id', $item->batch_id)->where('status', 'review')->whereKeyNot($item->id)->orderBy('id')->value('id');
        $p = $item->product;

        $boxes = $item->info_path ? ($item->parsed['boxes'] ?? []) : [];

        return view('admin.imports.review', [
            'item' => $item,
            'next' => $next,
            'categories' => app(CategoryTree::class)->flat(),
            'readPath' => implode(' / ', $item->parsed['category'] ?? []),
            'crops' => collect(array_keys(ImportItemProcessor::FIELD_LABELS))->filter(fn ($f) => isset($boxes[$f]))
                ->mapWithKeys(fn ($f) => [$f => route('admin.imports.crop', [$item, $f])])->all(),
            'values' => [
                'title' => $p?->title ?? $item->parsed['title'] ?? '',
                'subtitle' => $p?->subtitle ?? $item->parsed['subtitle'] ?? '',
                'author' => $p?->author?->name ?? $item->parsed['author'] ?? '',
                'publisher' => $p?->publisher?->name ?? $item->parsed['publisher'] ?? '',
                'category_id' => $p?->category_id,
                'book_type' => $p?->book_type ?? $item->parsed['book_type'] ?? null,
                'subject' => $p?->subject ?? $item->parsed['subject'] ?? null,
                'topics' => $p?->topics ?? [],
                'isbn' => $p?->isbn ?? $item->parsed['isbn'] ?? '',
                'pages' => $p?->page_count ?? $item->parsed['pages'] ?? '',
                'code' => $p?->code ?? $item->parsed['code'] ?? '',
            ],
        ]);
    }

    public function update(Request $request, ImportItem $item, ImportItemProcessor $processor, CategoryMapper $mapper): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            ...ClassificationInput::rules(),
            'isbn' => ['nullable', 'string', 'max:20'],
            'pages' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'code' => ['nullable', 'string', 'max:40'],
            ...SpecInput::rules(),
        ]);
        $isbn = null;
        if (! empty($data['isbn'])) {
            $isbn = Isbn::normalize($data['isbn']);
            if ($isbn === null) {
                return back()->withErrors(['isbn' => 'ISBN geçersiz (kontrol hanesi tutmuyor).'])->withInput();
            }
        }

        try {
            [$product, , $issues] = $processor->apply([
                'title' => trim($data['title']),
                'subtitle' => trim((string) ($data['subtitle'] ?? '')), // boş bırakılırsa alt başlık silinir
                'author' => $data['author'] ?? null,
                'publisher' => $data['publisher'] ?? null,
                'publisher_folder' => null,
                ...ClassificationInput::from($request),
                'isbn' => $isbn,
                'pages' => $data['pages'] ?? null,
                'code' => ! empty($data['code']) ? strtoupper(trim($data['code'])) : null,
                ...SpecInput::from($request),
            ], $item->product);
        } catch (Throwable $e) {
            return back()->withErrors(['title' => $e->getMessage()])->withInput();
        }
        if ($issues) {
            return back()->withErrors(['isbn' => implode(' ', $issues)])->withInput();
        }

        // Seçilen kategori hatırlanır: aynı "Türü" yolu sonraki yüklemelerde kendiliğinden bu kategoriye gider.
        if ($product->category && ! empty($item->parsed['category'])) {
            $mapper->remember($item->parsed['category'], $product->category);
        }
        $item->update(['status' => 'imported', 'product_id' => $product->id, 'reviewed_at' => now(), 'error' => null]);
        $item->batch?->refreshStatus();
        Activity::log('import.reviewed', $product);
        // Kontrolde sayfa sayısı girildiyse fiyat hesaplanmıştır: taslak ürün doğrudan yayına alınır.
        if ($product->status === 'draft' && $product->page_count && $product->price_bw !== null) {
            $product->update(['status' => 'active']);

            return $this->afterReview($item, 'Ürün kaydedildi; fiyatı sayfa sayısından hesaplandı ve yayına alındı.');
        }

        return $this->afterReview($item, 'Ürün kaydedildi ve onaylandı.');
    }

    public function approve(ImportItem $item): RedirectResponse
    {
        abort_unless($item->product_id, 422);
        $item->update(['status' => 'imported', 'reviewed_at' => now()]);
        $item->batch?->refreshStatus();

        return $this->afterReview($item, 'Onaylandı.');
    }

    /** Kontrol bekleyen tüm kalemleri değiştirmeden onaylar (ürünler taslak kalır; fiyat Excel ile gelir, K2). */
    public function approveAll(ImportBatch $batch): RedirectResponse
    {
        $count = $batch->items()->where('status', 'review')->whereNotNull('product_id')
            ->update(['status' => 'imported', 'reviewed_at' => now()]);
        $batch->refreshStatus();
        Activity::log('import.approved_all', null, ['batch' => $batch->id, 'count' => $count]);

        return back()->with('status', "{$count} kitap onaylandı. Ürünler taslakta: Ürünler sayfasında PDF sayfa sayılarını girin; fiyat otomatik hesaplanır ve yayına alınır.");
    }

    public function retry(ImportItem $item): RedirectResponse
    {
        if ($item->status === 'failed' || $item->status === 'review') {
            $item->update(['status' => 'queued', 'error' => null]);
            ProcessImportItem::dispatch($item->id);
        }

        return back()->with('status', 'Yeniden işlenmek üzere sıraya alındı.');
    }

    public function retryFailed(ImportBatch $batch): RedirectResponse
    {
        $ids = $batch->items()->where('status', 'failed')->pluck('id');
        ImportItem::whereIn('id', $ids)->update(['status' => 'queued', 'error' => null]);
        $ids->each(fn ($id) => ProcessImportItem::dispatch($id));

        return back()->with('status', $ids->count().' kalem yeniden sıraya alındı.');
    }

    public function image(ImportItem $item): StreamedResponse
    {
        abort_unless($item->info_path && Storage::disk('local')->exists($item->info_path), 404);

        return Storage::disk('local')->response($item->info_path, null, ['Cache-Control' => 'private, max-age=3600']);
    }

    public function destroy(ImportBatch $batch): RedirectResponse
    {
        Storage::disk('local')->deleteDirectory("imports/{$batch->id}");
        $batch->delete();

        return redirect()->route('admin.imports.index')->with('status', 'Yükleme kaydı silindi (ürünler silinmez).');
    }

    private function afterReview(ImportItem $item, string $message): RedirectResponse
    {
        $next = ImportItem::where('batch_id', $item->batch_id)->where('status', 'review')->orderBy('id')->value('id');

        return $next
            ? redirect()->route('admin.imports.review', $next)->with('status', $message)
            : redirect()->route('admin.imports.show', $item->batch_id)->with('status', $message.' Kontrol bekleyen kalem kalmadı.');
    }
}
