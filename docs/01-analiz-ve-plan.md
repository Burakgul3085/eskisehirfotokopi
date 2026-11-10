# Eskişehir Fotokopi — Analiz ve Plan

> Durum: **Taslak, kullanıcı onayı bekliyor.** Açık sorular en sonda (§12).
> Tarih: 2026-11-10

## 1. Proje özeti

Eskişehir içinde çalışan bir fotokopi/baskı merkezinin web sitesi. İki tür satış var:

1. **Katalog ürünleri:** Yönetimin yüklediği kitaplar/soru bankaları (fotokopi baskı). Müşteri sepete ekler, sipariş verir.
2. **Kendi belgesini bastırma:** Müşteri PDF/Word/görsel yükler, renk, yüz, düzen, cilt ve kopya seçer, sepete ekler.

**Ödeme altyapısı ve fatura entegrasyonu yok.** Ödeme **kapıda** yapılır (nakit veya kartla, kapıda POS). Bu yüzden hizmet yalnızca **Eskişehir'de, yönetimin belirlediği ilçelere** verilir. Mağazadan teslim yok, yalnızca kapıya teslim var. Belirli bir tutarın üstünde teslimat ücretsiz, altında teslimat ücreti alınır.

Sitede **statik hiçbir şey yok**: header, menüler, ana sayfa blokları, footer, sayfalar, mail metinleri, teslimat bölgeleri ve fiyatlar yönetim panelinden değiştirilir.

## 2. Rakip analizi (özet)

İncelenen siteler: fotokopial.com (Limon Fotokopi) ve fotokopisec.com (Zeta Fotokopi). İkisi aynı yazılım üzerinde çalışıyor.

**Alınacak iyi fikirler**
- Büyük, ortada duran arama. Kitap adı, ders ve ISBN ile arama.
- Sınav bazlı kategori menüsü (YKS, KPSS, ALES, DGS, Ara Sınıflar…).
- Ana sayfada sınav kutuları, yayınevi logoları, "Çok satanlar" ve "Yeni eklenenler" satırları.
- Kategori sayfasında yayınevi filtresi, Renkli/Siyah-beyaz filtresi ve 7 sıralama seçeneği. Izgara veya liste görünümü.
- Yayınevi sayfası: yayınevinin tüm ürünleri ve yayınevinin kategorileri.
- Ürün sayfası: ürün kodu, ISBN, yayınevi, baskı yılı, tahmini teslim tarihi. Sepete ekleyince "Sepete git" penceresi ve öneri ürünler.
- Güven şeridi: ücretsiz teslimat eşiği, destek hattı, WhatsApp.

**Düzeltilecek zayıflıklar**
- Kartlarda kapak görseli çoğu zaman yok. Bizde her üründe mockup olacak.
- Tasarım kalabalık ve eski görünüyor. Ana sayfada aynı blok iki kez tekrar ediyor. Menü etiketlerinde emoji var.
- İndirim matematiği tutarsız: "%40" yazıyor, gerçek indirim yaklaşık %29.
- Yayınevi listesinde tekrarlar var (HIZ / Hız, ARTI BİR / ARTIBİR) ve kişi adları yayınevi olarak girilmiş. Arama ve harf indeksi yok.
- Ürün açıklaması görsel olarak konmuş. Metin olmadığı için SEO zayıf. Yazar ve sayfa sayısı yok.
- 115 sayfalık sayfalama ve aramasız, çok uzun yayınevi filtresi.
- Ürün sayfasında "Kitabı gönderen" kişinin adı herkese açık. Bu bir gizlilik sorunu.

## 3. Teknoloji

