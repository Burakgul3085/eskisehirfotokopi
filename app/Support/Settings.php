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
        'hidden_specs' => '',            // müşteriye varsayılan olarak gösterilmeyen ürün bilgileri (Product::SPECS anahtarları, virgülle)
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
        'maps_embed' => null,            // isteğe bağlı: Google Haritalar "Haritayı yerleştir" kodu
        'maps_resolved' => null,         // kısa paylaş bağlantısının kayıtta çözülmüş hâli (MapEmbed::resolve)
        'contact_kicker' => 'Bize ulaşın',
        'contact_title' => 'Sorunuz mu var?',
        'contact_title_accent' => 'Hemen yazın.',
        'contact_form_title' => 'Mesaj gönderin',
        'contact_info_title' => 'İletişim bilgileri',
        'contact_map_title' => 'Bizi ziyaret edin',
        'contact_intro' => 'Sipariş, belge baskısı veya ürünlerle ilgili sorularınızı yazın; en kısa sürede e-postayla yanıt verelim.',
        'contact_consent' => 'Bilgilerimin mesajıma yanıt verilmesi amacıyla işlenmesini kabul ediyorum.',

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
        'header_links' => 'Tüm kitaplar|/urunler
Yayınevleri|/yayinevleri
Belge Yükle|/fotokopi
Sipariş Takibi|/siparis-takip
İletişim|/iletisim', // Etiket|Adres
        // Header'ın üstünde kayan bant: "simge|Metin|ROZET" (boşsa bant gösterilmez; duyuru da başa eklenir)
        'topbar_items' => 'truck|Eskişehir içi|KAPIYA TESLİM
cash|Ödeme kapıda|NAKİT VEYA KART
printer|Belgeni yükle|BİZ BASALIM
book|Binlerce kitap|TEK ADRESTE
whatsapp|WhatsApp ile|HIZLI DESTEK',
        'header_welcome' => 'Hoş geldiniz',
        'header_support_label' => 'Hızlı destek',

        // Kategori menüsü (header'daki "Tüm kategoriler" paneli; içinde yayında ürün olmayan kategori gösterilmez)
        'menu_all_label' => 'Tüm kategoriler',
        'menu_search_placeholder' => 'Kategori ara…',
        'search_placeholder' => 'Kitap, yayınevi, yazar, kategori veya sayfa ara…', // header ve ana sayfa arama kutusu
        'menu_see_all' => 'Tümünü gör',
        'menu_no_match' => 'Eşleşen kategori yok.',
        'category_empty' => 'Bu kategoride henüz ürün yok.',

        // Yayınevleri sayfası (/yayinevleri)
        'publishers_kicker' => 'Yayınevleri',
        'publishers_title' => 'Sevdiğin yayınevi,',
        'publishers_title_accent' => 'tek tıkla rafında.',
        'publishers_intro' => 'Bir yayınevine tıkla, kitaplarının, soru bankalarının ve denemelerinin hepsini gör.',
        'publishers_featured' => 'Öne çıkan yayınevleri',
        'publishers_all' => 'Tüm yayınevleri (A–Z)',
        'publishers_all_sub' => 'Mağazamızdaki yayınevlerinin tam listesi.',
        'publishers_search' => 'Yayınevi ara…',
        'publishers_no_match' => 'Aramana uyan yayınevi yok.',

        // Sipariş takibi sayfası (/siparis-takip)
        'track_kicker' => 'Sipariş takibi',
        'track_title' => 'Siparişin nerede,',
        'track_title_accent' => 'anında gör.',
        'track_intro' => 'Sipariş numaranı ve siparişte yazdığın telefonu gir; hazırlanıyor mu, yolda mı hemen öğren.',
        'track_form_title' => 'Siparişini sorgula',
        'track_button' => 'Siparişimi bul',
        'track_points' => 'shield|Güvenli sorgu|Yalnız numara ve telefon eşleşirse görünür.
