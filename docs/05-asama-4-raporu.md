# Aşama 4 Raporu — Vitrin

Tarih: 2026-11-10 · Testler: **53 geçti** (417 doğrulama)

## Yapılanlar

**Ana sayfa yönetimden kurulur** (Yönetim › Vitrin › Ana sayfa)
- Bloklar sıralanır (↑/↓), gizlenir/gösterilir, eklenir ve silinir. Kayıttan sonra değişiklik hemen yansır.
- Blok türleri:
  - Karşılama alanı (başlık, metin, arama kutusu)
  - Görsel slayt (6 slayt; kendiliğinden geçer, mobilde parmakla kaydırılır)
  - Kategori kutuları
  - Ürün satırı (kaynaklar aşağıda)
  - Yayınevi logoları
  - Fotokopi bandı
  - Güven şeridi
  - Sıkça sorulan sorular (schema.org FAQ verisiyle)
  - Serbest metin
- Ürün satırı kaynakları: yeni eklenenler, çok satanlar, en çok incelenenler, öne çıkanlar, indirimliler, seçilen kategori, seçilen yayınevi ve el ile seçilen ürünler. El ile seçmede ürün kodu, ISBN veya numara yazılır ve sıra korunur.
- İçeriği olmayan blok (ör. ürünü olmayan kategori satırı) hiç çizilmez.
- Kurulumda örnek bir düzen gelir.
- Menü bağlantıları ve güven şeridi Ayarlar › Genel'den yönetilir; sitede sabit metin kalmadı.

**Header**
- Kategori menüsü yönetimdeki ağaçtan gelir: "Tüm kategoriler" paneli ve ilk 6 ana kategorinin açılır alt menüsü. Menü fareyle ve klavyeyle (Tab) açılır, JavaScript gerektirmez.
- Mobil menüde kategoriler iç içe açılır (3 düzey).
- **Anlık arama:**
  - Yazdıkça 180 ms içinde ürün (görsel, yayınevi, fiyat), yayınevi ve kategori önerileri gelir.
  - ↑/↓ ve Enter ile gezinilir.
  - Sonuçlar tarayıcıda ve sunucuda (5 dakika) önbelleklenir.
  - JavaScript kapalıysa normal arama formu olarak çalışır.

**Sayfalar**
- **Kategori** (`/kategori/{ad}`): breadcrumb, alt kategori çipleri, alt ağaçtaki tüm ürünler.
  - Filtreler: yayınevi (sonuçtaki ürün sayısıyla ve aramalı), baskı tipi (S-B / renkli), fiyat aralığı, baskı yılı.
  - Sıralama: önerilen, en yeni, çok satan, fiyat (artan/azalan), A-Z, Z-A.
  - Filtreler URL'de tutulur. Canonical adres filtresiz sayfadır.
  - Mobilde filtreler açılır panelde.
- **Ürün** (`/urun/{ad}`):
  - Büyük mockup; tıklanınca büyük görsel açılır.
  - Baskı tipi seçimi: iki fiyat, eski fiyat ve doğru indirim yüzdesi.
  - Adet ve "Sepete ekle" (Aşama 5'te bağlanır).
  - Bilgi tablosu: yazar, yayınevi, kategori, sayfa, yıl, ISBN, kod.
  - Benzer ürünler ve aynı yayınevinden ürünler.
  - schema.org Product verisi.
  - Taslak ürün yalnız yöneticiye "önizleme" olarak açılır.
- **Yayınevleri** (`/yayinevleri`): öne çıkan logolar, A–Z harf dizini (Türkçe harflerle), anlık filtre ve ürün sayıları.
- **Yayınevi** (`/yayinevi/{ad}`): logo, açıklama, kategori kısayolları, filtreli ürün listesi.
- **Arama** (`/ara?q=`):
  - Ad, yazar, yayınevi, kategori, ISBN (tireli veya tiresiz) ve ürün koduyla aranır.
  - Türkçe karakterden bağımsızdır ("hiz" ile "Hız" aynı sonucu verir).
  - Başlığı sorguyla başlayanlar önce gelir.
  - Sonuç sayfası arama motorlarına kapalıdır (`noindex`).
- **Tüm ürünler** (`/urunler`).

**Hız**
- Ürün kartlarında görsel boyutları önceden yazılır, böylece sayfa kaymaz. Görseller 320/640/1400 px'lik `srcset` ile ve tembel yüklenir. İlk satırdaki görseller öncelikli yüklenir.
- Ana sayfa blok verisi ve benzer ürünler önbelleklenir. Önbellekte yalnızca kimlikler tutulur; Laravel 13 önbellekten model açmayı güvenlik gereği reddettiği için kayıtlar tek sorguda yüklenir.
- Kategori ağacı kalıcı önbellekte; menü her sayfada sorgusuz çizilir.

## Not
- Geliştirme ortamında `APP_URL` 8010 portuna çekildi (görsel adresleri yanlış porta gidiyordu).

## Sıradaki: Aşama 5 — Sepet, misafir sipariş, teslimat bölgeleri
