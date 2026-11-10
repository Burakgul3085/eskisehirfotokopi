<?php

namespace App\Domain\Import;

use App\Domain\Catalog\CategoryTree;
use App\Domain\Catalog\ProductImages;
use App\Domain\Import\Ocr\OcrEngine;
use App\Models\Author;
use App\Models\ImportItem;
use App\Models\Product;
use App\Models\Publisher;
use App\Models\PublisherAlias;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Bir kitap klasörünü ürüne dönüştürür (docs/01 §6):
 *  1. Bilgi görseli OCR ile okunur, tablo ayrıştırılır; ISBN ve sayfa sayısı ikinci kez yalnız rakam modunda okunur.
 *  2. OCR sonucu klasör adıyla (YAYINEVİ - KİTAP - YAZAR) karşılaştırılır; uyuşmazlık "kontrol gerekli" olarak işaretlenir.
 *  3. Yayınevi bulunur/oluşturulur, "Türü" yolundan kategori ağacı kurulur.
 *  4. Ürün ISBN → ürün kodu → yayınevi+ad sırasıyla eşleştirilir; yeni ürün taslak açılır (fiyat Excel ile gelir, K2).
 *  5. Mockup WebP'ye çevrilip ürüne bağlanır; özgün dosya silinir. Bilgi görseli kontrol için bir süre saklanır.
 */
class ImportItemProcessor
{
    public function __construct(
        private OcrEngine $ocr,
        private InfoSheetParser $parser,
        private CategoryTree $tree,
        private ProductImages $images,
    ) {}

    public function process(ImportItem $item): void
    {
        $start = hrtime(true);
        $item->update(['status' => 'processing', 'error' => null]);
        $disk = Storage::disk('local');

        try {
            [$data, $issues, $ocrText] = $this->read($item);
            [$product, $action, $applyIssues] = $this->apply($data, $item->product);
            $issues = [...$issues, ...$applyIssues];

            if ($item->mockup_path && $disk->exists($item->mockup_path)) {
                $this->images->store($product, $disk->path($item->mockup_path));
                $disk->delete($item->mockup_path);
            } elseif (! $product->image_key) {
                $issues[] = 'Mockup görseli yok.';
            }

            $item->update([
                'status' => $issues ? 'review' : 'imported',
                'parsed' => $data,
                'issues' => $issues ?: null,
                'ocr_text' => $ocrText,
                'product_id' => $product->id,
                'action' => $item->action ?? $action,
                'mockup_path' => null,
                'duration_ms' => (int) ((hrtime(true) - $start) / 1e6),
            ]);
        } catch (Throwable $e) {
            report($e);
            $item->update([
                'status' => 'failed',
                'error' => mb_substr($e->getMessage(), 0, 500),
                'duration_ms' => (int) ((hrtime(true) - $start) / 1e6),
            ]);
        }

        $item->batch?->refreshStatus();
    }

