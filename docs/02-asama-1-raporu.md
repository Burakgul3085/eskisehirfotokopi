# Aşama 1 Raporu — Altyapı

Tarih: 2026-11-10 · Testler: **24 geçti** (139 doğrulama)

## Yapılanlar

**Kurulum**
- Laravel 13 (PHP 8.5 yerelde; sunucuda 8.4), MySQL 8.4 (`utf8mb4_turkish_ci`), Türkçe dil dosyaları, saat dilimi `Europe/Istanbul`.
- Testler ayrı MySQL veritabanında çalışır (`eskisehirfotokopi_test`).
- Ön yüz: Tailwind v4, Alpine.js (CSP sürümü), Inter + Plus Jakarta Sans fontları yerelden (dış kaynak yok).

**Müşteri hesapları** (`web` guard)
- Üye ol (ad, e-posta, cep telefonu, parola, KVKK onayı), giriş, çıkış, parolamı unuttum / sıfırla, Hesabım (bilgiler + parola).
- E-posta küçük harfe, telefon `05xxxxxxxxx` biçimine çevrilir (`App\Rules\TurkishPhone`).
- Parola sıfırlama isteğinde hesabın var olup olmadığı açığa çıkmaz. Giriş, üyelik ve sıfırlama için IP ve e-posta başına istek sınırı var.
- Pasif müşteri giriş yapamaz. Üyeliksiz sipariş (K8) Aşama 5'te eklenecek.

**Yönetim paneli** (`/yonetim`, ayrı `admins` tablosu ve `admin` guard'ı)
- Giriş: 5 hatalı denemede hesap 15 dakika kilitlenir, ayrıca IP başına sınır var. Pasifleştirilen yöneticinin açık oturumu bir sonraki istekte kapanır.
- Roller: **Süper yönetici** (yönetici hesapları ve işlem günlüğü dahil) ve **Personel**. Son etkin süper yönetici silinemez, pasifleştirilemez, rolü düşürülemez.
- Gösterge paneli iskeleti, işlem günlüğü (kim, ne zaman, ne yaptı; parolalar asla yazılmaz).
- İlk yönetici komutu: `php artisan yonetici:olustur e-posta "Ad Soyad"` (parola gizli sorulur).

**Ayarlar sistemi** (`App\Support\Settings`, önbellekli)
- **Genel:** site adı, slogan, duyuru şeridi (+ bağlantı), site açık / bakımda (bakımdayken giriş yapmış yönetici siteyi görür), alt bilgi metni, telif satırı.
- **İletişim:** telefon, WhatsApp (doluysa sitede yüzen WhatsApp düğmesi), e-posta, adres, çalışma saatleri, harita bağlantısı, sosyal medya hesapları.
- **Görünüm:** logo, favicon, **ana renk ve vurgu rengi**. Tüm sitenin tonları bu iki renkten türetilir, yani renk değişince site baştan sona yeniden renklenir.
- **SEO:** ana sayfa başlığı ve açıklaması.
- **E-posta (SMTP):** sunucu, port, şifreleme, kullanıcı, parola (veritabanında **şifreli**; boş bırakılırsa mevcut parola korunur), gönderen adı/adresi, yönetici bildirim adresleri. Ayar doluysa site e-postaları bu hesaptan gider.

**Güvenlik**
- Sıkı içerik güvenlik politikası (yalnız kendi kaynaklarımız; satır içi script yok), X-Frame-Options, nosniff, Referrer-Policy, HTTPS'te HSTS.
- Renk alanları yalnız `#rrggbb` kabul eder (CSS enjeksiyonu engellenir). Logo/favicon yalnız görsel ve boyut sınırlı.

**Vitrin iskeleti**
- Header: duyuru şeridi, logo, büyük arama kutusu, hesap ve sepet. Mobilde ayrı arama satırı ve açılır menü.
- Footer: hizmet şeridi, hakkımızda, hesap bağlantıları, iletişim, telif. Tamamı ayarlardan beslenir.
- Geçici ana sayfa (hero + arama). Gerçek bloklar Aşama 4'te gelecek.

## Bilinçli olarak sonraya bırakılanlar
- Header ve footer'daki **bağlantı listeleri** ile **hizmet şeridi metinleri** henüz koddadır. Aşama 4'te (menü yönetimi) ve Aşama 8'de (footer sütunları) panele taşınacak.
- Parola sıfırlama e-postası Laravel'in varsayılan şablonunu kullanıyor. Aşama 7'de marka şablonuna geçecek.
- Arama kutusu henüz sonuç vermiyor (Aşama 4: Meilisearch).

## Yerel çalıştırma
```
php artisan serve --port=8010
```
Yönetim: http://localhost:8010/yonetim

## Sıradaki: Aşama 2 — Katalog çekirdeği
Kategori ağacı (sınırsız derinlik), yayınevleri (logo, takma ad), yazarlar, ürünler (iki fiyat: siyah-beyaz / renkli, K3), mockup görsel işleme (WebP, 3 boyut), panel ekranları ve Excel fiyat şablonu altyapısı (K2).