mail|E-postadaki bağlantı|Sipariş e-postandaki bağlantıyla da açılır.
clock|Anlık durum|Her adım değiştiğinde burada görünür.', // simge|Başlık|Açıklama
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
        'print' => null,                      // fotokopi ayarları (JSON, App\Domain\Print\PrintSettings)

        // Alt bilgi
        'footer_about' => 'Eskişehir içinde kitap, soru bankası ve belge baskısında hızlı ve güvenilir hizmet. Siparişiniz kapınıza gelir, ödemenizi kapıda nakit veya kartla yaparsınız.',
        'copyright' => null,             // boşsa "© {yıl} {site adı}"
        'footer_title_corporate' => 'Kurumsal', // sayfa sütunları (Yönetim › Sayfalar)
        'footer_title_help' => 'Yardım',
        // Alt bilgi: sütun başlıkları, harita ve çalışma saatleri kartları
        'footer_title_account' => 'Üyelik',
        'footer_title_shop' => 'Alışveriş',
        'footer_map_title' => 'Mağazamız',
        'footer_directions' => 'Yol tarifi',
        'footer_hours_title' => 'Çalışma saatleri',
        'footer_contact_link' => 'İletişim sayfası',
        // E-bülten (alt bilgideki form, abonelikten çıkış sayfası, e-posta alt bilgisi)
        'newsletter_enabled' => '1',
        'newsletter_kicker' => 'E-bülten',
        'newsletter_title' => 'Kampanyalardan ilk siz haberdar olun',
        'newsletter_text' => 'Yeni ürünler, indirimler ve size özel fırsatlar e-postanıza gelsin.',
        'newsletter_placeholder' => 'E-posta adresiniz',
        'newsletter_button' => 'Abone ol',
        'newsletter_consent' => 'Kampanya ve duyurular için ticari elektronik ileti almayı kabul ediyorum.',
        'newsletter_thanks' => 'Teşekkürler! E-bülten listemize eklendiniz.',
        'newsletter_mail_footer' => 'Bu e-postayı e-bültenimize abone olduğunuz için aldınız.',
        'newsletter_unsub_link' => 'Abonelikten çık',
        'newsletter_unsub_title' => 'E-bülten aboneliği',
        'newsletter_unsub_text' => ':email adresine kampanya ve duyuru e-postası gönderilmesini durdurmak istiyor musunuz?',
        'newsletter_unsub_button' => 'Aboneliğimi sonlandır',
        'newsletter_unsub_done' => 'Aboneliğiniz sonlandırıldı. Artık kampanya e-postası almayacaksınız.',
        // Bilgi sayfaları (yasal metinler, yardım, SSS) ortak metinleri — Ayarlar › Kurumsal sayfalar
        'pages_show_contact' => '0',          // bilgi sayfalarında telefon/e-posta/adres (kapalıyken yalnız İletişim bağlantısı)
        'pages_updated' => 'Son güncelleme',
        'pages_effective' => 'Yürürlük tarihi',
        'pages_copy_link' => 'Bağlantıyı kopyala',
        'pages_copied' => 'Bağlantı kopyalandı',
        'pages_print_note' => 'Bu belge, :site internet sitesinde yayımlanan güncel metnin çıktısıdır. Çıktı tarihi: :date. Güncel metin için: :url',
        'pages_contact_placeholder' => 'İletişim sayfamızda yer alan kanallar',
        'pages_toc_title' => 'İçindekiler',
        'pages_legal_title' => 'Yasal metinler',
        'pages_help_nav_title' => 'Yardım',
        'pages_help_title' => 'Yardıma mı ihtiyacınız var?',
        'pages_help_text' => 'Bize ulaşın, hemen yardımcı olalım.',
        'pages_contact_link' => 'İletişim',
        'pages_print' => 'Yazdır / PDF olarak kaydet',
        'faq_search_placeholder' => 'Sorularda ara…',
        'faq_empty' => 'Aramanızla eşleşen soru bulunamadı.',
        'faq_more_title' => 'Cevabını bulamadınız mı?',
        'faq_more_text' => 'Sorunuzu bize yazın, en kısa sürede yanıtlayalım.',
        'delivery_card_home' => 'Eve teslim',
        'delivery_card_home_text' => 'Eskişehir içinde teslimat yaptığımız ilçelerdeki adreslere kendi aracımızla.',
        'delivery_card_pay' => 'Kapıda ödeme',
        'delivery_card_pay_text' => 'Ödemeyi teslimatta yaparsınız; internette kart bilgisi istenmez.',
        // Hakkımızda sayfası
        'about_title' => 'Eskişehir\'in kitap ve baskı adresi',
        'about_lead' => 'Kitap, soru bankası ve belge baskısında hızlı, güvenilir ve uygun fiyatlı hizmet. Siparişiniz kapınıza gelir, ödemenizi teslimatta yaparsınız.',
        'about_image_path' => null,
        'about_founded' => null,               // kuruluş yılı (ör. 2016) — "N yıldır hizmetinizde"
        'about_badge' => 'yıldır hizmetinizde',
        'about_primary_button' => 'Ürünleri keşfet',
        'about_secondary_button' => 'Bize ulaşın',
        'about_stat_products' => 'Ürün çeşidi',
        'about_stat_publishers' => 'Yayınevi',
        'about_stat_districts' => 'Teslimat ilçesi',
        'about_stat_years' => 'Yıllık tecrübe',
        'about_story_kicker' => 'Biz kimiz',
        'about_story_title' => 'Hikâyemiz',
        'about_values_kicker' => 'Yol haritamız',
        'about_values_title' => 'Neye inanıyoruz?',
        'about_values' => "target|Misyonumuz|Okuldan sınava, ofisten eve ihtiyaç duyduğunuz kitap ve belgeyi doğru fiyatla, hızla ulaştırmak.\neye|Vizyonumuz|Eskişehir'de kitap ve baskı denince akla gelen ilk, en güvenilir adres olmak.\nheart|Değerlerimiz|Dürüst fiyat, söz verdiğimiz saatte teslimat ve her müşteriye güler yüz.",
        'about_features_kicker' => 'Farkımız',
        'about_features_title' => 'Neden bizi tercih etmelisiniz?',
        'about_features' => "truck|Kapınıza teslim|Eskişehir içinde belirlenen bölgelere kendi aracımızla.\ncash|Kapıda ödeme|Nakit veya kartla, teslimatta ödersiniz.\nprinter|Belge baskısı|PDF ve Word belgenizi yükleyin, basıp getirelim.\nbook|Geniş ürün yelpazesi|Sınav kitaplarından okuma kitaplarına binlerce ürün.",
        'about_milestones_kicker' => 'Kilometre taşları',
        'about_milestones_title' => 'Yolculuğumuz',
        'about_milestones' => null,            // "yıl|Başlık|Açıklama" satırları; boşsa bölüm gösterilmez
        'about_cta_title' => 'Aradığınız kitap büyük ihtimalle bizde',
        'about_cta_text' => 'Kataloğumuza göz atın; bulamadığınızı sorun, sizin için bakalım.',
        'back_to_top_label' => 'Yukarı çık', // sayfa kaydırılınca altta çıkan buton; boşsa buton gösterilmez
        'cookie_notice' => 'Sitemiz yalnız çalışması için zorunlu çerezleri (oturum ve sepet) kullanır.', // boşsa gösterilmez

        // Onay metinleri (altında ilgili sözleşme sayfalarının bağlantıları listelenir)
        'checkout_consent' => 'Sipariş bilgilerimin teslimat için kullanılmasını ve satış koşullarını onaylıyorum.',
        'register_consent' => 'Üyelik koşullarını ve KVKK aydınlatma metnini okudum, kabul ediyorum.',

        // Sepet sayfası (adımlar: her satır bir adım; güven rozetleri: simge|Başlık|Açıklama)
        'cart_steps' => "Sepetim\nTeslimat ve ödeme\nSipariş onayı",
        'cart_trust' => "shield|Güvenli alışveriş|Bilgileriniz korunur\ntruck|Kapıya teslim|Eskişehir içi hızlı teslimat\ncash|Kapıda ödeme|Nakit veya kartla ödeyin",
        'cart_empty_title' => 'Sepetiniz şu an boş',
        'cart_suggest_title' => 'Bunlar da ilginizi çekebilir',
        'cart_print_title' => 'Belge de bastırmak ister misiniz?',
        'cart_print_text' => 'PDF veya Word dosyanızı yükleyin, fiyatı görün, siparişinize ekleyelim.',
        'cart_payment_title' => 'Ödeme seçenekleri',

        // Sipariş onayı sayfası
        'done_title' => 'Siparişiniz alındı',
        'done_text' => 'Teşekkür ederiz! Siparişinizi hazırlamaya başlıyoruz; teslimat zamanında kapınızdayız.',
        'done_note' => 'Siparişinizin durumunu sipariş numaranız ve telefonunuzla dilediğiniz zaman takip edebilirsiniz.',
        'done_help_title' => 'Sorunuz mu var?',
        'done_help_text' => 'WhatsApp üzerinden bize yazın.',

        // Bulunamadı (404) sayfası; arama metninde :q aranan kelimeyle değişir
        'notfound_kicker' => 'Hata 404',
        'notfound_title' => 'Aradığınızı',
        'notfound_accent' => 'bulamadık.',
        'notfound_text' => 'Bu sayfa taşınmış ya da hiç var olmamış olabilir. Aşağıdan arayın ya da popüler bölümlere göz atın.',
        'notfound_search_text' => ':q için ürün, kategori ya da sayfa bulamadık. Farklı bir kelime, yazar adı veya ISBN deneyin.',

        // Hesabım sayfaları (üst etiket ve sayfa açıklamaları)
        'account_kicker' => 'Hesabım',
        'account_overview_text' => 'Siparişlerinizi, adreslerinizi ve hesap bilgilerinizi buradan yönetin.',
        'account_orders_text' => 'Siparişlerinizin durumunu adım adım takip edin.',
        'account_addresses_text' => 'Kayıtlı adresinizi siparişte tek tıkla seçin.',
        'account_favorites_text' => 'Beğendiğiniz ürünler burada toplanır.',
        'account_messages_text' => 'Bize yazdığınız mesajlar ve yanıtlarımız.',
        'account_settings_text' => 'Ad, e-posta, telefon ve parolanızı güncelleyin.',

        // Giriş / üye ol sayfalarının sol paneli (avantajlar: simge|Başlık|Açıklama, her satır bir madde)
        'auth_panel_kicker' => 'Hesabınız',
        'auth_panel_title' => 'Kitabınız bir tık',
        'auth_panel_accent' => 'uzağınızda.',
        'auth_panel_text' => 'Siparişlerinizi takip edin, adreslerinizi kaydedin, belgelerinizi tek yerden yönetin.',
        'auth_panel_points' => "truck|Kapınıza teslim|Eskişehir içi hızlı gönderim\nshield|Güvenli hesap|Bilgileriniz korunur\npackage|Sipariş takibi|Her adımı anlık görün",

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

    /**
     * Gönderen adresin alan adına göre SMTP sunucusu (yönetimde yalnız adres ve uygulama şifresi girilir).
     * Tanınmayan alan adları Google Workspace kabul edilir.
     *
     * @return array{host: string, port: int, scheme: string}
     */
    public static function mailServer(string $address): array
    {
        $domain = mb_strtolower((string) substr(strrchr($address, '@') ?: '', 1));
        $host = match (true) {
            in_array($domain, ['outlook.com', 'hotmail.com', 'live.com', 'msn.com'], true) => 'smtp.office365.com',
            str_starts_with($domain, 'yandex.') => 'smtp.yandex.com',
            str_starts_with($domain, 'yahoo.') => 'smtp.mail.yahoo.com',
            in_array($domain, ['icloud.com', 'me.com'], true) => 'smtp.mail.me.com',
            default => 'smtp.gmail.com',
        };

        return ['host' => $host, 'port' => 587, 'scheme' => 'smtp'];
    }

    private const CACHE_KEY = 'settings.saved';

    /** @return array<string, ?string> */
    public static function all(): array
    {
        $saved = Cache::memo()->rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->all());

        return array_merge(self::DEFAULTS, array_intersect_key($saved, self::DEFAULTS));
    }

    /** @return list<string> */
    public static function hiddenSpecs(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) self::get('hidden_specs')))));
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
        Cache::memo()->forget(self::CACHE_KEY);
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

    /**
     * Çalışma saatleri satırlarını gün / saat olarak ayırır ("Pzt–Cuma 08:00–20:00", "Cumartesi: 09:00-20:00",
     * "Pazar | Kapalı"). Saat bulunamayan satır olduğu gibi gün sütununda kalır.
     *
     * @return list<array{day: string, time: string}>
     */
    public static function workingHours(): array
    {
        $out = [];
        foreach (preg_split('/\R/u', (string) self::get('working_hours')) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (str_contains($line, '|')) {
                [$day, $time] = array_map('trim', explode('|', $line, 2));
            } elseif (preg_match('/^(.*?)[\s:]+(\d{1,2}[:.]\d{2}\s*[-–—]\s*\d{1,2}[:.]\d{2}|kapal[ıi]|24\s*saat)$/iu', $line, $m)) {
                [$day, $time] = [rtrim($m[1], " :\t"), $m[2]];
            } else {
                [$day, $time] = [$line, ''];
            }
            $out[] = ['day' => $day, 'time' => preg_replace('/\s*[-–—]\s*/u', ' – ', $time)];
        }

        return $out;
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
        return self::iconLines('trust_items');
    }

    /**
     * "simge|Başlık|Açıklama" satırlarından oluşan bir ayarı listeye çevirir (güven şeridi, takip sayfası maddeleri).
     *
     * @return list<array{icon: string, title: string, text: string}>
     */
    public static function iconLines(string $key): array
    {
        $out = [];
        foreach (preg_split('/\R/u', (string) self::get($key)) as $line) {
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