    /**
     * OCR ve klasör adından alanları çıkarır.
     *
     * @return array{0: array<string, mixed>, 1: list<string>, 2: ?string}
     */
    public function read(ImportItem $item): array
    {
        $folder = FolderName::book($item->folder, $item->publisher_folder);
        $issues = [];
        $ocr = ['title' => null, 'fields' => [], 'boxes' => [], 'conf' => []];
        $ocrText = null;
        $infoPath = $item->info_path ? Storage::disk('local')->path($item->info_path) : null;

        if (! $infoPath || ! is_file($infoPath)) {
            $issues[] = 'Bilgi görseli yok; bilgiler klasör adından alındı.';
            $infoPath = null;
        } elseif (! $this->ocr->available()) {
            $issues[] = 'OCR motoru kurulu değil; bilgiler klasör adından alındı.';
            $infoPath = null;
        } else {
            $ocr = $this->parser->parse($this->ocr->words($infoPath));
            $ocrText = implode("\n", $ocr['lines']);
        }
        $f = $ocr['fields'];

        // Kitap adı: OCR başlığı düzgün harflidir; klasör adıyla uyuşmazsa klasör adı esas alınır (insan yazdı).
        $title = $this->pick('Kitap adı', $ocr['title'], $folder['title'], $issues, 0.85);
        $author = $this->pick('Yazar', $f['author'] ?? null, $folder['author'], $issues, 0.8);

        // Yayınevi: klasör kimliği belirler; OCR adı aynı yayınevini gösteriyorsa düzgün yazımı için kullanılır.
        $publisher = $folder['publisher'] ?? ($f['publisher'] ?? null);
        $publisherOcr = $f['publisher'] ?? null;
        if ($folder['publisher'] && $publisherOcr && Publisher::matchKey($folder['publisher']) !== Publisher::matchKey($publisherOcr)
            && ! FolderName::similar(Publisher::matchKey($folder['publisher']), Publisher::matchKey($publisherOcr), 0.8)) {
            $issues[] = "Yayınevi uyuşmuyor: klasörde “{$folder['publisher']}”, görselde “{$publisherOcr}”.";
        }

        $isbn = null;
        if (! empty($f['isbn'])) {
            $isbn = Isbn::normalize($f['isbn']);
            if ($isbn === null && $infoPath && isset($ocr['boxes']['isbn'])) {
                $isbn = Isbn::normalize($this->ocr->line($infoPath, $ocr['boxes']['isbn'], true));
            }
            if ($isbn === null) {
                $issues[] = "ISBN okunamadı veya geçersiz: “{$f['isbn']}”.";
            }
        }

        $pages = null;
        if (! empty($f['pages'])) {
            $pages = $this->number($f['pages']);
            if ($infoPath && isset($ocr['boxes']['pages'])) {
                // İkinci okuma yalnız rakam modunda; iki okuma farklıysa insan kontrol eder.
                $second = $this->number($this->ocr->line($infoPath, $ocr['boxes']['pages'], true));
                if ($pages === null) {
                    $pages = $second;
                } elseif ($second !== null && $second !== $pages) {
                    $issues[] = "Sayfa sayısı iki okumada farklı: {$pages} / {$second}.";
                }
            }
            if ($pages === null || $pages < 1 || $pages > 5000) {
                $issues[] = "Sayfa sayısı okunamadı: “{$f['pages']}”.";
                $pages = null;
            }
        }

        $year = null;
        if (! empty($f['year']) && preg_match('/\b(19|20)\d{2}\b/', $f['year'], $m) && (int) $m[0] <= (int) date('Y') + 1) {
            $year = (int) $m[0];
        }

        $code = null;
        if (! empty($f['code'])) {
            $code = strtoupper(preg_replace('/\s+/', '', $f['code']));
            if (! preg_match('/^[A-Z0-9][A-Z0-9\-_.\/]{0,39}$/', $code)) {
                $issues[] = "Ürün kodu geçersiz: “{$f['code']}”.";
                $code = null;
            }
        }

        $categoryPath = self::splitCategory($f['category'] ?? null);
        if ($infoPath && ! $categoryPath) {
            $issues[] = 'Türü okunamadı; ürün kategorisiz kaldı.';
        }

        foreach (['author' => 'Yazar', 'publisher' => 'Yayınevi', 'category' => 'Türü', 'code' => 'Ürün kodu'] as $k => $label) {
            if (isset($ocr['conf'][$k], $f[$k]) && $f[$k] !== null && $ocr['conf'][$k] < 45) {
                $issues[] = "{$label} düşük güvenle okundu: “{$f[$k]}”.";
            }
        }

        if ($title === null) {
            $issues[] = 'Kitap adı bulunamadı.';
        }

        return [[
            'title' => $title,
            'author' => $author,
            'publisher' => $publisherOcr && $publisher && Publisher::matchKey($publisherOcr) === Publisher::matchKey($publisher) ? $publisherOcr : $publisher,
            'publisher_folder' => $folder['publisher'],
            'category' => $categoryPath,
            'isbn' => $isbn,
            'pages' => $pages,
            'year' => $year,
            'code' => $code,
            'ocr' => ['title' => $ocr['title'], 'fields' => $f],
        ], $issues, $ocrText];
    }