| Katman | Seçim | Neden |
|---|---|---|
| Dil / çatı | PHP 8.4 + **Laravel** | B2B projesiyle aynı. Fotokopi modülü, mail altyapısı, panel ve deploy betikleri doğrudan taşınır. |
| Veritabanı | MySQL 8.4 | İstenen |
| Önbellek / kuyruk / oturum | Redis | Sayfalar hızlı açılır. OCR ve görsel işleme arka planda çalışır. |
| Arama | **Meilisearch** (Laravel Scout) | 10.000+ üründe yazım hatasına dayanıklı, Türkçe uyumlu, anlık (milisaniyede) arama |
| Ön yüz | Blade + Alpine.js + Tailwind (Vite) | Hafif. SPA yükü olmadan hızlı açılır. |
| Görsel | libvips / Intervention → WebP, 3 boyut | Kartta küçük görsel yüklenir, `srcset` ve lazy-load kullanılır |
| OCR | **Tesseract 5 (Türkçe)** + etiket tabanlı ayrıştırıcı. İsteğe bağlı yapay zekâ yedeği. | §6 |
| Belge işleme | LibreOffice (headless) + poppler | B2B'deki fotokopi modülünden |
| Sunucu | Hostinger KVM 2, **Ubuntu 24.04 LTS**, Nginx, PHP-FPM, Supervisor | 24.04 daha uzun destek alır. B2B `deploy/` betikleri uyarlanır. |
| CDN / SSL | Cloudflare (ücretsiz) + Let's Encrypt | Görseller önbellekten gelir |

## 4. Kullanıcı tarafı (vitrin)

**Header (yönetimden):** duyuru şeridi, logo, büyük arama, Hesabım, Favoriler, Sepet, mega menü (sınırsız derinlikte kategoriler) ve sabit linkler (Yayınevleri, Fotokopi Çektir, Sipariş Takip…).

**Anlık arama:** Yazarken açılan panelde ürün (görselli), yayınevi ve kategori önerileri gelir. ISBN, ürün kodu, yazar ve yayınevi ile aranabilir.

**Ana sayfa:** Yönetimden sıralanabilir **bloklardan** oluşur:
hero slider · sınav/kategori kutuları · yayınevi logo şeridi · ürün satırları (çok satanlar, yeni eklenenler, seçilen bir kategori, el ile seçilen ürünler) · "Belgeni yükle, kapına getirelim" fotokopi bandı · teslimat bölgeleri ve ücretsiz teslimat eşiği · SSS · güven şeridi.

**Kategori sayfası:** breadcrumb, alt kategori çipleri ve filtreler. Filtreler: yayınevi (aramalı), yazar, fiyat aralığı, renkli/siyah-beyaz, baskı yılı. Sıralama: yeni, A-Z, Z-A, fiyat artan/azalan, çok satan, popüler. Sayfalama ve "daha fazla yükle". Filtreler URL'de tutulur, böylece paylaşılabilir ve SEO uyumlu olur.

**Ürün sayfası:** büyük mockup (yakınlaştırma) ve galeri. Ad, yazar, yayınevi (logolu), ürün kodu, ISBN, kategori yolu, baskı yılı, sayfa sayısı, baskı tipi. Fiyat (indirim doğru hesaplanır), adet, sepete ekle, favori. Tahmini teslim günü (ilçeye göre). Sekmeler: açıklama ve teslimat bilgisi. Altında benzer ürünler ve aynı yayınevinden ürünler.

**Yayınevleri:** logo ızgarası, arama, A-Z harf indeksi. **Yayınevi sayfası:** logo, açıklama, yayınevinin kategorileri ve ürünleri (filtrelerle).

**Fotokopi Çektir:** B2B'deki akış taşınır. Çoklu belge, 8 MB'lık parçalarla kesintiye dayanıklı yükleme, sayfa sayısı otomatik çıkar, canlı fiyat. Seçenekler: renk, tek/çift yüz, bir yüze 1/2/4/8 sayfa, A4/A3, sayfa aralığı, spiral cilt, kopya sayısı. Kademeli fiyatlar yönetimden ayarlanır. Telif onay kutusu var.

**Sepet ve sipariş:** Katalog ürünleri ve baskı işleri aynı sepette. Ödeme adımında:
adres (yalnızca hizmet verilen ilçe/mahalleler seçilebilir) → teslimat ücreti veya "ücretsiz teslimat için X ₺ kaldı" → ödeme yöntemi (**kapıda nakit** / **kapıda kart**) → not → onay. En düşük sipariş tutarı yönetimden ayarlanır.

**Hesabım:** siparişler ve durum zaman çizelgesi, adresler, favoriler, mesajlar (destek talepleri), profil. Üye olmadan sipariş takibi (sipariş no + telefon).

**Diğer:** iletişim formu, CMS sayfaları (KVKK, mesafeli satış, teslimat koşulları, SSS…), çerez bandı, WhatsApp butonu, sitemap.xml, schema.org ürün verisi.

