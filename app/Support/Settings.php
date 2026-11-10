<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

/**
 * Site ayarları (anahtar → metin). Sitede sabit metin yoktur: başlık, iletişim, renkler, logo, duyuru, SEO ve
 * e-posta ayarları buradan okunur ve yönetimden değiştirilir. Tanımsız anahtar yazılamaz.
 *
 * Kayıtlı değerler önbellekte tutulur; varsayılanlar her okumada eklenir (yeni anahtar eklenince eski önbellek bozulmaz).
 */
class Settings
{
    public const DEFAULTS = [
        // Genel
        'site_name' => 'Eskişehir Fotokopi',
        'site_tagline' => 'Kitap, soru bankası ve belge baskısı kapınıza gelsin',
        'announcement' => null,          // üst duyuru şeridi (boşsa gösterilmez)
        'announcement_link' => null,
        'store_open' => '1',             // kapalıyken vitrinde bakım sayfası gösterilir (yöneticiler görür)
        'closed_message' => 'Sitemiz kısa bir süre bakımdadır. Lütfen daha sonra tekrar deneyin.',

        // İletişim
        'phone' => null,
        'whatsapp' => null,              // yalnız rakam, ülke koduyla (905xxxxxxxxx)
        'email' => null,
        'address' => null,
        'working_hours' => null,         // satır satır
        'maps_url' => null,

        // Sosyal medya
        'instagram' => null,
        'facebook' => null,
        'x' => null,
        'youtube' => null,
        'tiktok' => null,

        // Görünüm
        'logo_path' => null,             // public diskte
        'favicon_path' => null,
        'color_primary' => '#0f766e',
        'color_accent' => '#f59e0b',

        // Menü ve şeritler (satır başına bir öğe)
        'header_links' => 'Yayınevleri|/yayinevleri
Belge Yükle|/fotokopi
Sipariş Takibi|/siparis-takip', // Etiket|Adres
        'trust_items' => 'truck|Kapınıza teslim|Eskişehir içi belirlenen bölgelere
cash|Kapıda ödeme|Nakit veya kredi kartı ile
printer|Belge baskısı|Belgeni yükle, biz basalım', // simge|Başlık|Açıklama

        // Teslimat ve ödeme (K6: hepsi panelden; ilçe ücretleri Yönetim › Teslimat bölgeleri)
        'delivery_free_threshold' => '500',   // bu tutar ve üstü ücretsiz teslimat (boşsa yok)
        'delivery_min_order' => '100',        // en düşük sipariş tutarı (boşsa yok)
        'delivery_cutoff' => '15:00',         // bu saate kadar verilen sipariş aynı gün çıkar
        'delivery_same_day' => '1',
        'delivery_closed_days' => '7',        // teslimat yapılmayan günler (1=Pazartesi … 7=Pazar, virgülle)
        'delivery_slots' => "10:00 - 13:00\n13:00 - 17:00\n17:00 - 20:00", // boşsa saat seçimi sorulmaz
        'delivery_info' => 'Siparişleriniz Eskişehir içinde belirlenen bölgelere kapıya teslim edilir. Ödemeyi teslimatta nakit veya kartla yaparsınız.',
        'payment_cash' => '1',
        'payment_card' => '1',
        'orders_open' => '1',                 // kapalıyken sepet çalışır, sipariş alınmaz
        'orders_closed_message' => 'Şu anda sipariş alamıyoruz. Lütfen daha sonra tekrar deneyin.',

        // Alt bilgi
        'footer_about' => 'Eskişehir içinde kitap, soru bankası ve belge baskısında hızlı ve güvenilir hizmet. Siparişiniz kapınıza gelir, ödemenizi kapıda nakit veya kartla yaparsınız.',
        'copyright' => null,             // boşsa "© {yıl} {site adı}"

        // SEO
        'meta_title' => null,
        'meta_description' => null,

        // E-posta (SMTP). Parola şifreli saklanır.
        'mail_host' => null,
        'mail_port' => '587',
        'mail_encryption' => 'tls',
        'mail_username' => null,
        'mail_password' => null,
        'mail_from_address' => null,
        'mail_from_name' => null,
        'notify_emails' => null,         // yönetici bildirim adresleri (virgülle)
    ];

