<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use App\Support\Text;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Yayınevi. match_key, adın "yayınları/yayınevi/yayıncılık" ekleri atılmış normal hâlidir; böylece "HIZ YAYINLARI",
 * "Hız Yayınevi" ve "hiz" aynı yayınevine düşer. Ek eşleşmeler publisher_aliases tablosundadır.
 */
class Publisher extends Model
{
    use HasUniqueSlug;

    protected $fillable = ['name', 'slug', 'match_key', 'logo_path', 'description', 'is_active', 'is_featured', 'position', 'meta_title', 'meta_description'];

    private const SUFFIXES = ['yayinlari', 'yayinevi', 'yayincilik', 'yayinlar', 'yayin', 'yayinevleri', 'kitabevi', 'kitap evi', 'ekran goruntuleri', 'ekran goruntusu', 'basim', 'dagitim', 'ltd sti', 'as'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_featured' => 'boolean'];
    }

    public static function matchKey(string $name): string
    {
        $key = ' '.Text::key($name).' ';
        // Sondaki ekleri tekrar tekrar sök ("... YAYINLARI EKRAN GÖRÜNTÜLERİ").
        do {
            $before = $key;
            foreach (self::SUFFIXES as $suffix) {
                $key = preg_replace('/\s'.preg_quote($suffix, '/').'\s*$/', ' ', $key);
            }
        } while ($key !== $before);
        $key = trim(preg_replace('/\s+/', ' ', $key));

        return $key !== '' ? $key : Text::key($name);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(PublisherAlias::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    /** Ad baş harfleri (logo yoksa gösterilen rozet). */
    public function initials(): string
    {
        $words = array_values(array_filter(explode(' ', self::matchKey($this->name))));

        return Text::upper(mb_substr($words[0] ?? $this->name, 0, 1).mb_substr($words[1] ?? '', 0, 1));
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Ada veya takma ada göre yayınevi. */
    public static function findByName(string $name): ?self
    {
        $key = self::matchKey($name);

        return static::where('match_key', $key)->first()
            ?? PublisherAlias::where('match_key', $key)->first()?->publisher;
    }
}