## 5. Yönetim paneli

- **Gösterge paneli:** bugünkü siparişler, durum dağılımı, ilçe bazlı teslimat listesi, son mesajlar.
- **Siparişler:** filtre, durum değiştirme (her değişiklikte müşteriye mail), yazdırılabilir sipariş fişi, **ilçeye göre günlük teslimat listesi** (kurye rotası), baskı dosyalarını indirme, iptal.
- **Ürünler:** liste, hızlı düzenleme, **toplu fiyat/kategori/durum değiştirme**, Excel dışa ve içe aktarma.
- **Toplu içe aktarma:** klasör sürükle-bırak (§6).
- **Kategoriler:** sürükle-bırak ağaç, sınırsız derinlik, birleştirme ve taşıma.
- **Yayınevleri:** logo, açıklama, **takma adlar** (HIZ / Hız Yayınları gibi tekrarları önler), birleştirme.
- **Fotokopi ayarları:** kademeli fiyatlar, renkler, cilt, A3, sınırlar, dosya saklama süreleri.
- **Teslimat:** ilçe ve mahalle listesi, her bölge için ücret, ücretsiz teslimat eşiği, en düşük sipariş, tahmini teslim süresi, açık/kapalı.
- **Müşteriler** ve **Mesajlar** (yanıt verince müşteriye mail gider).
- **Görünüm:** logo, renkler, header linkleri, mega menü, footer sütunları, sosyal medya, iletişim, slider, ana sayfa blokları.
- **Sayfalar** (zengin metin editörü), **SEO** (başlık ve açıklama şablonları, yönlendirmeler).
- **Mail:** SMTP ayarları, şablon metinleri, deneme maili, gönderim günlüğü.
- **Yöneticiler ve yetkiler**, işlem günlüğü.

## 6. Toplu ürün yükleme ve OCR (kritik modül)

**Klasör yapısı (kullanıcının ekran görüntülerinden):**
```
ANA KLASÖR/
└── BOĞAZİÇİ ÜNİVERSİTESİ YAYINLARI EKRAN GÖRÜNTÜLERİ/        ← yayınevi
    └── BOĞAZİÇİ ÜNİVERSİTESİ YAYINLARI - ALMANYANIN KISA TARİHİ - MARY FULBROOK/   ← ürün
        ├── 1.jpg                                     ← düz kapak
        ├── ChatGPT Görseli 27 Eki 2026 14_19_55.png  ← 3B mockup (ana görsel)
        └── Ekran görüntüsü 2026-10-27 142031.png     ← bilgi tablosu (OCR)
```
Bilgi tablosundaki alanlar: Ürün Kodu, Yazar, Yayınevi, Türü (`Tarih / Avrupa Tarihi / Almanya Tarihi`), ISBN/Barkod, Sayfa Sayısı.

**Akış**
1. Panelde "Toplu Yükle" sayfasına **ana klasör**, tek bir yayınevi klasörü veya tek bir kitap klasörü sürüklenir. Derinlik otomatik algılanır.
2. Tarayıcı ağacı okur ve yüklemeden **önce özet** gösterir: X yayınevi, Y kitap, Z sorunlu klasör (bilgi görseli eksik, mockup eksik…).
3. Dosyalar 6 paralel bağlantıyla yüklenir. Yükleme kesilirse kaldığı yerden sürer. Daha önce yüklenen dosya (içerik özeti aynıysa) atlanır, bu yüzden aynı klasörü yeniden sürüklemek güvenlidir.
4. Her kitap klasörü sunucuda bir kuyruk işi olur:
   - **Dosya sınıflandırma:** "Ekran görüntüsü" → bilgi, "ChatGPT Görseli" → mockup, diğeri → kapak. İsim tutmazsa en/boy oranı ve metin yoğunluğuna bakılır.
   - **OCR:** Tesseract (tur+eng). Görsel önce büyütülür ve keskinleştirilir. Ardından etiket tabanlı ayrıştırma yapılır ("Yazar" satırının sağındaki değer gibi). ISBN için 13 hane ve kontrol hanesi doğrulanır.
   - **Yedek bilgi:** OCR'ın okuyamadığı alanlar klasör adından (`YAYINEVİ - KİTAP ADI - YAZAR`) doldurulur. "Henüz doğrulanmadı" gibi değerler boş sayılır.
   - **Yayınevi eşleştirme:** "EKRAN GÖRÜNTÜLERİ" eki atılır, Türkçe büyük/küçük harf normalize edilir, "YAYINLARI / YAYINEVİ / YAYINCILIK" eşdeğer sayılır, takma adlara bakılır. Bulunamazsa yayınevi oluşturulur.
   - **Kategori:** "Türü" yolu ağaca çevrilir (`Tarih › Avrupa Tarihi › Almanya Tarihi`). Eksik düğümler oluşturulur. Türü yoksa "Sınıflandırılmamış" kategorisine düşer.
   - **Ürün:** ISBN, sonra ürün kodu, sonra yayınevi + ad ile aranır. Varsa güncellenir, yoksa oluşturulur.
   - **Görseller:** WebP'ye çevrilir, 3 boyut üretilir.
