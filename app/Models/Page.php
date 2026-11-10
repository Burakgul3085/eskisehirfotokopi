<?php

namespace App\Models;

use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Kurumsal / yasal sayfa. İçerik Markdown (HTML etiketleri temizlenir). Sistem sayfaları `key` ile bulunur ve
 * formlardan bağlanır (sales, kvkk, membership, cookies ...).
 */
class Page extends Model
{
    public const COLUMNS = ['corporate' => 'Kurumsal', 'help' => 'Yardım'];

    private const CACHE_KEY = 'pages.links';

    protected $fillable = ['key', 'title', 'slug', 'body', 'meta_description', 'footer_column', 'position', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::memo()->forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::memo()->forget(self::CACHE_KEY));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function isSystem(): bool
    {
        return $this->key !== null;
    }

    public function url(): string
    {
        return route('page.show', $this->slug);
    }

    /** Markdown → güvenli HTML; {site_adi} gibi yer tutucular ayarlardan doldurulur. */
    public function html(): HtmlString
    {
        $body = strtr((string) $this->body, array_map(fn ($v) => $v ?: '—', self::placeholders()));

        return new HtmlString(Str::markdown($body, ['html_input' => 'escape', 'allow_unsafe_links' => false]));
    }

    /** Yer tutucu → ayardaki değer (boşsa null). @return array<string, ?string> */
    public static function placeholders(): array
    {
        $s = Settings::all();

        return ['{site_adi}' => Settings::siteName(), '{telefon}' => $s['phone'] ?: null, '{eposta}' => $s['email'] ?: null, '{adres}' => $s['address'] ?: null];
    }

    /** İçerikte kullanılan ama ayarlarda boş olan yer tutucular (yönetimde uyarı için). @return list<string> */
    public function missingPlaceholders(): array
    {
        return array_keys(array_filter(self::placeholders(), fn ($v, $k) => $v === null && str_contains((string) $this->body, $k), ARRAY_FILTER_USE_BOTH));
    }

    /**
     * Alt bilgi ve formlar için hafif bağlantı listesi (önbellekte dizi olarak).
     *
     * @return array{columns: array<string, list<array{title: string, url: string}>>, keys: array<string, array{title: string, url: string}>}
     */
    public static function links(): array
    {
        $rows = Cache::memo()->rememberForever(self::CACHE_KEY, fn () => self::active()->orderBy('position')->orderBy('title')
            ->get(['key', 'title', 'slug', 'footer_column'])->map(fn ($p) => $p->only('key', 'title', 'slug', 'footer_column'))->all());

        $out = ['columns' => [], 'keys' => []];
        foreach ($rows as $row) {
            $link = ['title' => $row['title'], 'url' => route('page.show', $row['slug'])];
            if ($row['footer_column']) {
                $out['columns'][$row['footer_column']][] = $link;
            }
            if ($row['key']) {
                $out['keys'][$row['key']] = $link;
            }
        }

        return $out;
    }

    /** Sistem sayfasının bağlantısı (kapalıysa null). */
    public static function linkFor(string $key): ?array
    {
        return self::links()['keys'][$key] ?? null;
    }
}