    public const SECRETS = ['mail_password'];

    private const CACHE_KEY = 'settings.saved';

    /** @return array<string, ?string> */
    public static function all(): array
    {
        $saved = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->all());

        return array_merge(self::DEFAULTS, array_intersect_key($saved, self::DEFAULTS));
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = self::all()[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    public static function flag(string $key): bool
    {
        return self::get($key) === '1';
    }

    /** @param  array<string, ?string>  $values */
    public static function put(array $values): void
    {
        foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
            $value = $value === null ? null : trim((string) $value);
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value === '' ? null : $value]);
        }
        Cache::forget(self::CACHE_KEY);
    }

    public static function secret(string $key): ?string
    {
        $value = self::get($key);
        if ($value === null) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }

    public static function putSecret(string $key, ?string $plain): void
    {
        self::put([$key => blank($plain) ? null : Crypt::encryptString($plain)]);
    }

    /** public diskteki bir ayar dosyasının adresi (logo, favicon). */
    public static function fileUrl(string $key): ?string
    {
        $path = self::get($key);

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public static function siteName(): string
    {
        return (string) self::get('site_name', 'Eskişehir Fotokopi');
    }

    public static function copyright(): string
    {
        return self::get('copyright') ?? '© '.now()->year.' '.self::siteName().'. Tüm hakları saklıdır.';
    }

    /** WhatsApp sohbet bağlantısı (numara tanımlı değilse null). */
    public static function whatsappUrl(?string $text = null): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) self::get('whatsapp'));

        return $digits ? 'https://wa.me/'.$digits.($text ? '?text='.rawurlencode($text) : '') : null;
    }

    /**
     * "Etiket|Adres" satırlarından bağlantılar (ör. header_links).
     *
     * @return list<array{label: string, url: string}>
     */
    public static function links(string $key): array
    {
        $out = [];
        foreach (preg_split('/\R/u', (string) self::get($key)) as $line) {
            [$label, $url] = array_map('trim', explode('|', $line, 2)) + [1 => ''];
            if ($label !== '' && $url !== '' && preg_match('#^(/|https?://|tel:|mailto:)#i', $url)) {
                $out[] = ['label' => $label, 'url' => $url];
            }
        }

        return $out;
    }

    /**
     * Güven şeridi öğeleri: "simge|Başlık|Açıklama" satırları.
     *
     * @return list<array{icon: string, title: string, text: string}>
     */
    public static function trustItems(): array
    {
        $out = [];
        foreach (preg_split('/\R/u', (string) self::get('trust_items')) as $line) {
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) >= 2 && $parts[1] !== '') {
                $out[] = ['icon' => $parts[0] ?: 'check', 'title' => $parts[1], 'text' => $parts[2] ?? ''];
            }
        }

        return $out;
    }

    /** @return list<array{key: string, label: string, url: string}> */
    public static function socialLinks(): array
    {
        $labels = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'x' => 'X', 'youtube' => 'YouTube', 'tiktok' => 'TikTok'];
        $links = [];
        foreach ($labels as $key => $label) {
            if ($url = self::get($key)) {
                $links[] = ['key' => $key, 'label' => $label, 'url' => $url];
            }
        }

        return $links;
    }

    /** Vitrin renkleri için CSS değişkenleri (layout'ta :root'a yazılır). Yalnız geçerli #rrggbb kabul edilir. */
    public static function themeCss(): string
    {
        $color = fn (string $key) => preg_match('/^#[0-9a-f]{6}$/i', (string) self::get($key)) ? self::get($key) : self::DEFAULTS[$key];

        return ':root{--color-primary:'.$color('color_primary').';--color-accent:'.$color('color_accent').';}';
    }
}