5. **Kontrol ekranı:** Güveni düşük ürünler bilgi görseli ile yan yana, düzenlenebilir alanlarla listelenir. Toplu "Onayla ve yayınla" yapılır. Sorunsuz ürünler doğrudan yayına alınabilir (ayar).

**Hız:** Tesseract 2 çekirdekte görsel başına yaklaşık 0,5–1,5 sn sürer. 10.000 kitap 2 işçiyle birkaç saatte arka planda biter; panel bu sürede kullanılabilir. Asıl sınır **internet yükleme hızı**. Bu yüzden ikinci bir yol da olacak: klasör SFTP/rsync ile sunucuya atılır, panelden "sunucudaki klasörden içe aktar" çalıştırılır.

**Ücretli yapay zekâ kullanılmaz (K10).** OCR ayrıntısı §6.1'de. "1" adlı kapak dosyası kullanılmaz (K5).

## 7. Sipariş durumları ve mailler

`Alındı → Onaylandı → Hazırlanıyor → Yola çıktı → Teslim edildi` ve `İptal`. Her geçiş zaman çizelgesine yazılır ve müşteriye mail gider. Yeni siparişte yöneticiye de mail gider.

**Tek, marka renkli, mobil uyumlu mail şablonu.** Logo ve renkler panelden gelir. Mail; sipariş özeti, durum çubuğu ve buton içerir. Kullanılacağı yerler: hoş geldin, e-posta doğrulama, şifre sıfırlama, her sipariş durumu, iptal, mesaj yanıtı, iletişim formu (yöneticiye), yeni sipariş (yöneticiye), fotokopi belgesi işlenemedi. Mailler kuyruktan gider, gönderim günlüğü tutulur. B2B'deki Gmail/SMTP altyapısı kullanılır.

## 8. Performans hedefleri

- Ana sayfa ve kategori sayfası sunucu yanıtı < 200 ms (Redis önbelleği; ürün değişince ilgili önbellek temizlenir).
- Arama sonucu < 50 ms (Meilisearch).
- Kartlarda yaklaşık 20–30 KB WebP, lazy-load, `width/height` ile kayma olmaz. LCP < 2,5 sn (mobil).
- Kategori sayfaları sayfalamalı; aynı anda yalnızca görünen ürünler yüklenir.
- 10.000 ürünlük deneme verisiyle yük testi (Aşama 9).

## 9. Veri modeli (ana tablolar)

`users` (müşteri) · `admins` · `addresses` · `districts` / `neighborhoods` (teslimat bölgeleri, ücret) · `categories` (nested set) · `publishers` + `publisher_aliases` · `authors` · `products` (kod, isbn, ad, slug, yazar, yayınevi, sayfa, baskı tipi, yıl, fiyat, eski fiyat, durum, satış/görüntülenme sayaçları) · `product_images` · `category_product` · `carts` / `cart_items` · `print_files` / `print_jobs` · `orders` / `order_items` / `order_status_history` · `favorites` · `messages` / `message_threads` · `import_batches` / `import_items` · `settings` · `menus` / `menu_items` · `home_blocks` · `sliders` · `pages` · `mail_templates` / `mail_log` · `activity_log`.

## 10. Aşamalar

