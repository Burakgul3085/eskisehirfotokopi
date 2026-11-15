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
use App\Support\Text;
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

            // Kapak ve künye yalnız okunmak içindir; işlendikten sonra silinir.
            foreach (['cover_path', 'imprint_path'] as $col) {
                if ($item->{$col}) {
                    $disk->delete($item->{$col});
                }
            }

            if ($item->mockup_path && $disk->exists($item->mockup_path)) {
                $this->images->store($product, $disk->path($item->mockup_path));
                $disk->delete($item->mockup_path);
            } elseif (! $product->image_key) {
                $issues[] = 'Ürün görseli yok (klasörde MOCKUP veya KAPAK adlı görsel bulunamadı); ürün sayfasından ekleyin.';
            }

            $item->update([
                'status' => $issues ? 'review' : 'imported',
                'parsed' => $data,
                'issues' => $issues ?: null,
                'ocr_text' => $ocrText,
                'product_id' => $product->id,
                'action' => $item->action ?? $action,
                'mockup_path' => null,
                'cover_path' => null,
                'imprint_path' => null,
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

        // Bilgi tablosu dışındaki sayfalar: kapak (yazar, özellikler) ve künye (baskı yılı, ISBN).
        $page = ['author' => null, 'features' => [], 'isbn' => null, 'year' => null];
        if ($this->ocr->available()) {
            foreach (['cover' => 'Kapak', 'imprint' => 'Künye'] as $role => $label) {
                $path = $item->{$role.'_path'} ? Storage::disk('local')->path($item->{$role.'_path'}) : null;
                if (! $path || ! is_file($path)) {
                    continue;
                }
                try {
                    $text = $this->ocr->text($path);
                } catch (Throwable) {
                    continue; // kapak okunamazsa kitap yine işlenir
                }
                $read = $role === 'cover' ? PageReader::cover($text) : PageReader::imprint($text);
                $page = array_merge($page, array_filter($read, fn ($v) => $v !== null && $v !== []));
                $ocrText = trim(($ocrText ?? '')."\n\n--- {$label} ---\n".trim($text));
            }
        }

        // Kitap adı: görseldeki başlık "Ad — Alt başlık" ya da "Ad — Yazar" olabilir; ad klasörle karşılaştırılır.
        [$ocrTitle, $ocrRest] = self::splitTitle($ocr['title']);
        if ($ocrRest !== null && ! FolderName::similar($ocrTitle, $folder['title'], 0.85) && FolderName::similar($ocr['title'], $folder['title'], 0.85)) {
            [$ocrTitle, $ocrRest] = [$ocr['title'], null]; // adın kendisinde tire varmış
        }
        $title = $this->pick('Kitap adı', $ocrTitle, $folder['title'], $issues, 0.85);
        [$author, $authorConfirmed] = $this->pickAuthor($f['author'] ?? null, $folder['author'], $issues);
        $subtitle = null;
        if ($ocrRest !== null && ! FolderName::similar($ocrRest, $author, 0.75) && ! FolderName::similar($ocrRest, $folder['author'], 0.75)) {
            $subtitle = $ocrRest;
            if (preg_match('/\p{L}\d|\d\p{L}/u', $subtitle)) {
                $issues[] = "Alt başlık görselden okundu, harf hatası olabilir: “{$subtitle}”.";
            }
        }
        if ($author === null && $page['author']) {
            $author = $page['author'];
            $issues[] = "Yazar kapaktan okundu: “{$author}”; doğruluğunu kontrol edin.";
        }

        // Yayınevi: klasör kimliği belirler; OCR adı aynı yayınevini gösteriyorsa düzgün yazımı için kullanılır.
        $publisher = $folder['publisher'] ?? ($f['publisher'] ?? null);
        $publisherOcr = $f['publisher'] ?? null;
        if ($folder['publisher'] && $publisherOcr && ! Publisher::sameName($folder['publisher'], $publisherOcr)) {
            $issues[] = "Yayınevi uyuşmuyor: klasörde “{$folder['publisher']}”, görselde “{$publisherOcr}”.";
        }

        $isbn = null;
        if (empty($f['isbn']) && $page['isbn']) {
            $isbn = $page['isbn']; // kapak/künyedeki ISBN (kontrol hanesi tutuyor)
        }
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

        // Öncelik: bilgi tablosundaki yıl → künyedeki baskı tarihi → klasör adının sonundaki yıl ("… SORU BANKASI 2023").
        $year = $page['year'] ?? $folder['year'];
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
        if (! $categoryPath && $categoryPath = TitleCategory::guess($title)) {
            // "Türü" yok: kitap adından tahmin (Soru Bankası › TYT › Biyoloji); kontrol ekranında düzeltilebilir.
            if ($infoPath) {
                $issues[] = 'Türü okunamadı; kategori kitap adından tahmin edildi: '.implode(' › ', $categoryPath).'.';
            }
        } elseif ($infoPath && ! $categoryPath) {
            $issues[] = 'Türü okunamadı; ürün kategorisiz kaldı.';
        }

        // Düşük güvenli okuma uyarısı, değer klasörle doğrulandıysa ya da kategori adları tanındıysa gereksizdir.
        $confirmed = [
            'author' => $authorConfirmed,
            'publisher' => $folder['publisher'] && $publisherOcr && Publisher::sameName($folder['publisher'], $publisherOcr),
            'category' => $categoryPath && collect($categoryPath)->every(fn ($n) => $this->tree->closestName($n) !== null),
        ];
        foreach (['author' => 'Yazar', 'publisher' => 'Yayınevi', 'category' => 'Türü', 'code' => 'Ürün kodu'] as $k => $label) {
            if (! ($confirmed[$k] ?? false) && isset($ocr['conf'][$k], $f[$k]) && $f[$k] !== null && $ocr['conf'][$k] < 45) {
                $issues[] = "{$label} düşük güvenle okundu: “{$f[$k]}”.";
            }
        }

        if ($title === null) {
            $issues[] = 'Kitap adı bulunamadı.';
        }

        return [[
            'title' => $title,
            'subtitle' => $subtitle,
            'author' => $author,
            'publisher' => $publisherOcr && $publisher && Publisher::matchKey($publisherOcr) === Publisher::matchKey($publisher) ? $publisherOcr : $publisher,
            'publisher_folder' => $folder['publisher'],
            'category' => $categoryPath,
            'isbn' => $isbn,
            'pages' => $pages,
            'year' => $year,
            'code' => $code,
            'description' => $page['features'] ? implode("\n", array_map(fn ($x) => '• '.$x, $page['features'])) : null,
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
                $data['category'] ? $this->tree->ensurePath($data['category'], fuzzy: true) : null,
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
                'subtitle' => $data['subtitle'] ?? null,
                'publisher_id' => $publisher?->id,
                'author_id' => $author?->id,
                'category_id' => $category?->id,
                'isbn' => $isbn,
                'code' => $code,
                'page_count' => $data['pages'] ?? null,
                'print_year' => $data['year'] ?? null,
            ], fn ($v) => $v !== null);

            // Açıklama (kapaktaki özellikler) yalnız boşsa yazılır; elle girilen açıklama korunur.
            if (! empty($data['description']) && (! $product || blank($product->description))) {
                $values['description'] = $data['description'];
            }

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
        $ocr = $ocr !== null ? self::clean($ocr) : null;
        if ($ocr && $folder) {
            // Harfler birebir aynıysa görseldeki düzgün yazım (büyük/küçük harf, kesme işareti) kullanılır;
            // yalnız benziyorsa OCR bir harfi yanlış okumuştur ("\Mimanya"), doğru harfler klasördedir.
            if (self::sameLetters($ocr, $folder)) {
                return $ocr;
            }
            if (FolderName::similar($ocr, $folder, $threshold)) {
                return CategoryTree::displayName($folder);
            }
            $issues[] = "{$label} uyuşmuyor: klasörde “{$folder}”, görselde “{$ocr}”.";

            return CategoryTree::displayName($folder);
        }
        if ($folder) {
            return CategoryTree::displayName($folder);
        }

        return $ocr;
    }

    /**
     * Yazar: görseldeki liste ("Yoshi Olda, Lorna Marshall") klasördeki adla (çoğu zaman kısaltılmış: "YOSHİ OİDA LORNA")
     * birleştirilir. Görseldeki kelimeler klasördeki doğru yazımla düzeltilir; klasördeki ad bir yazarın daha uzun
     * biçimiyse ("V S RAMACHANDRAN") o kullanılır. Klasördeki tüm kelimeler sonuçta varsa yazar doğrulanmış sayılır.
     *
     * @return array{0: ?string, 1: bool} [yazar, klasörle doğrulandı mı]
     */
    private function pickAuthor(?string $ocr, ?string $folder, array &$issues): array
    {
        $ocr = $ocr !== null ? self::clean($ocr) : null;
        if (! $ocr || ! $folder) {
            return [$this->pick('Yazar', $ocr, $folder, $issues, 0.8), false];
        }
        if (self::sameLetters($ocr, $folder)) {
            return [$ocr, true];
        }
        $folderWords = array_values(array_filter(explode(' ', CategoryTree::displayName(trim($folder)))));
        $folderKeys = array_map(fn ($w) => Text::key($w), $folderWords);
        $lists = [];
        foreach (preg_split('/\s*[,;&]\s*|\s+ve\s+/u', $ocr) as $name) {
            $words = array_values(array_filter(explode(' ', self::clean($name))));
            foreach ($words as $i => $w) {
                // Harf hatası: klasördeki benzer kelimeyle değiştirilir ("Olda" → "Oida", "Samachandran" → "Ramachandran")
                foreach ($folderWords as $j => $fw) {
                    $a = Text::key($w);
                    $len = max(strlen($a), strlen($folderKeys[$j]));
                    if ($a !== $folderKeys[$j] && $len >= 4 && levenshtein($a, $folderKeys[$j]) <= ($len >= 8 ? 2 : 1)) {
                        $words[$i] = $fw;
                    }
                }
            }
            if ($words) {
                $lists[] = $words;
            }
        }
        $authors = [];
        foreach ($lists as $n => $words) {
            $keys = array_map(fn ($w) => Text::key($w), $words);
            $others = [];
            foreach ($lists as $m => $ws) {
                if ($m !== $n) {
                    array_push($others, ...array_map(fn ($w) => Text::key($w), $ws));
                }
            }
            // Klasör bu yazarın daha uzun biçimi ("V S Ramachandran" ⊃ "Ramachandran"); fazla kelimeler başka yazara
            // aitse ("YOSHİ OİDA LORNA" → Lorna ikinci yazar) değiştirilmez.
            $extra = array_diff($folderKeys, $keys);
            if (count($folderKeys) > count($keys) && ! array_diff($keys, $folderKeys) && ! array_intersect($extra, $others)) {
                $words = $folderWords;
            }
            $authors[] = implode(' ', $words);
        }
        $all = array_map(fn ($w) => Text::key($w), explode(' ', implode(' ', $authors)));
        if ($authors && ! array_diff($folderKeys, $all)) {
            return [implode(', ', array_unique($authors)), true];
        }

        return [$this->pick('Yazar', $ocr, $folder, $issues, 0.8), false];
    }

    /** Harfler aynı mı (büyük/küçük harf, boşluk, kesme işareti ve ı/i farkı yok sayılır; ğ/g, ü/u gibi farklar sayılır). */
    public static function sameLetters(string $a, string $b): bool
    {
        $norm = fn ($s) => str_replace('ı', 'i', preg_replace('/[^\p{L}\p{N}]+/u', '', Text::lower($s)));

        return $norm($a) !== '' && $norm($a) === $norm($b);
    }

    /** "Ad — Alt başlık" → ['Ad', 'Alt başlık']; ayraç yoksa [ad, null]. */
    public static function splitTitle(?string $title): array
    {
        $title = $title !== null ? self::clean($title) : null;
        if ($title === null || $title === '') {
            return [null, null];
        }
        $parts = preg_split('/\s+[—–-]\s+|\s*:\s+/u', $title, 2);
        $rest = isset($parts[1]) ? self::clean($parts[1]) : '';

        return [self::clean($parts[0]), mb_strlen(preg_replace('/[^\p{L}]/u', '', $rest)) >= 3 ? $rest : null];
    }

    /** Baştaki/sondaki OCR artıklarını atar: "\Mimanya'nın", "* Ah", "“Samachandran". */
    public static function clean(string $s): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s));

        return trim(preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}.)!?]+$/u', '', $s));
    }

    private function number(?string $s): ?int
    {
        $s = strtr((string) $s, ['O' => '0', 'o' => '0', 'I' => '1', 'l' => '1', '|' => '1']);

        return preg_match('/\d{1,5}/', $s, $m) ? (int) $m[0] : null;
    }
}
