# Aşama 9 raporu (yerel kısım): güvenlik, performans, son kontroller

Sunucu kurulumu ve canlıya alma bu raporun dışındadır; ayrı bir adımda yapılacak.

## Güvenlik taraması

**Kontrol edilenler (sorun yok)**
- **Bağımlılıklar:** `composer audit` ve `npm audit` bilinen açık bulmadı.
- **Ham HTML çıktısı (`{!! !!}`):**
  - Yalnız şu yerlerde var: JSON-LD (`JSON_HEX_TAG` ile), simge SVG'leri, tema CSS'i ve sayfa içeriği.
  - Sayfa içeriğinde Markdown'daki HTML etiketleri kaçırılır, `javascript:` bağlantıları kaldırılır.
  - Mesaj ve e-posta metinleri her zaman kaçırılarak basılır.
- **Ham SQL:** Kullanıcıdan gelen değerler bağlı parametreyle gider. `FIELD(id, …)` yalnız tam sayıya çevrilmiş kimliklerle kurulur.
- **Erişim denetimi:**
  - Hesabım'daki sipariş ve adres sayfaları sahibini denetler.
  - Sipariş takibi ve mesaj konusu gizli belirteçle açılır; belirteçler sabit süreli karşılaştırılır (`hash_equals`).
  - Fotokopi belgeleri oturum sahibine bağlıdır.
  - "Sipariş tamamlandı" sayfası yalnız siparişi veren oturumda açılır.
- **Oturum:** Girişte oturum yenilenir, çıkışta belirteç değişir. Giriş, kayıt, parola sıfırlama, sipariş, takip, iletişim ve yükleme uçlarında hız sınırı var.
- **Güvenlik başlıkları:** CSP (satır içi betik yok), X-Frame-Options, nosniff, Referrer-Policy. HTTPS'te HSTS.

**Düzeltilenler**
- **Kapatılan müşteri hesabı:**
  - Önceden açık oturumlar siliniyordu ama "beni hatırla" çereziyle müşteri tekrar girebiliyordu.
  - Artık hesap kapatılınca hatırlama belirteci değişiyor.
  - Yeni `EnsureCustomerActive` ara katmanı, kapalı hesabı her istekte oturumdan çıkarıyor.
- **Kapalı mesaj konusu:** Müşteri e-postadaki bağlantıyla kapalı konuya yanıt yazabiliyordu. Artık engelleniyor; iletişim formuna yönlendiriliyor.
- **Fotokopi denetleyicisi:** Oturuma bağlı belge sahibi nesnesini kurucuda alıyordu. Laravel denetleyici örneğini saklayabildiği için bu nesne artık her istekte yeniden çözülüyor.

## Performans (10 bin ürün)

Ölçüm için ayrı bir veritabanına 10 bin ürün, 80 yayınevi, 300 yazar ve 90 kategori yüklendi. Ölçümden sonra bu veritabanı silindi.

| Sayfa | Önce | Sonra | Neden |
|---|---|---|---|
| Her sayfa | ~115 sorgu | 8–17 sorgu | Ayar, kategori ağacı ve sayfa bağlantıları istek başına onlarca kez önbellek tablosundan okunuyordu. `Cache::memo()` ile istek içinde bir kez okunuyor. |
| /yayinevleri | 894 ms (sorgu 752 ms) | 120 ms | Ürün sayıları yayınevi başına alt sorguyla değil, tek gruplu sorguyla hesaplanıyor. |
| Kategori sayfası | 417 ms (sayım 205 ms) | 215 ms | Alt kategori kimlikleri önce çekilip listeyle süzülüyor. Alt sorgulu IN sayımı yavaşlatıyordu. Ana sayfanın kategori bloğuna da uygulandı. |
| Arama, ürün, ana sayfa | 250–350 ms | 240–330 ms | Zaten iyiydi. |
| Yönetim sayfaları | — | 60–240 ms, en fazla ~30 ms veritabanı | Sorun yok. |

Yerel süreler Windows'taki `artisan serve` sunucusunda, önbelleksiz ve hata ayıklama açıkken ölçüldü. Bunların yaklaşık 160 ms'si sunucunun sabit yükü. Canlı sunucuda daha hızlı olacak; bunun için şunlar gerekiyor:
- opcache
- `php artisan optimize`
- `CACHE_STORE=redis` ya da `file`

## Son kontroller
- Vitrinin ve yönetimin 50'ye yakın adresi tarayıcıda açıldı: hepsi 200 döndü, konsolda hata yok. Tek 404 olan `/yonetim/ana-sayfa/create` beklenen davranış: blok türü listeden seçiliyor.
- 375 px mobil genişlikte vitrin ve yönetim sayfalarında yatay taşma yok.
- **Düzeltildi:** Ayarlarda telefon, adres ya da e-posta boşsa sözleşme metinlerinde boşluk kalıyordu. Artık "—" yazıyor. Yönetimdeki sayfa formunda hangi bilginin eksik olduğu uyarısı çıkıyor.

## Testler
- `tests/Feature/HardeningTest.php` dosyasında 6 test var. Kapsadıkları:
  - kapatılan hesabın oturumdan çıkarılması
  - kapalı konuya yanıtın engellenmesi
  - boş yer tutucu ve yönetim uyarısı
  - istek içi önbelleğin güncel kalması
  - yayınevi sayımı
  - güvenlik başlıkları
- Toplam: 89 test, hepsi geçiyor.

## Yerelde denenemeyenler (sunucuda denenecek)
- ~~**Tesseract OCR**~~ → 2026-11-10'da yerelde kuruldu (choco, 5.5.3 + `tur` tessdata_best; `.env` → `OCR_TESSERACT`) ve denendi:
  - Plan §6 düzenindeki bilgi tablosu (açık ve koyu tema) tüm alanlarıyla doğru okundu; kitap kontrole düşmeden eklendi (~1,3 sn).
  - Düzeltme: ön işlemdeki kontrast adımı Türkçe işaretleri siliyordu ("Bogazici"); kaldırıldı.
  - "soru banka" arşivinde bilgi tablosu yok (KAPAK, BASIM, İÇİNDEKİLER); bilgiler klasör adından gelir, kitaplar "Hepsini onayla" ile onaylanır.
- **LibreOffice:** Word, PowerPoint ve Excel dosyalarının PDF'e çevrilmesi.
- **poppler (`pdfinfo`, `pdftoppm`):** PDF sayfa sayımı yerelde yedek yöntemle çalıştı. İlk sayfa önizlemesi denenemedi.

## Canlıya almadan önce sizin yapmanız gerekenler
- **İletişim bilgileri:** Ayarlar › İletişim'e telefon, e-posta ve adres girin. Sözleşme metinleri bunları kullanıyor.
- **Sözleşme metinleri:** Mesafeli Satış Sözleşmesi, KVKK, Üyelik Koşulları, Teslimat ve İade metinlerini işletme bilgileriyle gözden geçirin; gerekirse bir hukukçuya gösterin.
- **E-posta:** Ayarlar › E-posta'ya SMTP bilgilerini girin, sonra deneme e-postası gönderin.