| # | Aşama | Çıktı |
|---|---|---|
| 0 | Analiz ve plan | Bu belge, repo |
| 1 | Altyapı | Laravel kurulumu, müşteri ve yönetici girişleri, ayarlar sistemi, panel iskeleti, tasarım sistemi |
| 2 | Katalog çekirdeği | Kategori ağacı, yayınevleri, yazarlar, ürünler, görsel işleme, panel ekranları |
| 3 | **Toplu yükleme ve OCR** | Klasör sürükle-bırak, kuyruk, OCR, eşleştirme, kontrol ekranı (kullanıcının gerçek klasörüyle test) |
| 4 | Vitrin | Ana sayfa blokları, mega menü, kategori/yayınevi/ürün sayfaları, Meilisearch araması |
| 5 | Sepet ve sipariş | Kapıda ödeme, teslimat bölgeleri, ücret ve eşik, sipariş durumları |
| 6 | Fotokopi modülü | B2B'den taşıma ve uyarlama |
| 7 | Mail ve mesajlaşma | Şablon, tüm olay mailleri, destek mesajları |
| 8 | İçerik ve SEO | Sayfalar, menüler, footer, sitemap, KVKK/çerez |
| 9 | Güvenlik ve performans | 10.000 ürün yük testi, güvenlik taraması, yedekleme |
| 10 | Yayına alma | VPS kurulumu, domain, SSL, Cloudflare, canlı veri yükleme |

Her aşama sonunda testler çalışır, `docs/` altına aşama raporu yazılır ve `main`'e push edilir.

## 11. Riskler

- **Telif:** Yayınevlerinin kitaplarının fotokopisini satmak, Fikir ve Sanat Eserleri Kanunu (FSEK) kapsamında yayınevlerinin şikâyetine açıktır. Rakiplerin de yaptığı bu iş, bir hukuk danışmanıyla değerlendirilmeli. Bu karar teknik planı değiştirmez.
- **Yükleme süresi:** 10.000 kitap × 3 görsel büyük bir veri. İnternet yavaşsa SFTP yolu kullanılır.
- **OCR doğruluğu:** Bilgi tabloları tek tip ekran görüntüsü olduğu için yüksek doğruluk bekleniyor. Yine de kontrol ekranı zorunlu bir güvenlik ağı olarak kalacak.

## 12. Kararlar (2026-11-10, kullanıcı yanıtları)

| # | Karar |
|---|---|
| K1 | **Müşteri belgeleri:** Fiyat yapbozkırtasiye.com/fotokopi mantığıyla otomatik hesaplanır (B2B `PrintSettings/PrintPricing` taşınır). |
| K2 | **Katalog ürünleri:** Fiyatı yönetim girer. Yükleme bittikten sonra panelden **Excel fiyat şablonu** indirilir. Şablonda tüm aktif ürünler satır satır gelir (ID, ürün kodu, ISBN, ad, yayınevi, mevcut fiyatlar). Yönetim yalnızca fiyat sütunlarını doldurup geri yükler; sistem doğrular ve uygular. Fiyatı olmayan ürün vitrinde "fiyat bekliyor" durumunda kalır ve satılmaz. |
| K3 | **İki fiyat:** Her katalog ürününün **Siyah-beyaz** ve **Renkli** fiyatı vardır. Müşteri ürün sayfasında baskı tipini seçer. Bir fiyat boşsa o seçenek satılmaz. Excel şablonunda iki ayrı sütun bulunur (isteğe bağlı üstü çizili fiyat sütunlarıyla). |
| K4 | Bilgi tablolarındaki sayfa sayıları doğrulanmıştır. Sayfa sayısı zorunlu alan sayılır. |
| K5 | "1" adlı düz kapak dosyası **kullanılmaz**; her klasörde bulunmuyor. Ürün görseli yalnızca mockup'tır. Dosya sınıflandırma: bilgi tablosu (OCR) + mockup; diğer dosyalar yok sayılır. |
| K6 | **Teslimat kuralları tamamen panelden yönetilir:** ilçe/mahalle listesi, bölge ücreti, ücretsiz teslimat eşiği, en düşük sipariş, teslim süresi (aynı gün / ertesi gün / saat aralığı, sipariş kesim saati). Kodda sabit değer yok. |
| K7 | Domain: **eskisehirfotokopi.com** |
| K8 | **Üyeliksiz sipariş** açık. Takip sipariş no + telefon/e-posta ile yapılır. İsteyen sipariş sonrası tek tıkla üye olabilir. |
| K9 | Kitap PDF'leri siteye **yüklenmez**, mağaza bilgisayarında kalır. Siteye yalnızca mockup (≤10 MB) ve bilgi görseli yüklenir. |
| K10 | **Ücretli yapay zekâ/API yok.** OCR tamamen yerel ve ücretsiz araçlarla yapılır (§6.1). Doğruluk ve hız öncelik. |

