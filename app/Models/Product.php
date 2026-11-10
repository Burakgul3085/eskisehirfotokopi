<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use App\Support\Text;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Katalog ürünü (kitap / soru bankası). İki baskı tipi ve iki fiyat: siyah-beyaz (bw) ve renkli (color) (K3).
 * Vitrinde görünür = durum "active"; satın alınabilir = en az bir fiyatı var (K2).
 */
class Product extends Model
{
    use HasUniqueSlug;

    public const STATUSES = ['draft' => 'Taslak', 'active' => 'Yayında', 'passive' => 'Pasif'];

    public const PRINT_TYPES = ['bw' => 'Siyah-beyaz', 'color' => 'Renkli'];

    /** Görsel boyutları (genişlik, px). */
    public const IMAGE_SIZES = ['sm' => 320, 'md' => 640, 'lg' => 1400];

    protected $fillable = ['code', 'isbn', 'title', 'slug', 'publisher_id', 'author_id', 'category_id', 'page_count', 'print_year',
        'description', 'price_bw', 'price_color', 'compare_bw', 'compare_color', 'status', 'is_featured', 'image_key', 'image_width',
        'image_height', 'sales_count', 'view_count', 'meta_title', 'meta_description', 'published_at'];

    protected function casts(): array
    {
        return [
            'price_bw' => 'decimal:2', 'price_color' => 'decimal:2', 'compare_bw' => 'decimal:2', 'compare_color' => 'decimal:2',
            'is_featured' => 'boolean', 'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $p) {
            if ($p->status === 'active' && ! $p->published_at) {
                $p->published_at = now();
            }
            if ($p->isDirty(['title', 'code', 'isbn', 'author_id', 'publisher_id', 'category_id']) || $p->search_text === null) {
                $p->search_text = $p->buildSearchText();
            }
        });
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function buildSearchText(): string
    {
        $parts = [$this->title, $this->code, $this->isbn,
            $this->author_id ? Author::find($this->author_id)?->name : null,
            $this->publisher_id ? Publisher::find($this->publisher_id)?->name : null,
            $this->category_id ? Category::find($this->category_id)?->fullName(' ') : null];

        return ' '.Text::key(implode(' ', array_filter($parts))).' ';
    }

    // ---- Fiyat ve satış ----

    public function price(string $type): ?string
    {
        return $type === 'color' ? $this->price_color : $this->price_bw;
    }

    public function comparePrice(string $type): ?string
    {
        $compare = $type === 'color' ? $this->compare_color : $this->compare_bw;
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
        $pct = (int) floor((1 - (float) $this->price($type) / (float) $compare) * 100);

        return $pct >= 1 ? $pct : null;
    }

    /** @return list<string> satılabilen baskı tipleri */
    public function printTypes(): array
    {
        return array_values(array_filter(['bw', 'color'], fn ($t) => $this->price($t) !== null));
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

    public function imageUrl(string $size = 'md'): ?string
    {
        return $this->image_key ? Storage::disk('public')->url("products/{$this->image_key}/{$size}.webp") : null;
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
