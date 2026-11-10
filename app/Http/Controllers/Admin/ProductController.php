<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\CategoryTree;
use App\Domain\Catalog\PriceSheet;
use App\Domain\Catalog\ProductImages;
use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Category;
use App\Models\Product;
use App\Models\Publisher;
use App\Support\Activity;
use App\Support\Money;
use App\Support\Text;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Ürün yönetimi: filtreli liste, toplu işlemler, düzenleme, görsel ve Excel fiyat şablonu. */
class ProductController extends Controller
{
    public function __construct(private CategoryTree $tree, private ProductImages $images) {}

    public function index(Request $request): View
    {
        $products = $this->filtered($request)->with(['publisher:id,name', 'category:id,name', 'author:id,name'])
            ->orderByDesc('id')->paginate((int) $request->query('adet', 50) === 200 ? 200 : 50)->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'categories' => $this->tree->flat(),
            'publishers' => Publisher::orderBy('name')->get(['id', 'name']),
            'stats' => [
                'total' => Product::count(),
                'active' => Product::where('status', 'active')->count(),
                'draft' => Product::where('status', 'draft')->count(),
                'missing_price' => Product::missingPrice()->count(),
                'missing_image' => Product::whereNull('image_key')->count(),
            ],
        ]);
    }

    /** Listedeki filtreler (Excel dışa aktarma da aynısını kullanır). */
    private function filtered(Request $request): Builder
    {
        $q = trim((string) $request->query('q'));

        return Product::query()
            ->when($q !== '', function ($w) use ($q) {
                $digits = preg_replace('/\D/', '', $q);
                $w->where(function ($x) use ($q, $digits) {
                    foreach (explode(' ', Text::key($q)) as $term) {
                        $x->where('search_text', 'like', '%'.$term.'%');
                    }
                    if (strlen($digits) >= 10) {
                        $x->orWhere('isbn', $digits);
                    }
                });
            })
            ->when($request->filled('yayinevi'), fn ($w) => $w->where('publisher_id', $request->integer('yayinevi')))
            ->when($request->filled('kategori') && $request->query('kategori') !== '0', function ($w) use ($request) {
                $cat = Category::find($request->integer('kategori'));
                $w->whereIn('category_id', $cat ? $cat->subtreeIds() : [0]);
            })
            ->when(isset(Product::STATUSES[$request->query('durum')]), fn ($w) => $w->where('status', $request->query('durum')))
            ->when($request->query('fiyat') === 'eksik', fn ($w) => $w->missingPrice())
            ->when($request->query('fiyat') === 'var', fn ($w) => $w->priced())
            ->when($request->query('gorsel') === 'eksik', fn ($w) => $w->whereNull('image_key'))
            ->when($request->query('kategori') === '0', fn ($w) => $w->whereNull('category_id'));
    }

    public function create(): View
    {
        return $this->form(new Product(['status' => 'draft']));
    }

    public function edit(Product $product): View
    {
        return $this->form($product->load(['author', 'publisher', 'category']));
    }

    private function form(Product $product): View
    {
        return view('admin.products.form', [
            'product' => $product,
            'categories' => $this->tree->flat(),
            'publishers' => Publisher::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        $product = Product::create($data + ['slug' => Product::uniqueSlug($data['title'])]);
        $this->saveImage($request, $product);
        Activity::log('product.created', $product, ['title' => $product->title]);

        return redirect()->route('admin.products.edit', $product)->with('status', 'Ürün eklendi.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request, $product);
        $product->update($data);
        $this->saveImage($request, $product);
        Activity::log('product.updated', $product, collect($product->getChanges())->except(['updated_at', 'search_text'])->all());

        return redirect()->route('admin.products.edit', $product)->with('status', 'Ürün kaydedildi.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->images->delete($product);
        Activity::log('product.deleted', null, ['title' => $product->title, 'isbn' => $product->isbn]);
        $product->delete();

        return redirect()->route('admin.products.index')->with('status', 'Ürün silindi.');
    }

    /** Toplu işlem: seçilen ürünlerde durum, kategori, öne çıkarma veya silme. */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:1000'],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['active', 'passive', 'draft', 'feature', 'unfeature', 'category', 'delete'])],
            'category_id' => ['nullable', 'required_if:action,category', 'integer', 'exists:categories,id'],
        ], ['ids.required' => 'Önce ürün seçin.']);
        $query = Product::whereIn('id', $data['ids']);
        $n = 0;
        switch ($data['action']) {
            case 'active':
                // Fiyatı olmayan ürün yayına alınmaz (K2).
                $n = $query->clone()->priced()->get()->each(fn ($p) => $p->update(['status' => 'active']))->count();
                $skipped = count($data['ids']) - $n;
                break;
            case 'passive':
            case 'draft':
                $n = $query->update(['status' => $data['action']]);
                break;
            case 'feature':
            case 'unfeature':
                $n = $query->update(['is_featured' => $data['action'] === 'feature']);
                break;
            case 'category':
                $query->get()->each(fn ($p) => $p->update(['category_id' => $data['category_id']]));
                $n = count($data['ids']);
                break;
            case 'delete':
                $query->get()->each(function ($p) {
                    $this->images->delete($p);
                    $p->delete();
                });
                $n = count($data['ids']);
                break;
        }
        Activity::log('products.bulk', null, ['action' => $data['action'], 'count' => $n]);
        $msg = $data['action'] === 'delete' ? "{$n} ürün silindi." : "{$n} ürün güncellendi.";
        if (! empty($skipped)) {
            $msg .= " {$skipped} ürün fiyatı olmadığı için yayına alınmadı.";
        }

        return back()->with('status', $msg);
    }

    /**
     * Ürün listesinden toplu sayfa sayısı girişi: pages[ID] => sayfa. Boş hücre değiştirmez. Fiyatlar sayfa sayısından
     * otomatik hesaplanır; "publish" işaretliyse fiyatı oluşan taslaklar yayına alınır.
     */
    public function pages(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pages' => ['required', 'array', 'max:1000'],
            'pages.*' => ['nullable', 'integer', 'min:1', 'max:20000'],
        ], ['pages.*.integer' => 'Sayfa sayısı tam sayı olmalıdır.', 'pages.*.min' => 'Sayfa sayısı en az 1 olmalıdır.', 'pages.*.max' => 'Sayfa sayısı en fazla 20000 olabilir.']);
        $changes = array_filter($data['pages'], fn ($v) => $v !== null && $v !== '');
        $saved = 0;
        $published = 0;
        foreach (Product::whereIn('id', array_keys($changes))->get() as $p) {
            $p->page_count = (int) $changes[$p->id];
            if ($request->boolean('publish') && $p->status === 'draft') {
                $p->status = 'active';
                $published++;
            }
            if ($p->isDirty()) {
                $p->save();
                $saved++;
            }
        }
        Activity::log('products.pages', null, ['count' => $saved, 'published' => $published]);

        return back()->with('status', "{$saved} ürünün sayfa sayısı kaydedildi, fiyatları hesaplandı".($published ? "; {$published} ürün yayına alındı" : '').'.');
    }

    public function priceTemplate(Request $request, PriceSheet $sheet): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'fiyat').'.xlsx';
        $sheet->export($this->filtered($request), $path);

        return response()->download($path, 'fiyat-sablonu-'.now()->format('Y-m-d-Hi').'.xlsx')->deleteFileAfterSend();
    }

    public function priceImport(Request $request, PriceSheet $sheet): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:20480']], ['file.mimes' => 'Yalnızca .xlsx dosyası yükleyin.']);
        try {
            $result = $sheet->import($request->file('file')->getRealPath(), $request->boolean('publish'));
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['file' => 'Dosya okunamadı. Excel\'de .xlsx olarak kaydedip yeniden deneyin.']);
        }

        return back()->with('status', "Fiyatlar işlendi: {$result['updated']} ürün güncellendi".
            ($result['published'] ? ", {$result['published']} ürün yayına alındı" : '').
            ($result['unchanged'] ? ", {$result['unchanged']} ürün zaten aynıydı" : '').'.')
            ->with('import_errors', array_slice($result['errors'], 0, 100));
    }

    private function validated(Request $request, ?Product $product): array
    {
        $request->merge(['isbn' => preg_replace('/[^0-9X]/i', '', (string) $request->input('isbn')) ?: null]);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:40', Rule::unique('products', 'code')->ignore($product)],
            'isbn' => ['nullable', 'string', 'between:10,13', Rule::unique('products', 'isbn')->ignore($product)],
            'author' => ['nullable', 'string', 'max:150'],
            'publisher_id' => ['nullable', 'integer', 'exists:publishers,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'page_count' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'print_year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'description' => ['nullable', 'string', 'max:20000'],
            'status' => ['required', Rule::in(array_keys(Product::STATUSES))],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:170'],
            'image' => ['nullable', 'image', 'max:15360'],
        ]);
        $prices = [];
        // Satış fiyatları sayfa sayısından hesaplanır (Product::booted); burada yalnız üstü çizili eski fiyatlar alınır.
        foreach (['compare_bw', 'compare_color'] as $field) {
            $raw = $request->input($field);
            if (blank($raw)) {
                $prices[$field] = null;

                continue;
            }
            $value = Money::parse($raw);
            if ($value === null || bccomp($value, '0', 2) <= 0) {
                throw ValidationException::withMessages([$field => 'Geçerli bir tutar girin (ör. 149,90).']);
            }
            $prices[$field] = $value;
        }
        // Eski kayıt: sayfa sayısı hiç girilmemiş, elle fiyatı olan ürün yayında kalabilir.
        $legacyPriced = $product && $product->page_count === null && $product->price_bw !== null;
        if ($data['status'] === 'active' && empty($data['page_count']) && ! $legacyPriced) {
            throw ValidationException::withMessages(['page_count' => 'Sayfa sayısı olmayan ürünün fiyatı hesaplanamaz; yayına almak için sayfa sayısını girin.']);
        }
        $data['author_id'] = Author::resolve($data['author'] ?? null)?->id;
        unset($data['author'], $data['image']);
        $data['is_featured'] = $request->boolean('is_featured');

        return $data + $prices;
    }

    private function saveImage(Request $request, Product $product): void
    {
        if ($request->hasFile('image')) {
            $this->images->store($product, $request->file('image')->getRealPath());
        } elseif ($request->boolean('remove_image')) {
            $this->images->delete($product);
        }
    }
}
