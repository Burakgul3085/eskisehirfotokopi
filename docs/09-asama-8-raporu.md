# Aşama 8 raporu: sayfalar, alt bilgi, çerez bildirimi, site haritası

## Yapılanlar

**Kurumsal ve yasal sayfalar (Yönetim › Vitrin › Sayfalar)**
- Sayfa adresi `/sayfa/{adres}`. İçerik Markdown ile yazılır:
  - Biçimler: `## başlık`, `**kalın**`, `- madde`, `[bağlantı](https://…)`.
  - HTML etiketleri ve `javascript:` bağlantıları temizlenir.
- Yer tutucular ayarlardaki bilgilerle dolar: `{site_adi}`, `{telefon}`, `{eposta}`, `{adres}`.
- Hazır gelen sayfalar:
  - Hakkımızda
  - Teslimat ve İade
  - Sıkça Sorulan Sorular
  - Mesafeli Satış Sözleşmesi
  - KVKK Aydınlatma Metni
  - Üyelik Koşulları
  - Çerez Politikası
- **Bu metinler taslaktır.** Yayından önce işletme bilgileriyle gözden geçirilmeli; gerekirse bir hukukçuya gösterilmeli.
- **Sistem sayfaları:** Hazır gelen sayfalar formlardan bağlandığı için silinemez, yalnız gizlenebilir. Gizli sayfa vitrinde 404 verir; yönetici önizleyebilir.
- Her sayfa için:
  - alt bilgi sütunu: Kurumsal, Yardım ya da gösterme
  - sıra
  - arama motoru açıklaması

**Alt bilgi**
- Sayfalar Kurumsal ve Yardım sütunlarında listelenir. Sütun başlıkları Ayarlar › Genel'den değiştirilir.
- Yardım sütununa ayrıca Sipariş takibi ve İletişim bağlantıları eklenir.

**Onay metinleri**
- Sipariş, üyelik ve iletişim formlarındaki onay kutusu metinleri ayarlardan gelir.
- Altlarında ilgili sayfaların bağlantıları listelenir; bağlantılar yeni sekmede açılır:
  - sipariş: Mesafeli Satış Sözleşmesi, KVKK, Teslimat ve İade
  - üyelik: Üyelik Koşulları, KVKK
  - iletişim: KVKK

**Çerez bildirimi**
- Site yalnız zorunlu çerezleri (oturum, sepet, güvenlik) kullanır; bu yüzden onay değil bilgilendirme gösterilir.
- Bildirim bir kez kapatılınca tarayıcıda hatırlanır.
- Metni Ayarlar › Genel'den değiştirilir; boş bırakılırsa bildirim gösterilmez.

**Site haritası**
- `/sitemap.xml`: ana sayfa, fotokopi, yayınevleri, iletişim; ayrıca aktif kategoriler, yayınevleri, sayfalar ve yayındaki ürünler. 6 saat önbellekte tutulur.
- `/robots.txt`: yönetim, sepet, sipariş, hesap ve mesaj sayfaları taranmaz. Site haritasının adresini içerir.

## Düzeltilen hata
- Mağaza şablonundaki `request('page')` çağrısı rota parametresini de okuyordu. `/sayfa/{page}` rotasında bu, sayfa nesnesini sayıya çevirmeye çalışıp 500 hatası veriyordu.
- Artık yalnız adres çubuğundaki `?page=` değeri okunuyor (`request()->query('page')`).

## Testler
- `tests/Feature/PagesAndSeoTest.php` dosyasında 4 test var. Kapsadıkları:
  - yer tutucular ve güvenli Markdown
  - alt bilgi, onay bağlantıları ve çerez bildirimi
  - yönetimde sayfa ekleme ve silme, sistem sayfasının silinememesi
  - site haritası ve robots.txt
- Toplam: 83 test, hepsi geçiyor.