    /**
     * Alanları ürüne yazar (oluşturur veya günceller). Fiyat ve yayın durumu korunur.
     *
     * @return array{0: Product, 1: string, 2: list<string>}
     */
    public function apply(array $data, ?Product $product = null): array
    {
        $issues = [];
        if (empty($data['title'])) {
            throw new \RuntimeException('Kitap adı bulunamadı; ürün oluşturulamadı.');
        }

        // Yayınevi ve kategori oluşturma yarışını önlemek için kısa kilit (paralel işçiler aynı yayınevini iki kez açmasın).
        [$publisher, $category, $author] = Cache::lock('import-taxonomy', 30)->block(30, function () use ($data) {
            return [
                $this->resolvePublisher($data['publisher'] ?? null, $data['publisher_folder'] ?? null),
                $data['category'] ? $this->tree->ensurePath($data['category']) : null,
                Author::resolve($data['author'] ?? null),
            ];
        });

        return DB::transaction(function () use ($data, $product, $publisher, $category, $author, $issues) {
            $product ??= $this->match($data, $publisher?->id);
            $action = $product ? 'updated' : 'created';

            $isbn = $data['isbn'] ?? null;
            if ($isbn && Product::where('isbn', $isbn)->when($product, fn ($q) => $q->whereKeyNot($product->id))->exists()) {
                $issues[] = "ISBN {$isbn} başka bir üründe kayıtlı; bu ürüne yazılmadı.";
                $isbn = null;
            }
            $code = $data['code'] ?? null;
            if ($code && Product::where('code', $code)->when($product, fn ($q) => $q->whereKeyNot($product->id))->exists()) {
                $issues[] = "Ürün kodu {$code} başka bir üründe kayıtlı; bu ürüne yazılmadı.";
                $code = null;
            }

            $values = array_filter([
                'title' => $data['title'],
                'publisher_id' => $publisher?->id,
                'author_id' => $author?->id,
                'category_id' => $category?->id,
                'isbn' => $isbn,
                'code' => $code,
                'page_count' => $data['pages'] ?? null,
                'print_year' => $data['year'] ?? null,
            ], fn ($v) => $v !== null);

            if ($product) {
                $product->fill($values)->save();
            } else {
                $product = Product::create($values + ['slug' => Product::uniqueSlug($data['title']), 'status' => 'draft']);
            }

            return [$product, $action, $issues];
        });
    }

    /** "Tarih / Avrupa Tarihi > Almanya" → ['Tarih', 'Avrupa Tarihi', 'Almanya'] */
    public static function splitCategory(?string $value): array
    {
        if ($value === null) {
            return [];
        }
        $parts = preg_split('/\s*[\/>›»|\\\\]\s*/u', $value);

        return array_values(array_filter(array_map(fn ($p) => trim($p, " \t-–:;,."), $parts), fn ($p) => mb_strlen($p) >= 2));
    }

    private function match(array $data, ?int $publisherId): ?Product
    {
        if (! empty($data['isbn']) && $p = Product::where('isbn', $data['isbn'])->first()) {
            return $p;
        }
        if (! empty($data['code']) && $p = Product::where('code', $data['code'])->first()) {
            return $p;
        }

        // Harmanlama (utf8mb4_turkish_ci) büyük/küçük harfi yok sayar.
        return Product::where('title', $data['title'])->where('publisher_id', $publisherId)->first();
    }

    private function resolvePublisher(?string $name, ?string $folderName): ?Publisher
    {
        if (! $name) {
            return null;
        }
        $publisher = Publisher::findByName($name) ?? ($folderName ? Publisher::findByName($folderName) : null);
        if (! $publisher) {
            $display = CategoryTree::displayName(trim($name));
            $publisher = Publisher::create([
                'name' => $display,
                'slug' => Publisher::uniqueSlug($display),
                'match_key' => Publisher::matchKey($display),
                'is_active' => true,
            ]);
        }
        // Klasör adı başka bir anahtara çıkıyorsa takma ad olarak eklenir; sonraki kitaplar doğrudan eşleşir.
        if ($folderName) {
            $key = Publisher::matchKey($folderName);
            if ($key !== '' && $key !== $publisher->match_key && ! PublisherAlias::where('match_key', $key)->exists()
                && ! Publisher::where('match_key', $key)->exists()) {
                $publisher->aliases()->create(['match_key' => $key]);
            }
        }

        return $publisher;
    }

    /** OCR değeri ile klasör değerinden birini seçer. */
    private function pick(string $label, ?string $ocr, ?string $folder, array &$issues, float $threshold): ?string
    {
        if ($ocr && $folder) {
            if (FolderName::similar($ocr, $folder, $threshold)) {
                return $ocr;
            }
            $issues[] = "{$label} uyuşmuyor: klasörde “{$folder}”, görselde “{$ocr}”.";

            return CategoryTree::displayName($folder);
        }
        if ($folder) {
            return CategoryTree::displayName($folder);
        }

        return $ocr;
    }

    private function number(?string $s): ?int
    {
        $s = strtr((string) $s, ['O' => '0', 'o' => '0', 'I' => '1', 'l' => '1', '|' => '1']);

        return preg_match('/\d{1,5}/', $s, $m) ? (int) $m[0] : null;
    }
}
