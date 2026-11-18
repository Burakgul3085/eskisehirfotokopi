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
        static::saved(fn () => Cache::forget('search.pages2')); // site araması sayfa dizini
        static::deleted(fn () => Cache::forget('search.pages2'));
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
        return new HtmlString(self::markdown((string) $this->body));
    }

    private static function markdown(string $text): string
    {
        $text = strtr($text, array_map(fn ($v) => $v ?: '—', self::placeholders()));

        return Str::markdown($text, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }

    /**
     * Vitrindeki görünüm: Hakkımızda tanıtım sayfası, SSS akordeonu, yardım sayfası (alt bilgide Yardım sütunu) veya
     * içindekiler listeli yasal metin (diğerleri).
     */
    public function layout(): string
    {
        return match (true) {
            $this->key === 'about' => 'about',
            $this->key === 'faq' => 'faq',
            $this->footer_column === 'help' => 'help',
            default => 'legal',
        };
    }

    public function icon(): string
    {
        return match ($this->key) {
            'about' => 'home', 'faq' => 'help', 'delivery' => 'truck', 'kvkk' => 'shield', 'membership' => 'users',
            'cookies' => 'cookie', 'sales' => 'file', default => $this->footer_column === 'help' ? 'help' : 'file',
        };
    }

    /**
     * Yasal metin: ## başlıklarına bağlantı kimliği eklenmiş HTML ve içindekiler listesi.
     *
     * @return array{html: HtmlString, toc: list<array{id: string, title: string}>}
     */
    public function document(): array
    {
        $toc = [];
        $html = preg_replace_callback('#<h2>(.*?)</h2>#s', function ($m) use (&$toc) {
            $id = 'bolum-'.(count($toc) + 1);
            $toc[] = ['id' => $id, 'title' => html_entity_decode(strip_tags($m[1]), ENT_QUOTES)];

            return '<h2 id="'.$id.'">'.$m[1].'</h2>';
        }, self::markdown((string) $this->body));

        return ['html' => new HtmlString($html), 'toc' => $toc];
    }

    /**
     * SSS sayfası içeriği: "# Grup" satırları grupları, "## Soru" satırları soruları başlatır; altındaki metin cevaptır.
     * Grup başlığı hiç yoksa tek, adsız grup döner.
     *
     * @return list<array{id: string, title: ?string, items: list<array{q: string, a: HtmlString, plain: string}>}>
     */
    public function faqGroups(): array
    {
        $groups = [];
        $current = null;
        $question = null;
        $answer = [];
        $flush = function () use (&$groups, &$current, &$question, &$answer) {
            if ($question !== null) {
                if ($current === null) {
                    $groups[] = ['title' => null, 'items' => []];
                    $current = array_key_last($groups);
                }
                $text = trim(implode("\n", $answer));
                $groups[$current]['items'][] = ['q' => $question, 'a' => new HtmlString(self::markdown($text)), 'plain' => trim(strip_tags(self::markdown($text)))];
            }
            $question = null;
            $answer = [];
        };
        foreach (preg_split('/\R/u', (string) $this->body) as $line) {
            if (preg_match('/^#\s+(.+)$/u', $line, $m)) {
                $flush();
                $groups[] = ['title' => trim($m[1]), 'items' => []];
                $current = array_key_last($groups);
            } elseif (preg_match('/^##\s+(.+)$/u', $line, $m)) {
                $flush();
                $question = strtr(trim($m[1]), array_map(fn ($v) => $v ?: '—', self::placeholders()));
            } elseif ($question !== null) {
                $answer[] = $line;
            }
        }
        $flush();

        $groups = array_values(array_filter($groups, fn ($g) => $g['items'] !== []));
        foreach ($groups as $i => &$g) {
            $g['id'] = $g['title'] ? Str::slug($g['title']) ?: 'grup-'.($i + 1) : 'sorular';
        }

        return $groups;
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
