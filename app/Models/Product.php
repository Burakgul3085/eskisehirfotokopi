<?php

namespace App\Models;

use App\Domain\Catalog\BookPricing;
use App\Domain\Print\PrintOptions;
use App\Domain\Print\PrintSettings;
use App\Models\Concerns\HasUniqueSlug;
use App\Support\EmptyValue;
use App\Support\Settings;
use App\Support\Text;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Katalog ürünü (kitap / soru bankası). Baskı tipi = renk × düzen: siyah-beyaz / renkli, önlü arkalı 1'e 1 / 2'ye 2.
 * Fiyat elle girilmez: sayfa sayısından fotokopi kademeleriyle hesaplanır (BookPricing). Sayfa sayısı olmayan eski
 * kayıtların elle girilmiş fiyatı (yalnız 1'e 1) korunur.
 * Vitrinde görünür = durum "active"; satın alınabilir = en az bir fiyatı var (K2).
 */
class Product extends Model
{
    use HasUniqueSlug;

    public const STATUSES = ['draft' => 'Taslak', 'active' => 'Yayında', 'passive' => 'Pasif'];

    /** Sepet ve siparişte saklanan baskı tipi => ad. Sonu "2" olanlar önlü arkalı 2'ye 2'dir. */
    public const PRINT_TYPES = ['bw' => "Siyah-beyaz · 1'e 1", 'bw2' => "Siyah-beyaz · 2'ye 2", 'color' => "Renkli · 1'e 1", 'color2' => "Renkli · 2'ye 2"];

    /** Kitap cildi: ciltsiz kitaplar zımbalanıp ücretsiz gönderilir; spiral fotokopi ayarlarındaki sabit fiyatla eklenir. */
    public const BINDINGS = ['none' => 'Zımbalı', 'spiral' => 'Spiral cilt'];

    /** Baskı tipinin fiyat sütunu */
    public const PRICE_COLUMNS = ['bw' => 'price_bw', 'bw2' => 'price_bw_2up', 'color' => 'price_color', 'color2' => 'price_color_2up'];

    /** Görsel boyutları (genişlik, px). */
    public const IMAGE_SIZES = ['sm' => 320, 'md' => 640, 'lg' => 1400];

    protected $fillable = ['code', 'isbn', 'title', 'subtitle', 'slug', 'publisher_id', 'author_id', 'category_id', 'page_count', 'print_year',
        'description', 'price_bw', 'price_color', 'price_bw_2up', 'price_color_2up', 'compare_bw', 'compare_color', 'status', 'is_featured', 'image_key', 'image_width',
        'image_height', 'sales_count', 'view_count', 'meta_title', 'meta_description', 'published_at', 'spec_visibility', 'extra_specs', 'book_type', 'subject', 'topics'];

    /**
     * Müşteriye gösterilebilen ürün bilgileri (anahtar => ad). Her biri genel ayardan (Ayarlar › Ürün bilgileri)
     * açılıp kapatılır; ürün kendi seçimini (spec_visibility) taşıyorsa o geçerlidir. "extras" tüm ek bilgilerin
     * varsayılanıdır; tek bir ek bilgi "ek:<etiket anahtarı>" ile ayrıca açılıp kapatılabilir.
     */
    public const SPECS = [
        'author' => 'Yazar', 'publisher' => 'Yayınevi', 'category' => 'Kategori', 'type' => 'Kitap türü', 'subject' => 'Ders', 'topics' => 'Konular', 'pages' => 'Sayfa sayısı', 'year' => 'Baskı yılı',
        'isbn' => 'ISBN', 'code' => 'Ürün kodu', 'print' => 'Baskı', 'extras' => 'Ek bilgiler',
    ];

    protected function casts(): array
    {
        return [
            'price_bw' => 'decimal:2', 'price_color' => 'decimal:2', 'price_bw_2up' => 'decimal:2', 'price_color_2up' => 'decimal:2', 'compare_bw' => 'decimal:2', 'compare_color' => 'decimal:2',
            'is_featured' => 'boolean', 'rating_avg' => 'float', 'published_at' => 'datetime', 'spec_visibility' => 'array', 'extra_specs' => 'array', 'topics' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $p) {
            if ($p->status === 'active' && ! $p->published_at) {
                $p->published_at = now();
            }
            // Sayfa sayısı değişince (ya da sayfalı üründe fiyat eksikse) dört fiyat yeniden hesaplanır.
            if ($p->isDirty('page_count') || ($p->page_count && $p->price_bw === null)) {
                $p->fill(app(BookPricing::class)->prices($p->page_count ? (int) $p->page_count : null));
            }
            if ($p->isDirty(['title', 'subtitle', 'code', 'isbn', 'author_id', 'publisher_id', 'category_id', 'extra_specs', 'book_type', 'subject', 'topics']) || $p->search_text === null) {
                $p->search_text = $p->buildSearchText();
            }
        });
        // Yazarlar yalnız ürünlerle yaşar (yönetimde ayrı sayfası yok): ürünü kalmayan yazar kaydı silinir.
        static::updated(function (Product $p) {
            if ($p->wasChanged('author_id') && ($old = $p->getOriginal('author_id'))) {
                Author::pruneIfOrphan($old);
            }
        });
        static::deleted(fn (Product $p) => $p->author_id && Author::pruneIfOrphan($p->author_id));
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    /** Ürün değerlendirmeleri (yayında olanlar için published() kapsamı). */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ProductQuestion::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function buildSearchText(): string
    {
        // ISBN ve ürün kodu müşteriye gizlense de aramada bulunur (numarayla arayan doğru kitaba ulaşır).
        $parts = [$this->title, $this->subtitle, $this->code, $this->isbn, ...array_column($this->extras(), 'value'),
            $this->book_type, $this->subject, ...($this->topics ?? []),
            $this->author_id ? Author::find($this->author_id)?->name : null,
            $this->publisher_id ? Publisher::find($this->publisher_id)?->name : null,
            $this->category_id ? Category::find($this->category_id)?->fullName(' ') : null];

        return ' '.Text::key(implode(' ', array_filter($parts))).' ';
    }

    // ---- Ürün bilgileri (göster/gizle, ek bilgiler) ----

    public static function extraKey(string $label): string
    {
        return 'ek:'.Text::key($label);
    }

    /**
     * Ek bilgiler: boş olmayan ve "bilgi yok" anlamı taşımayan satırlar.
     *
     * @return list<array{label: string, value: string}>
     */
    public function extras(): array
    {
        $out = [];
        foreach ($this->extra_specs ?? [] as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            if ($label !== '' && ! EmptyValue::is($value)) {
                $out[] = ['label' => $label, 'value' => $value];
            }
        }

        return $out;
    }

    /**
     * Toplu göster/gizle seçenekleri: standart bilgiler + ürünlerde geçen ek bilgi adları (anahtar => ad).
     *
     * @return array<string, string>
     */
    public static function specOptions(): array
    {
        $options = self::SPECS;
        foreach (static::whereNotNull('extra_specs')->pluck('extra_specs') as $rows) {
            foreach ($rows ?? [] as $row) {
                if (($label = trim((string) ($row['label'] ?? ''))) !== '') {
                    $options[self::extraKey($label)] ??= 'Ek bilgi: '.$label;
                }
            }
        }

        return $options;
    }

    /** Ürünün kendi seçimi: true/false; seçim yoksa null (genel ayar geçerli). */
    public function specChoice(string $key): ?bool
    {
        $choices = $this->spec_visibility ?? [];

        return array_key_exists($key, $choices) ? (bool) $choices[$key] : null;
    }

    public function specVisible(string $key): bool
    {
        if (($own = $this->specChoice($key)) !== null) {
            return $own;
        }
        if (str_starts_with($key, 'ek:')) {
            return $this->specVisible('extras');
        }

        return ! in_array($key, Settings::hiddenSpecs(), true);
    }

    /**
     * Ürün sayfasındaki bilgi tablosu: ad => değer. Gizlenen ve "bilgi yok" anlamındaki değerler çıkarılır.
     *
     * @param  iterable<Category>  $trail  kategori yolu
     * @param  list<string>  $types  satılan baskı tipleri
     */
    public function storefrontSpecs(iterable $trail = [], array $types = []): array
    {
        $trail = collect($trail);
        $values = [
            'author' => $this->author?->name,
            'publisher' => $this->publisher?->name,
            'category' => $trail->isNotEmpty() ? $trail->pluck('name')->implode(' › ') : null,
            'type' => $this->book_type,
            'subject' => $this->subject,
            'topics' => $this->topics ? implode(', ', $this->topics) : null,
            'pages' => $this->page_count,
            'year' => $this->print_year,
            'isbn' => $this->isbn,
            'code' => $this->code,
            'print' => $types ? implode(' / ', array_unique(array_map(fn ($t) => PrintOptions::COLORS[self::colorOf($t)], $types))).' · önlü arkalı' : null,
        ];
        $specs = [];
        foreach ($values as $key => $value) {
            if ($value !== null && $value !== '' && ! EmptyValue::is((string) $value) && $this->specVisible($key)) {
                $specs[self::SPECS[$key]] = $value;
            }
            if ($key === 'code') {
                foreach ($this->extras() as $row) {
                    if ($this->specVisible(self::extraKey($row['label'])) && ! isset($specs[$row['label']])) {
                        $specs[$row['label']] = $row['value'];
                    }
                }
            }
        }

        return $specs;
    }

    /**
     * Bilgi gösterimini toplu değiştirir: true (göster), false (gizle), null (ürün seçimini kaldır, genel ayar geçerli).
     *
     * @param  iterable<int>  $ids
     */
    public static function setSpecVisibility(iterable $ids, string $key, ?bool $visible): int
    {
        $n = 0;
        static::whereIn('id', collect($ids)->all())->select('id', 'spec_visibility')->chunkById(200, function ($products) use ($key, $visible, &$n) {
            foreach ($products as $p) {
                $choices = $p->spec_visibility ?? [];
                if ($visible === null) {
                    unset($choices[$key]);
                } else {
                    $choices[$key] = $visible;
                }
                // saving olaylarını (fiyat, arama metni) tetiklemeden yalnız bu sütun yazılır
                static::whereKey($p->id)->update(['spec_visibility' => $choices ? json_encode($choices) : null]);
                $n++;
            }
        });

        return $n;
    }

    // ---- Fiyat ve satış ----

    public function price(string $type): ?string
    {
        return isset(self::PRICE_COLUMNS[$type]) ? $this->{self::PRICE_COLUMNS[$type]} : null;
    }

    /** Üstü çizili eski fiyat yalnız 1'e 1 fiyatına uygulanır. */
    public function comparePrice(string $type): ?string
    {
        $compare = ['bw' => $this->compare_bw, 'color' => $this->compare_color][$type] ?? null;
        $price = $this->price($type);

        return $compare !== null && $price !== null && bccomp($compare, $price, 2) > 0 ? $compare : null;
    }

    /** Gerçek indirim yüzdesi (yuvarlanmış), yoksa null. */
    public function discountPercent(string $type): ?int
    {
        $compare = $this->comparePrice($type);
        if ($compare === null) {
            return null;
        }
        // Kayan nokta 240/300 için %19,999… verip %19 yazıyordu: bcmath ile kesin hesap.
        $pct = (int) bcdiv(bcmul(bcsub($compare, $this->price($type), 2), '100', 2), $compare, 0);

        return $pct >= 1 ? $pct : null;
    }

    /**
     * Satılabilen baskı tipleri: fiyatı olan ve rengi fotokopi ayarlarında açık olanlar (renkli yazıcı arızasında
     * kitapların renkli seçeneği de gizlenir).
     *
     * @return list<string>
     */
    public function printTypes(): array
    {
        $colors = app(PrintSettings::class)->colors();

        return array_values(array_filter(array_keys(self::PRINT_TYPES),
            fn ($t) => $this->price($t) !== null && in_array(rtrim($t, '2'), $colors, true)));
    }

    /** Rengin adı (ör. "bw2" → "Siyah-beyaz") */
    public static function colorOf(string $type): string
    {
        return rtrim($type, '2');
    }

    /** Varsayılan (kartta gösterilen) baskı tipi: siyah-beyaz fiyatı varsa o, yoksa renkli. */
    public function defaultPrintType(): ?string
    {
        return $this->printTypes()[0] ?? null;
    }

    public function isPurchasable(): bool
    {
        return $this->status === 'active' && $this->printTypes() !== [];
    }

    public function scopeVisible(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    public function scopePriced(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->whereNotNull('price_bw')->orWhereNotNull('price_color'));
    }

    public function scopeMissingPrice(Builder $q): Builder
    {
        return $q->whereNull('price_bw')->whereNull('price_color');
    }

    // ---- Görsel ----

    /**
     * Göreli adres ("/storage/products/…"): site hangi adresten açılırsa açılsın (localhost, 127.0.0.1, alan adı) görsel
     * aynı kaynaktan gelir; aksi hâlde CSP (img-src 'self') farklı ana makinedeki görseli engeller.
     * Paylaşım etiketleri ve yapılandırılmış veri için imageAbsoluteUrl() kullanılır.
     */
    public function imageUrl(string $size = 'md'): ?string
    {
        if (! $this->image_key) {
            return null;
        }

        return '/'.ltrim((string) parse_url(Storage::disk('public')->url("products/{$this->image_key}/{$size}.webp"), PHP_URL_PATH), '/');
    }

    public function imageAbsoluteUrl(string $size = 'lg'): ?string
    {
        return $this->image_key ? url($this->imageUrl($size)) : null;
    }

    /** Boyut sınıfları için srcset. */
    public function imageSrcset(): ?string
    {
        if (! $this->image_key) {
            return null;
        }

        return collect(self::IMAGE_SIZES)->map(fn ($w, $size) => $this->imageUrl($size)." {$w}w")->implode(', ');
    }

    /** Görselin en/boy oranına göre verilen genişlikteki yükseklik (yerleşim kaymasını önler). */
    public function imageHeightFor(int $width): int
    {
        return $this->image_width && $this->image_height ? (int) round($width * $this->image_height / $this->image_width) : (int) round($width * 1.4);
    }
}
