<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * İletişim sayfasındaki gömülü harita.
 *
 * Yönetici Ayarlar › İletişim'e Google Haritalar bağlantısını yapıştırır (paylaş bağlantısı, tam adres çubuğu bağlantısı
 * ya da "Haritayı yerleştir" kodu). Buradan her zaman www.google.com/maps üzerinde bir gömme adresi üretilir (CSP frame-src
 * yalnız bu ana makineye izin verir). Kısa paylaş bağlantıları (maps.app.goo.gl) kayıtta bir kez çözülür.
 */
class MapEmbed
{
    /** Ayarlardan gömme adresi; üretilemiyorsa null (harita bölümü gösterilmez). */
    public static function url(): ?string
    {
        return self::fromCode(Settings::get('maps_embed'))
            ?? self::fromLink(Settings::get('maps_resolved'))
            ?? self::fromLink(Settings::get('maps_url'))
            ?? self::fromQuery(Settings::get('address'));
    }

    /** "Haritayı yerleştir" iframe kodu ya da doğrudan gömme adresi: yalnız google.com/maps/embed kabul edilir. */
    public static function fromCode(?string $code): ?string
    {
        if (blank($code)) {
            return null;
        }
        $src = preg_match('/src\s*=\s*["\']([^"\']+)["\']/i', $code, $m) ? $m[1] : trim($code);
        $src = html_entity_decode($src, ENT_QUOTES);
        $parts = parse_url($src);

        return ($parts['scheme'] ?? '') === 'https'
            && in_array($parts['host'] ?? '', ['www.google.com', 'google.com'], true)
            && str_starts_with($parts['path'] ?? '', '/maps/embed')
                ? 'https://www.google.com'.$parts['path'].(isset($parts['query']) ? '?'.$parts['query'] : '')
                : null;
    }

    /** Tam Google Haritalar bağlantısından konum: @enlem,boylam > q/query parametresi > /place/Ad */
    public static function fromLink(?string $url): ?string
    {
        if (blank($url) || ! preg_match('#^https?://([a-z0-9-]+\.)*google\.[a-z.]+/maps#i', $url)) {
            return null;
        }
        if (preg_match('/@(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)/', $url, $m)) {
            return self::fromQuery($m[1].','.$m[2]);
        }
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        foreach (['q', 'query', 'destination'] as $key) {
            if (filled($query[$key] ?? null) && is_string($query[$key])) {
                return self::fromQuery($query[$key]);
            }
        }
        if (preg_match('#/maps/place/([^/@?]+)#', $url, $m)) {
            return self::fromQuery(str_replace('+', ' ', urldecode($m[1])));
        }

        return null;
    }

    public static function fromQuery(?string $q): ?string
    {
        $q = trim((string) $q);

        return $q === '' ? null : 'https://www.google.com/maps?q='.rawurlencode(mb_substr($q, 0, 300)).'&z=16&hl=tr&output=embed';
    }

    /** Kısa paylaş bağlantısını (maps.app.goo.gl, goo.gl/maps) tam bağlantıya çözer; olmazsa null. */
    public static function resolve(?string $url): ?string
    {
        if (blank($url) || ! preg_match('#^https://(maps\.app\.goo\.gl|goo\.gl/maps)/#i', $url)) {
            return null;
        }
        try {
            for ($hop = 0; $hop < 4; $hop++) {
                $location = Http::timeout(5)->withoutRedirecting()->get($url)->header('Location');
                if (! $location) {
                    return null;
                }
                $url = $location;
                if (self::fromLink($url)) {
                    return $url;
                }
            }
        } catch (Throwable) {
            // Ağ hatası: harita adres üzerinden gösterilir
        }

        return null;
    }
}
