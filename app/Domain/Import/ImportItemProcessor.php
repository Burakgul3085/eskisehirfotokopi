<?php

namespace App\Domain\Import;

use App\Domain\Catalog\CategoryMapper;
use App\Domain\Catalog\CategoryTree;
use App\Domain\Catalog\ProductImages;
use App\Domain\Import\Ocr\OcrEngine;
use App\Models\Author;
use App\Models\Category;
use App\Models\ImportItem;
use App\Models\PlacementRule;
use App\Models\Product;
use App\Models\Publisher;
use App\Models\PublisherAlias;
use App\Support\EmptyValue;
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
    /** @var list<int> son apply() çağrısında açılan kategoriler */
    private array $createdCategories = [];

    /** @var ?array{category: int, confidence: string, reasons: list<string>, alternative: ?int} son apply() yerleştirme kararı */
    private ?array $placement = null;

    /** Kitap Türkçe dışında bir dildeyse ı/i düzeltmesi yapılmaz ("DIE" Almancada "Die"dir, "Dıe" değil). */
    private bool $turkishText = true;

    public function __construct(
        private OcrEngine $ocr,
        private InfoSheetParser $parser,
        private CategoryTree $tree,
        private ProductImages $images,
        private CategoryMapper $mapper,
    ) {}

    public function process(ImportItem $item): void
    {
        $start = hrtime(true);
        $item->update(['status' => 'processing', 'error' => null]);
        $disk = Storage::disk('local');

        try {
            [$data, $issues, $ocrText] = $this->read($item);
            $data['default_category_id'] = $item->batch?->default_category_id;
            $data['info_read'] = (bool) $item->info_path;
            [$product, $action, $applyIssues] = $this->apply($data, $item->product);
            $issues = [...$issues, ...$applyIssues];

            // Ürün görseli: MOCKUP; yoksa ve üründe görsel de yoksa KAPAK görseli kullanılır.
            if ($item->mockup_path && $disk->exists($item->mockup_path)) {
                $this->images->store($product, $disk->path($item->mockup_path));
                $disk->delete($item->mockup_path);
            } elseif (! $product->image_key && $item->cover_path && $disk->exists($item->cover_path)) {
                $this->images->store($product, $disk->path($item->cover_path));
                $issues[] = 'MOCKUP görseli yoktu; ürün görseli olarak kapak kullanıldı.';
            } elseif (! $product->image_key) {
                $issues[] = 'Ürün görseli yok (klasörde MOCKUP veya KAPAK adlı görsel bulunamadı); ürün sayfasından ekleyin.';
            }

            // Kapak ve künye yalnız okunmak içindir; işlendikten sonra silinir.
            foreach (['cover_path', 'imprint_path'] as $col) {
                if ($item->{$col}) {
                    $disk->delete($item->{$col});
                }
            }
            $data['new_categories'] = $this->createdCategories;
            $data['placement'] = $this->placement; // yükleme sayfasındaki "tahminle yerleştirilenler" listesi için

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
        $ocr = ['title' => null, 'fields' => [], 'boxes' => [], 'conf' => [], 'extras' => [], 'disagree' => [], 'language' => null, 'languages' => null];
        $ocrText = null;
        $infoPath = $item->info_path ? Storage::disk('local')->path($item->info_path) : null;

        if (! $infoPath || ! is_file($infoPath)) {
            $issues[] = 'Bilgi görseli yok; bilgiler klasör adından alındı.';
            $infoPath = null;
        } elseif (! $this->ocr->available()) {
            $issues[] = 'OCR motoru kurulu değil; bilgiler klasör adından alındı.';
            $infoPath = null;
        } else {
            $ocr = $this->readInfo($infoPath, $item->folder.' '.$item->publisher_folder);
            $ocrText = implode("\n", $ocr['lines']);
        }
        $f = $ocr['fields'];
        $this->turkishText = $ocr['language'] === null;

        // Bilgi tablosu dışındaki sayfalar: kapak (yazar, özellikler) ve künye (baskı yılı, ISBN).
        $page = ['author' => null, 'features' => [], 'isbn' => null, 'year' => null];
        if ($this->ocr->available()) {
            foreach (['cover' => 'Kapak', 'imprint' => 'Künye'] as $role => $label) {
                $path = $item->{$role.'_path'} ? Storage::disk('local')->path($item->{$role.'_path'}) : null;
                if (! $path || ! is_file($path)) {
                    continue;
                }
                try {
                    $text = $this->ocr->text($path, $ocr['languages']);
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
        // Tabloda "Kitap Adı" / "Alt Başlık" satırları varsa üstteki başlıktan önce gelir.
        if (! empty($f['title'])) {
            $ocrTitle = self::clean($f['title']);
        }
        if (! empty($f['subtitle'])) {
            $ocrRest = self::clean($f['subtitle']);
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
                // İlk okumada rakamların arasına işaret/harf karışmışsa ("178" → "1/8") rakam modundaki okuma esas alınır
                if ($pages === null || ($second !== null && preg_match('/\d[^\d\s]+\d/u', (string) $f['pages']))) {
                    $pages = $second ?? $pages;
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
            // Koddan sonra gelen OCR artıkları ("BHY-638 “O”") atılır: ilk kod biçimli parça alınır
            $code = preg_match('/[A-Z0-9][A-Z0-9\-_.\/]{2,39}/', strtoupper($f['code']), $m) ? $m[0] : strtoupper(preg_replace('/\s+/', '', $f['code']));
            if (! preg_match('/^[A-Z0-9][A-Z0-9\-_.\/]{0,39}$/', $code)) {
                $issues[] = "Ürün kodu geçersiz: “{$f['code']}”.";
                $code = null;
            }
        }

        // "Türü" yolu ağaca eklenmez: apply() kısa listeden kategori seçer, kalan ayrıntılar konu etiketi olur.
        // Kitap türü (Soru Bankası…) ve ders (Matematik…) kitap adından okunur; bunlar kategori değil, süzgeçtir.
        $categoryPath = self::splitCategory($f['category'] ?? null);

        // Düşük güvenli okuma uyarısı, değer klasörle doğrulandıysa gereksizdir. "Türü" kategoriye eşlenirken ayrıca
        // denetlenir (eşlenemezse uyarı apply()'dan gelir).
        $confirmed = [
            'author' => $authorConfirmed,
            'publisher' => $folder['publisher'] && $publisherOcr && Publisher::sameName($folder['publisher'], $publisherOcr),
            'category' => true,
        ];
        foreach (['author' => 'Yazar', 'publisher' => 'Yayınevi', 'code' => 'Ürün kodu'] as $k => $label) {
            if (! ($confirmed[$k] ?? false) && isset($ocr['conf'][$k], $f[$k]) && $f[$k] !== null && $ocr['conf'][$k] < 45) {
                $issues[] = "{$label} düşük güvenle okundu: “{$f[$k]}”.";
            }
        }

        // Okumalar (farklı ön işlemler) bir alanda anlaşamadıysa ve değer başka yoldan doğrulanmadıysa insan bakar.
        $confirmed += [
            'title' => (bool) $folder['title'],          // klasörle karşılaştırıldı (uyuşmazlık ayrıca yazılır)
            'isbn' => $isbn !== null,                     // kontrol hanesi tuttu
            'pages' => true,                              // rakam modunda ikinci okuma ayrıca yapılıyor
            'year' => $year !== null,
        ];
        foreach ($ocr['disagree'] as $k => $values) {
            if ($confirmed[$k] ?? false) {
                continue;
            }
            $label = self::FIELD_LABELS[$k] ?? (str_starts_with($k, 'extra:') ? substr($k, 6) : $k);
            $issues[] = "{$label} okumaları birbirini tutmuyor (".implode(' / ', array_map(fn ($v) => "“{$v}”", $values)).'); en sık okunan yazıldı, kontrol edin.';
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
            'folder_title' => $folder['title'],
            'isbn' => $isbn,
            'pages' => $pages,
            'year' => $year,
            'code' => $code,
            'description' => $page['features'] ? implode("\n", array_map(fn ($x) => '• '.$x, $page['features'])) : null,
            'extras' => array_map(fn ($e) => ['label' => self::clean($e['label']), 'value' => $e['value']], $ocr['extras']),
            'language' => $ocr['language'],
            'boxes' => $ocr['boxes'] + collect($ocr['extras'])->mapWithKeys(fn ($e, $i) => ['extra:'.$i => $e['box'] ?? null])->filter()->all(),
            'box_scale' => $infoPath ? $this->ocr->scale($infoPath) : null,
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
        // Daha önce açılmış yayınevi, kategori ve yazar yeniden açılmaz: adın yazımı/OCR hatası farklı olsa da eşlenir.
        // Kategori: kontrol ekranında seçildiyse o; değilse "Türü" yolu ve kitap adı kısa listeye eşlenir (yeni dal açılmaz).
        // Yerleştirme yayınevi ve yazarın geçmişine de bakar (345 Yayınları → sınav kitabı, Russell → Felsefe).
        [$publisher, $mapped, $author] = Cache::lock('import-taxonomy', 30)->block(30, function () use ($data) {
            $this->mapper->flush();
            $publisher = $this->resolvePublisher($data['publisher'] ?? null, $data['publisher_folder'] ?? null);
            $author = Author::resolve($data['author'] ?? null);
            $mapped = array_key_exists('category_id', $data)
                ? ['category' => $data['category_id'] ? Category::find($data['category_id']) : null, 'topics' => $data['topics'] ?? [],
                    'matched' => true, 'created' => [], 'confidence' => 'high', 'reasons' => ['Yönetici seçti'], 'alternative' => null,
                    'book_type' => $data['book_type'] ?? null, 'subject' => $data['subject'] ?? null]
                : $this->mapper->map($data['category'] ?? [], $data['title'], $data['default_category_id'] ?? null, [
                    'folder_title' => $data['folder_title'] ?? null, 'subtitle' => $data['subtitle'] ?? null,
                    'publisher_id' => $publisher?->id, 'author_id' => $author?->id, 'isbn' => $data['isbn'] ?? null, 'code' => $data['code'] ?? null,
                    'extras' => array_column($data['extras'] ?? [], 'label'),
                ]);

            return [$publisher, $mapped, $author];
        });
        $category = $mapped['category'];
        $this->createdCategories = $mapped['created'];
        if (! empty($mapped['rule'])) {
            PlacementRule::whereKey($mapped['rule'])->increment('applied_count');
        }
        $this->placement = $category ? [
            'category' => $category->id, 'confidence' => $mapped['confidence'], 'reasons' => $mapped['reasons'], 'alternative' => $mapped['alternative'],
        ] : null;

        return DB::transaction(function () use ($data, $product, $publisher, $category, $mapped, $author, $issues) {
            $matched = $product === null;
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
                'book_type' => $mapped['book_type'] ?? null,
                'subject' => $mapped['subject'] ?? null,
                'isbn' => $isbn,
                'code' => $code,
                'page_count' => $data['pages'] ?? null,
                'print_year' => $data['year'] ?? null,
            ], fn ($v) => $v !== null);
            // Eşleşen mevcut üründe ad yalnız yazımca farklıysa ("Almanyanın" / "Almanya'nın") mevcut yazım korunur.
            if ($matched && $product && self::titleKey($product->title) === self::titleKey($data['title'])) {
                unset($values['title']);
            }
            if (array_key_exists('category_id', $data)) { // kontrol ekranı: boş bırakılan alan silinir
                foreach (['category_id', 'book_type', 'subject'] as $k) {
                    $values[$k] = $data[$k] ?? null;
                }
            }
            if (($values['subtitle'] ?? null) === '') {
                $values['subtitle'] = null; // kontrol ekranında boş bırakılan alt başlık silinir
            }

            // Ek bilgiler: kontrol ekranından gelen liste olduğu gibi yazılır; OCR'den gelenler yalnız eksik olanları
            // tamamlar (elle düzeltilmiş değer ezilmez).
            if (array_key_exists('spec_visibility', $data)) {
                $values['spec_visibility'] = $data['spec_visibility'] ?: null;
            }
            if (array_key_exists('extra_specs', $data)) {
                $values['extra_specs'] = $data['extra_specs'] ?: null;
            } elseif (! empty($data['extras'])) {
                $rows = $product?->extra_specs ?? [];
                foreach ($data['extras'] as $e) {
                    if (EmptyValue::is($e['value'] ?? null) || trim((string) ($e['label'] ?? '')) === '') {
                        continue;
                    }
                    $at = collect($rows)->search(fn ($r) => Text::key($r['label'] ?? '') === Text::key($e['label']));
                    if ($at === false) {
                        $rows[] = ['label' => $e['label'], 'value' => $e['value']];
                    } elseif (EmptyValue::is($rows[$at]['value'] ?? null)) {
                        $rows[$at]['value'] = $e['value'];
                    }
                }
                $values['extra_specs'] = $rows ?: null;
            }

            // Konu etiketleri: kontrol ekranından gelen liste olduğu gibi; OCR'den gelenler mevcutlara eklenir.
            if (array_key_exists('topics', $data) && array_key_exists('category_id', $data)) {
                $values['topics'] = array_values(array_filter($data['topics'] ?? [])) ?: null;
            } elseif ($mapped['topics']) {
                $values['topics'] = $this->mapper->cleanTopics([...($product?->topics ?? []), ...$mapped['topics']], $category) ?: null;
            }

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

    public const FIELD_LABELS = [
        'title' => 'Kitap adı', 'subtitle' => 'Alt başlık', 'author' => 'Yazar', 'publisher' => 'Yayınevi', 'category' => 'Türü',
        'code' => 'Ürün kodu', 'isbn' => 'ISBN', 'pages' => 'Sayfa sayısı', 'year' => 'Baskı yılı',
    ];

    /**
     * Bilgi görselini okur: önce varsayılan dillerle; metin başka bir dili gösteriyorsa (Almanca, İtalyanca…) o dil
     * eklenerek yeniden. Ardından diğer ön işlem biçimleriyle de okunur ve sonuçlar alan alan oylanır (vote()).
     */
    private function readInfo(string $path, string $hint): array
    {
        $base = config('catalog.ocr_languages');
        $first = $this->parser->parse($this->ocr->words($path, 0, $base));
        $language = LanguageGuess::guess(implode("\n", $first['lines'])."\n".$hint);
        $languages = $base;
        $extra = array_filter(array_map('trim', explode(',', (string) config('catalog.ocr_extra_languages'))));
        if ($language && in_array($language, $extra, true) && in_array($language, $this->ocr->languages(), true)) {
            $languages = self::withLanguage($base, $language);
            $first = $this->parser->parse($this->ocr->words($path, 0, $languages));
        }
        $reads = [$first];
        for ($pass = 1; $pass < $this->ocr->passes(); $pass++) {
            try {
                $reads[] = $this->parser->parse($this->ocr->words($path, $pass, $languages));
            } catch (Throwable) {
                // ek okuma başarısızsa eldekilerle devam edilir
            }
        }

        return ['language' => $language, 'languages' => $languages] + self::vote($reads);
    }

    /** "tur+eng" + deu → "tur+deu+eng" (Türkçe etiketler önce, kitabın dili ikinci). */
    public static function withLanguage(string $languages, string $language): string
    {
        $list = array_values(array_diff(explode('+', $languages), [$language]));
        array_splice($list, min(1, count($list)), 0, [$language]);

        return implode('+', $list);
    }

    /**
     * Birden çok okumanın oylanması: her alanda harfleri aynı olan değerler gruplanır, en çok okunan kazanır
     * (eşitlikte güveni yüksek olan). Kazanan grup okumaların yarısından fazlası değilse alan "disagree"e yazılır.
     * Ek bilgiler etiketlerine göre eşlenir; okumaların çoğunda görünmeyen ek satır gürültü sayılıp atılır.
     *
     * @param  list<array>  $reads  InfoSheetParser::parse sonuçları (ilki koordinat kaynağıdır)
     */
    public static function vote(array $reads): array
    {
        $out = $reads[0] + ['extras' => []];
        $out['disagree'] = [];
        if (count($reads) < 2) {
            return $out;
        }
        $keys = array_unique(array_merge(...array_map(fn ($r) => array_keys($r['fields']), $reads)));
        foreach ($keys as $k) {
            $candidates = array_map(fn ($r) => [$r['fields'][$k] ?? null, $r['conf'][$k] ?? 0.0], $reads);
            if ($k === 'category') {
                // Yolun başındaki/sonundaki 3 harften kısa artıklar ("dh /", "Ah/") oylamadan önce atılır
                $candidates = array_map(fn ($c) => [$c[0] === null ? null : (implode(' / ', array_filter(self::splitCategory($c[0]),
                    fn ($seg) => mb_strlen(preg_replace('/[^\p{L}]/u', '', $seg)) >= 3)) ?: null), $c[1]], $candidates);
            }
            [$value, $conf, $ok, $all] = self::choose($candidates);
            $out['fields'][$k] = $value;
            $out['conf'][$k] = $conf;
            if (! $ok) {
                $out['disagree'][$k] = $all;
            }
        }
        [$title, , $ok, $all] = self::choose(array_map(fn ($r) => [$r['title'], 90.0], $reads));
        $out['title'] = $title;
        if (! $ok) {
            $out['disagree']['title'] = $all;
        }

        $groups = [];
        foreach ($reads as $n => $r) {
            foreach ($r['extras'] ?? [] as $e) {
                $groups[Text::key($e['label'])][$n] ??= $e;
            }
        }
        $extras = [];
        foreach ($groups as $group) {
            if (count($group) * 2 <= count($reads)) {
                continue;
            }
            [$label] = self::choose(array_map(fn ($e) => [$e['label'], $e['conf'] ?? 0.0], $group));
            [$value, $conf, $ok, $all] = self::choose(array_map(fn ($e) => [$e['value'], $e['conf'] ?? 0.0], $group));
            $extras[] = ['label' => $label, 'value' => $value, 'box' => isset($group[0]) ? $group[0]['box'] : null, 'conf' => $conf];
            if (! $ok) {
                $out['disagree']['extra:'.$label] = $all;
            }
        }
        $out['extras'] = $extras;

        return $out;
    }

    /**
     * @param  list<array{0: ?string, 1: float}>  $candidates  [değer, güven]
     * @return array{0: ?string, 1: float, 2: bool, 3: list<string>} [seçilen, güveni, çoğunluk var mı, farklı değerler]
     */
    private static function choose(array $candidates): array
    {
        $norm = fn ($s) => str_replace('ı', 'i', preg_replace('/[^\p{L}\p{N}]+/u', '', Text::lower($s)));
        $groups = [];
        $total = 0;
        foreach ($candidates as [$value, $conf]) {
            if ($value === null || $value === '') {
                continue;
            }
            $total++;
            $groups[$norm($value)][] = [$value, (float) $conf];
        }
        if ($groups === []) {
            return [null, 0.0, true, []];
        }
        uasort($groups, fn ($a, $b) => count($b) <=> count($a) ?: array_sum(array_column($b, 1)) <=> array_sum(array_column($a, 1)));
        $best = reset($groups);
        usort($best, fn ($a, $b) => $b[1] <=> $a[1]);
        $all = array_values(array_unique(array_map(fn ($g) => $g[0][0], $groups)));

        return [$best[0][0], $best[0][1], count($groups) === 1 || count(reset($groups)) * 2 > $total, $all];
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

        // Aynı yayınevinde aynı adlı kitap (büyük/küçük harf, kesme işareti, Türkçe harf farkı yok sayılır):
        // "ALMANYANIN KISA TARİHİ" ile "Almanya'nın Kısa Tarihi" aynı üründür, yeniden açılmaz.
        $key = self::titleKey($data['title']);
        $id = Product::where('publisher_id', $publisherId)->select('id', 'title')->cursor()
            ->first(fn ($p) => self::titleKey($p->title) === $key)?->id;

        return $id ? Product::find($id) : null;
    }

    /** Aynı kitap adı karşılaştırması: harfler ve rakamlar (boşluk, kesme işareti, Türkçe harf farkı yok sayılır). */
    public static function titleKey(string $title): string
    {
        return str_replace(' ', '', Text::key($title));
    }

    private function resolvePublisher(?string $name, ?string $folderName): ?Publisher
    {
        if (! $name) {
            return null;
        }
        $publisher = Publisher::findByName($name) ?? ($folderName ? Publisher::findByName($folderName) : null);
        // Yazımı biraz farklı ama aynı yayınevi ("Boğaziçi Yayınları" / "Boğaziçi Üniversitesi Yayınevi", OCR harf hatası)
        $publisher ??= Publisher::query()->get()->first(fn ($p) => Publisher::sameName($p->name, $name)
            || ($folderName && Publisher::sameName($p->name, $folderName)));
        if ($publisher && ($key = Publisher::matchKey($name)) !== '' && $key !== $publisher->match_key
            && ! PublisherAlias::where('match_key', $key)->exists() && ! Publisher::where('match_key', $key)->exists()) {
            $publisher->aliases()->create(['match_key' => $key]);
        }
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
                return $this->turkishText ? self::fixDotless($ocr, $folder) : $ocr;
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
            return [$this->turkishText ? self::fixDotless($ocr, $folder) : $ocr, true];
        }
        $folderWords = array_values(array_filter(explode(' ', CategoryTree::displayName(trim($folder)))));
        $folderKeys = array_map(fn ($w) => Text::key($w), $folderWords);
        $lists = [];
        foreach (preg_split('/\s*[,;&]\s*|\s+ve\s+/u', $ocr) as $name) {
            $words = array_values(array_filter(explode(' ', self::clean($name))));
            foreach ($words as $i => $w) {
                // Harf hatası: klasördeki benzer kelimeyle değiştirilir ("Olda" → "Oida", "Samachandran" → "Ramachandran")
                foreach ($folderWords as $j => $fw) {
                    if (self::similarWord($w, $folderKeys[$j])) {
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
            $extraBelongsToOther = collect($extra)->contains(fn ($e) => collect($others)->contains(fn ($o) => $o === $e || self::similarWord($o, $e)));
            if (count($folderKeys) > count($keys) && ! array_diff($keys, $folderKeys) && ! $extraBelongsToOther) {
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

    /**
     * OCR'nin harf karıştırması mı: en fazla 1 harf farkı (8+ harfte 2), ya da sık görülen "rn" ↔ "m" karışması
     * ("Loma" = "Lorna", "Modemlik" = "Modernlik"). $b Text::key biçiminde.
     */
    public static function similarWord(string $word, string $b): bool
    {
        $a = Text::key($word);
        if ($a === $b) {
            return false;
        }
        $len = max(strlen($a), strlen($b));
        if ($len >= 4 && levenshtein($a, $b) <= ($len >= 8 ? 2 : 1)) {
            return true;
        }

        return $len >= 4 && str_replace('rn', 'm', $a) === str_replace('rn', 'm', $b);
    }

    /**
     * OCR ı/i ayrımını sık kaçırır ("Almanya'nin"); klasör adı büyük harfle yazıldığından (I / İ) doğru harfi bilir.
     * Harfler birebir eşleştiğinde OCR'deki ı/i klasördekine göre düzeltilir, büyük/küçük harf OCR'deki gibi kalır.
     */
    public static function fixDotless(string $ocr, string $folder): string
    {
        $o = mb_str_split($ocr);
        $f = array_values(array_filter(mb_str_split($folder), fn ($ch) => preg_match('/[\p{L}\p{N}]/u', $ch)));
        $k = 0;
        foreach ($o as $i => $ch) {
            if (! preg_match('/[\p{L}\p{N}]/u', $ch)) {
                continue;
            }
            $want = isset($f[$k]) ? Text::lower($f[$k]) : null;
            $k++;
            $have = Text::lower($ch);
            if (in_array($have, ['i', 'ı'], true) && in_array($want, ['i', 'ı'], true) && $have !== $want) {
                $upper = $ch !== $have;
                $o[$i] = $upper ? ($want === 'i' ? 'İ' : 'I') : $want;
            }
        }

        return implode('', $o);
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
        $s = preg_replace("/[’'`´‘]{2,}|[’`´‘]/u", "'", $s); // "Almanya’'nın" → "Almanya'nın"

        return trim(preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}.)!?]+$/u', '', $s));
    }

    private function number(?string $s): ?int
    {
        $s = strtr((string) $s, ['O' => '0', 'o' => '0', 'I' => '1', 'l' => '1', '|' => '1']);

        return preg_match('/\d{1,5}/', $s, $m) ? (int) $m[0] : null;
    }
}
