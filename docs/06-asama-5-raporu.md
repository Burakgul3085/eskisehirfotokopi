# Aşama 5 raporu — sepet, sipariş, teslimat bölgeleri

## Yapılanlar

**Sepet (`App\Domain\Cart`)**
- Oturumda tutulur; misafir ve üye için aynı sepet. Oturumda yalnız ürün kimliği, baskı tipi ve adet saklanır. Ad ve fiyat her gösterimde güncel kayıttan okunur.
- Yayından kalkan ya da fiyatı silinen ürün sepetten kendiliğinden düşer.
- Sınırlar: en fazla 60 satır, satır başına en fazla 50 adet.
- `Cart::resolver('print', …)` ile Aşama 6'daki baskı işleri aynı sepete bağlanacak.
- Rotalar: `/sepet` (ekle, güncelle, çıkar). Ürün sayfasındaki "Sepete ekle" düğmesi artık çalışıyor.

**Sipariş (`/siparis`, tek sayfa)**
- Sayfa sırası: iletişim → adres → teslimat saati → kapıda ödeme yöntemi (nakit ya da kart).
- İlçe ve mahalle seçilince teslimat ücreti, ücretsiz teslimata kalan tutar, en düşük sipariş uyarısı ve tahmini teslim günü anında görünür (`checkout` CSP bileşeni).
- Tutarlar sunucuda `PlaceOrder` içinde yeniden hesaplanır; tarayıcıdan gelen tutar kullanılmaz.
- Sunucuda denetlenenler: kapalı ilçe, listede olmayan veya kapalı mahalle, en düşük tutar, ödeme yöntemi ve saat aralığı.
- Misafir sipariş verebilir (K8). Misafirden e-posta istenir; durum bildirimleri Aşama 7'de bu adrese gider.
- Üye kayıtlı adresini tek tıkla doldurabilir ve yeni adresi hesabına kaydedebilir.
- Sipariş numarası `ES` + yıl + 6 hane biçimindedir. Sipariş verilince satış sayacı artar; iptalde geri alınır.
- Ayarlardan "sipariş alımı kapalı" yapılınca sepet çalışır, sipariş onaylanamaz.
- Sipariş gönderimi hız sınırlıdır: dakikada 6, saatte 30.

**Sipariş takibi**
- `/siparis-takip`: sipariş numarası ve telefonla sorgulanır.
- `/siparis/{no}/{gizli-anahtar}`: e-postadaki bağlantı. İlerleme çubuğu (Yeni → Onaylandı → Hazırlanıyor → Yolda → Teslim edildi), tahmini teslimat ve durum geçmişi gösterilir.

**Hesabım**
- Siparişlerim, sipariş ayrıntısı ve "Tekrar sipariş ver" (ürünler güncel fiyatla sepete eklenir).
- Adreslerim: en fazla 10 adres, varsayılan adres seçimi; yalnız teslimat yapılan ilçeler seçilebilir.
- Favorilerim: ürün sayfasındaki kalp düğmesi sayfa yenilenmeden çalışır.

**Yönetim**
- *Siparişler:* durum sekmeleri (sayılarıyla), arama (no, ad, telefon, e-posta), ilçe, ödeme ve tarih filtreleri.
- *Sipariş ayrıntısı:*
  - Durum değiştirme; not müşteriye görünür, iptalde neden yazmak zorunludur.
  - "Müşteriye e-postayla bildir" seçeneği.
  - Müşteri bilgileri ve tek tıkla arama/WhatsApp; müşterinin görmediği iç not.
  - Yazdırılabilir teslimat fişi.
- *Teslimat listesi:* seçilen günün açık siparişleri ilçeye göre gruplu, tahsil edilecek toplam ile; tek tuşla tümü yazdırılır.
- *Teslimat bölgeleri (K6):*
  - İlçe açma/kapama, ücret ve ek gün.
  - İlçeye özel ücretsiz teslimat eşiği ve en düşük tutar; boşsa genel ayar geçerlidir.
  - İsteğe bağlı mahalle listesi; satır biçimi `Mahalle|60,00` ya da `Mahalle|kapalı`.
- *Ayarlar › Teslimat ve sipariş:*
  - Sipariş alımı açık/kapalı ve kapalı mesajı.
  - Ücretsiz teslimat eşiği ve en düşük sipariş tutarı.
  - Aynı gün teslimat ve kesim saati, teslimat yapılmayan günler, saat aralıkları.
  - Ödeme yöntemleri ve teslimat bilgi metni.
- İşlem günlüğüne sipariş ve bölge kayıtları eklendi.

## Düzeltilen eski hata
Ayar formlarındaki radyo düğmeleri seçili gelmiyordu: PHP `'1'` dizi anahtarını tamsayıya çevirdiği için katı karşılaştırma tutmuyordu. Hata Genel › Site durumu seçeneğinde de vardı ve düzeltildi.

## Sonraki aşamaya bırakılanlar
- `OrderPlaced` ve `OrderStatusChanged` olayları atılıyor, ancak e-posta dinleyicileri Aşama 7'de yazılacak. Şu an e-posta gitmiyor.
- Sipariş sayfasındaki "satış koşulları" metni Aşama 8'de CMS sayfasına bağlanacak.

## Testler
`tests/Feature/OrderTest.php` (12 test) şunları kapsıyor:
- Sepet işlemleri.
- Sunucu tarafı tutar hesabı.
- Ücretsiz teslimat ve mahalle ücreti.
- En düşük tutar, kapalı ilçe ve kapalı sipariş alımı.
- Takip.
- Üye adresi ve yeniden sipariş.
- Adres ve favoriler.
- Yönetici durum geçişleri ve iptalde satış sayacının geri alınması.
- Teslimat listesi.
- Bölge ve teslimat ayarları.
- Tahmini teslim günü hesabı.

Toplam 65 test geçiyor. Akış tarayıcıda uçtan uca denendi: sepet → sipariş → tamamlandı → takip → yönetim ekranları.