### 6.1 Ücretsiz OCR — doğruluk ve hız tasarımı (K10)

Bilgi görselleri aynı şablonda üretilmiş temiz ekran görüntüleri (iki sütunlu tablo). Bu nedenle genel amaçlı bir yapay zekâ yerine **şablona özel bir okuyucu** hem daha doğru hem daha hızlıdır:

1. **Motor:** Tesseract 5 (LSTM, `tur` + `eng`, açık kaynak, ücretsiz). Yerel çalışır; ağ ve API yok.
2. **Ön işleme:** gri tonlama, 2× büyütme, kontrast/eşikleme. Ekran görüntüsünde bu adım hata oranını belirgin düşürür.
3. **Düzen okuma:** Tesseract'ın TSV çıktısından kelime koordinatları alınır. Satırlar y eksenine göre gruplanır. Sol sütun **etiket**, sağ sütun **değer** olarak ayrılır. Etiketler bulanık eşleştirilir ("Yazar", "Yayınevi", "Türü", "ISBN / Barkod", "Sayfa Sayısı", "Ürün Kodu"), yani OCR bir harfi yanlış okusa da etiket tanınır.
4. **Alan bazlı ikinci okuma:** Değer hücresi kırpılıp alan türüne uygun ayarla yeniden okunur. ISBN ve sayfa sayısı yalnızca rakam listesiyle okunur. Bu, rakamlarda en yüksek doğruluğu verir.
5. **Doğrulama:** ISBN-10/13 kontrol hanesi, sayfa sayısı makul aralıkta, ürün kodu biçimi. Yazar, yayınevi ve kitap adı **klasör adıyla çapraz kontrol** edilir (`YAYINEVİ - KİTAP - YAZAR`). İki kaynak uyuşmazsa ürün kontrol ekranına düşer; uyuşuyorsa doğrudan kabul edilir.
6. **Hız:** 2 çekirdekte paralel işçilerle görsel başına < 1 sn hedeflenir. Sonuç dosya özetiyle önbelleğe alınır; aynı görsel ikinci kez okunmaz.
7. **İkinci motor (gerekirse, yine ücretsiz):** Aşama 3'teki gerçek klasör testinde Tesseract'ın doğruluğu yetersiz kalırsa PaddleOCR (açık kaynak, yerel) yedek motor olarak eklenir. Karar ölçüme göre verilir.

## 13. Eski açık sorular (yanıtlandı, bkz. §12)

1. **Fiyat:** Bilgi görselinde fiyat yok. Ürün fiyatı nasıl belirlenecek? (a) sayfa sayısı × sayfa başı fiyat (kademeli, + cilt), (b) yayınevi veya kategori bazlı sabit fiyat, (c) yüklemeden sonra Excel ile toplu fiyat. Sayfa sayısı yoksa ne olacak?
2. **Baskı tipi:** Ürünler renkli ve siyah-beyaz seçenekli mi satılacak (aynı kitabın iki fiyatı)?
3. **"1" dosyası** (düz kapak) ikinci görsel olarak gösterilsin mi?
4. **Teslimat:** Hangi ilçeler (Odunpazarı, Tepebaşı, başka)? Teslimat ücreti, ücretsiz teslimat eşiği, en düşük sipariş tutarı, teslim süresi (aynı gün / ertesi gün)?
5. **Domain:** `eskisehirfotokopi.com` mu, `.com.tr` mi?
6. **Üyeliksiz sipariş** olsun mu, yoksa üyelik zorunlu mu?
7. **Yapay zekâ yedeği** için ücretli API kullanılsın mı, yoksa yalnızca ücretsiz Tesseract ve kontrol ekranı mı?
8. Videoda D: sürücüsündeki **"SORU BANKALARI PDF" (111 GB)** klasörü gösteriliyor. Bu PDF'ler siteye yüklenecek mi (ör. sipariş gelince baskı dosyası panelden indirilsin diye), yoksa yalnızca mağazadaki bilgisayarda mı kalacak?
