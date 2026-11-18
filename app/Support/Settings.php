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
book|Binlerce kitap|TEK ADRESTE',
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
        'pages_meta_sections' => ':n bölüm',
        'pages_meta_reading' => ':n dk okuma',
        'pages_prev' => 'Önceki belge',
        'pages_next' => 'Sonraki belge',
        'pages_doc_kicker' => 'Yasal belge',
        'faq_meta_questions' => ':n soru',
        'faq_meta_groups' => ':n konu başlığı',
        'faq_kicker' => 'Yardım merkezi',
        'faq_lead' => 'Sipariş, teslimat, ödeme, belge baskısı ve iade hakkında merak ettikleriniz.',
        'help_kicker' => 'Yardım merkezi',
        'delivery_lead' => 'Eskişehir içinde kendi aracımızla teslimat, kapıda ödeme ve kolay iade.',
        'delivery_card_slots' => 'Teslimat saatleri',
        'delivery_card_slots_text' => 'Sipariş verirken size uygun zaman aralığını seçersiniz.',
        'delivery_highlights' => "refresh|14 gün|Gerekçesiz cayma hakkı\ntruck|Ücretsiz alım|İade ürünü adresinizden alınır\ncash|14 gün içinde|Bedel iadesi\nshield|Kart bilgisi yok|Ödeme yalnız teslimatta",
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
        'about_kicker' => 'Hakkımızda',
        'about_scene' => '1',                // karşılamada 3D marka sahnesi (kapalıysa fotoğraf ya da düz marka işareti)
        'about_hero_points' => "check|Eskişehir içi kapıya teslim\ncheck|Kapıda nakit veya kartla ödeme\ncheck|Belgeni yükle, biz basalım",
        'about_quote' => 'Her siparişi, kendi siparişimizmiş gibi özenle hazırlıyoruz.',
        'about_quote_author' => 'Ekibimiz',
        'about_services_kicker' => 'Ne yapıyoruz',
        'about_services_title' => 'Hizmetlerimiz',
        'about_services' => "book|Kitap ve yayın|Sınav hazırlık, ders ve okuma kitapları; yayınevlerine göre düzenli katalog.|/urunler\nprinter|Fotokopi ve baskı|PDF ya da Word belgenizi yükleyin; kâğıt, renk ve cilt seçeneğiyle basalım.|/fotokopi\npackage|Kırtasiye|Okul ve ofis malzemeleri; dönem başı listeleri için toplu hazırlık.|/urunler\ntruck|Kapıya teslim|Eskişehir içinde kendi aracımızla, seçtiğiniz saat aralığında.|/sayfa/teslimat-ve-iade",
        'about_steps_kicker' => 'Nasıl çalışıyoruz',
        'about_steps_title' => 'Siparişten kapınıza dört adım',
        'about_steps' => "search|Seçin|Kitabınızı bulun ya da belgenizi yükleyin.\ncart|Sipariş verin|Teslimat saatini seçin; kart bilgisi gerekmez.\nprinter|Hazırlayalım|Ürünleriniz paketlenir, belgeleriniz basılır.\ntruck|Kapınızda|Kendi aracımızla getirir, ödemeyi teslimatta alırız.",
        'about_testimonials_kicker' => 'Müşterilerimiz',
        'about_testimonials_title' => 'Bizim için ne dediler?',
        'about_testimonials' => null,         // "Ad|Bilgi (ör. Veli)|Yorum" satırları; boşsa bölüm gösterilmez
        'about_info_title' => 'Mağazamız',
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

        // Ürün değerlendirmeleri ve soru-cevap (Ayarlar › Değerlendirme ve sorular)
        'reviews_enabled' => '1',  // ürün sayfasında değerlendirmeler
        'questions_enabled' => '1',  // ürün sayfasında soru-cevap
        'reviews_auto_publish' => '0',  // 1: değerlendirmeler onaysız yayınlanır
        'reviews_photos' => '1',  // değerlendirmeye fotoğraf eklenebilir (en fazla 4)
        'reviews_tab' => 'Değerlendirmeler',
        'questions_tab' => 'Soru & Cevap',
        'reviews_summary_title' => 'Müşteri değerlendirmeleri',
        'reviews_count' => ':n değerlendirme',
        'reviews_empty' => 'Bu ürün henüz değerlendirilmedi.',
        'reviews_empty_sub' => 'Ürünü satın alıp teslim aldıysanız ilk değerlendirmeyi siz yazın.',
        'reviews_write' => 'Değerlendirme yaz',
        'reviews_edit' => 'Değerlendirmemi düzenle',
        'reviews_delete' => 'Değerlendirmemi sil',
        'reviews_verified' => 'Doğrulanmış alışveriş',
        'reviews_store_reply' => 'Mağaza cevabı',
        'reviews_login_note' => 'Değerlendirme yazmak için giriş yapın. Ürünü satın alıp teslim alan üyelerimiz değerlendirebilir.',
        'reviews_not_bought' => 'Bu ürünü üye hesabınızla satın alıp teslim aldıktan sonra değerlendirebilirsiniz.',
        'reviews_not_delivered' => 'Siparişiniz teslim edildikten sonra bu ürünü değerlendirebilirsiniz.',
        'reviews_pending_note' => 'Değerlendirmeniz onay bekliyor; yayınlanınca e-postayla haber vereceğiz.',
        'reviews_pending_edit_note' => 'Düzenlemeniz onay bekliyor; onaylanana kadar sayfada önceki hâli görünür.',
        'reviews_rejected_note' => 'Değerlendirmeniz yayınlanamadı. Düzenleyip yeniden gönderebilirsiniz.',
        'reviews_form_title' => 'Ürünü değerlendirin',
        'reviews_form_note' => 'Değerlendirmeniz incelendikten sonra adınız kısaltılarak (ör. Ayşe K.) yayınlanır.',
        'reviews_rating_label' => 'Puanınız',
        'reviews_rating_labels' => "Çok kötü\nKötü\nİdare eder\nİyi\nÇok iyi",  // beş satır: 1 yıldızdan 5 yıldıza
        'reviews_title_label' => 'Başlık',
        'reviews_title_placeholder' => 'Kısaca özetleyin (isteğe bağlı)',
        'reviews_body_label' => 'Değerlendirmeniz',
        'reviews_body_placeholder' => 'Baskı kalitesi, içerik, teslimat… Neleri beğendiniz, neler daha iyi olabilirdi?',
        'reviews_photos_label' => 'Fotoğraf ekleyin (isteğe bağlı)',
        'reviews_photos_note' => 'En fazla 4 fotoğraf; JPG, PNG veya WEBP, her biri en fazla 5 MB. Fotoğrafların konum bilgisi silinir.',
        'reviews_submit' => 'Değerlendirmeyi gönder',
        'reviews_thanks' => 'Değerlendirmeniz alındı; onaylandıktan sonra yayınlanacak.',
        'reviews_thanks_auto' => 'Değerlendirmeniz yayınlandı. Teşekkür ederiz!',
        'reviews_deleted' => 'Değerlendirmeniz silindi.',
        'reviews_delete_confirm' => 'Değerlendirmeniz ve fotoğrafları kalıcı olarak silinsin mi?',
        'reviews_filter_all' => 'Tümü',
        'reviews_filter_photos' => 'Fotoğraflı',
        'reviews_sort_new' => 'En yeni',
        'reviews_sort_high' => 'En yüksek puan',
        'reviews_sort_low' => 'En düşük puan',
        'reviews_no_match' => 'Bu seçime uyan değerlendirme yok.',
        'reviews_rate_cta' => 'Değerlendir',
        'reviews_yours' => 'Sizin değerlendirmeniz',
        'reviews_login_button' => 'Giriş yap',
        'reviews_photo_pick' => 'Fotoğraf seçin',
        'reviews_photo_remove' => 'Kaldır',
        'reviews_photo_limit' => 'En fazla :n fotoğraf olabilir; fazlası eklenmedi.',
        'questions_name_label' => 'Ad soyad',
        'questions_email_label' => 'E-posta',
        'questions_kvkk_link' => 'Aydınlatma metni',
        'questions_title' => 'Ürün hakkında sorular',
        'questions_count' => ':n soru',
        'questions_empty' => 'Henüz cevaplanmış soru yok.',
        'questions_empty_sub' => 'Ürünle ilgili merak ettiğinizi sorun; cevabımızı e-postanıza gönderelim.',
        'questions_form_title' => 'Satıcıya soru sorun',
        'questions_form_note' => 'Satın almanız gerekmez. Sorunuz cevaplandığında e-postayla haber veririz; cevaplanan sorular adınız kısaltılarak ürün sayfasında yayınlanabilir.',
        'questions_body_label' => 'Sorunuz',
        'questions_body_placeholder' => 'Örn. Bu kitabın cevap anahtarı içinde mi?',
        'questions_consent' => 'Kişisel verilerimin sorumun cevaplanması amacıyla işlenmesini kabul ediyorum.',
        'questions_submit' => 'Soruyu gönder',
        'questions_thanks' => 'Sorunuz bize ulaştı. Cevabımızı e-posta adresinize göndereceğiz.',
        'questions_answer_label' => 'Satıcının cevabı',
        'account_reviews_title' => 'Değerlendirmelerim',
        'account_reviews_text' => 'Teslim aldığınız ürünleri değerlendirin, yazdıklarınızı yönetin.',
        'account_reviews_waiting' => 'Değerlendirmenizi bekleyen ürünler',
        'account_reviews_mine' => 'Yazdığım değerlendirmeler',
        'account_reviews_empty' => 'Henüz değerlendirme yazmadınız. Teslim aldığınız ürünler burada listelenir.',
        // Değerlendirme ve soru e-postaları (App\Domain\Reviews\NoticeTemplates); :ad, :urun, :puan, :siparis, :site
        'mailtpl_question_received_on' => '1',
        'mailtpl_question_received_subject' => 'Sorunuz bize ulaştı — :urun',
        'mailtpl_question_received_heading' => 'Sorunuz bize ulaştı',
        'mailtpl_question_received_body' => 'Merhaba :ad, “:urun” hakkındaki sorunuzu aldık. En kısa sürede cevaplayıp bu adrese e-postayla bildireceğiz.',
        'mailtpl_question_received_button' => 'Ürüne git',
        'mailtpl_question_admin_on' => '1',
        'mailtpl_question_admin_subject' => 'Yeni ürün sorusu: :urun',
        'mailtpl_question_admin_heading' => 'Yeni soru geldi',
        'mailtpl_question_admin_body' => ':ad, “:urun” ürünü hakkında bir soru sordu.',
        'mailtpl_question_admin_button' => 'Panelde cevapla',
        'mailtpl_question_answered_on' => '1',
        'mailtpl_question_answered_subject' => 'Sorunuz cevaplandı — :urun',
        'mailtpl_question_answered_heading' => 'Sorunuz cevaplandı',
        'mailtpl_question_answered_body' => 'Merhaba :ad, “:urun” hakkındaki sorunuzu cevapladık.',
        'mailtpl_question_answered_button' => 'Ürüne git',
        'mailtpl_review_received_on' => '1',
        'mailtpl_review_received_subject' => 'Değerlendirmeniz bize ulaştı — :urun',
        'mailtpl_review_received_heading' => 'Değerlendirmeniz bize ulaştı',
        'mailtpl_review_received_body' => 'Merhaba :ad, “:urun” için yazdığınız değerlendirme için teşekkür ederiz. İnceledikten sonra yayınlayıp size haber vereceğiz.',
        'mailtpl_review_received_button' => 'Değerlendirmelerim',
        'mailtpl_review_admin_on' => '1',
        'mailtpl_review_admin_subject' => 'Yeni değerlendirme: :urun (:puan/5)',
        'mailtpl_review_admin_heading' => 'Yeni değerlendirme geldi',
        'mailtpl_review_admin_body' => ':ad, “:urun” ürününe :puan yıldızlı bir değerlendirme yazdı.',
        'mailtpl_review_admin_button' => 'Panelde incele',
        'mailtpl_review_edited_admin_on' => '1',
        'mailtpl_review_edited_admin_subject' => 'Değerlendirme düzenlendi: :urun (:puan/5)',
        'mailtpl_review_edited_admin_heading' => 'Değerlendirme düzenlendi',
        'mailtpl_review_edited_admin_body' => ':ad, “:urun” için yazdığı değerlendirmeyi düzenledi. Onaylayana kadar sayfada önceki hâli görünür.',
        'mailtpl_review_edited_admin_button' => 'Panelde incele',
        'mailtpl_review_published_on' => '1',
        'mailtpl_review_published_subject' => 'Değerlendirmeniz yayınlandı — :urun',
        'mailtpl_review_published_heading' => 'Değerlendirmeniz yayınlandı',
        'mailtpl_review_published_body' => 'Merhaba :ad, “:urun” için yazdığınız değerlendirme artık ürün sayfasında. Görüşünüzü paylaştığınız için teşekkür ederiz.',
        'mailtpl_review_published_button' => 'Değerlendirmemi gör',
        'mailtpl_review_published_reply_on' => '1',
        'mailtpl_review_published_reply_subject' => 'Değerlendirmeniz yayınlandı — :urun',
        'mailtpl_review_published_reply_heading' => 'Değerlendirmeniz yayınlandı',
        'mailtpl_review_published_reply_body' => 'Merhaba :ad, “:urun” için yazdığınız değerlendirme yayınlandı. Size bir de cevap yazdık:',
        'mailtpl_review_published_reply_button' => 'Değerlendirmemi gör',
        'mailtpl_review_reply_on' => '1',
        'mailtpl_review_reply_subject' => 'Değerlendirmenize cevap verdik — :urun',
        'mailtpl_review_reply_heading' => 'Değerlendirmenize cevap verdik',
        'mailtpl_review_reply_body' => 'Merhaba :ad, “:urun” için yazdığınız değerlendirmeye cevabımız:',
        'mailtpl_review_reply_button' => 'Ürün sayfasında gör',
        'mailtpl_review_rejected_on' => '1',
        'mailtpl_review_rejected_subject' => 'Değerlendirmeniz yayınlanamadı — :urun',
        'mailtpl_review_rejected_heading' => 'Değerlendirmeniz yayınlanamadı',
        'mailtpl_review_rejected_body' => 'Merhaba :ad, “:urun” için yazdığınız değerlendirmeyi yayın kurallarımıza uymadığı için yayınlayamadık. Düzenleyip yeniden gönderebilirsiniz.',
        'mailtpl_review_rejected_button' => 'Değerlendirmemi düzenle',
        'mailtpl_review_invite_on' => '1',
        'mailtpl_review_invite_subject' => 'Aldığınız ürünleri değerlendirin — :site',
        'mailtpl_review_invite_heading' => 'Ürünlerinizi nasıl buldunuz?',
        'mailtpl_review_invite_body' => 'Merhaba :ad, :siparis numaralı siparişiniz teslim edildi. Aldığınız ürünleri birkaç cümleyle değerlendirirseniz diğer müşterilerimize de yol göstermiş olursunuz.',
        'mailtpl_review_invite_button' => 'Değerlendirmeye başla',
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
