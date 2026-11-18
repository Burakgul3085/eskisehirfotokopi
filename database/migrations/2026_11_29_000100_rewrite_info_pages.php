<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bilgi sayfalarının (Hakkımızda, yasal metinler, Teslimat ve İade, SSS) metinleri hukuki dille, ayrıntılı olarak
 * yeniden yazılır. Metinlerde telefon, e-posta ve adres yazılmaz; iletişim için İletişim sayfasına ({iletisim})
 * yönlendirilir. {site_adi}, {belge_gun} ve {siparis_belge_gun} gösterimde ayarlardan doldurulur.
 * Yönetici metni sonradan değiştirdiyse (ilk kurulum metninden farklıysa) bile üzerine yazılır; geri alma yoktur.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (self::bodies() as $key => $body) {
            DB::table('pages')->where('key', $key)->update(['body' => trim($body)."\n", 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Eski metinler geri yüklenmez.
    }

    /** @return array<string, string> */
    private static function bodies(): array
    {
        return [
            'about' => <<<'MD'
{site_adi}, Eskişehir'de öğrencilerin, öğretmenlerin, velilerin ve kurumların kırtasiye, kitap ve baskı ihtiyaçlarını tek noktadan karşılamak amacıyla kurulmuş bir yerel işletmedir. Mağaza deneyimimizi internete taşıyarak siparişlerinizi bulunduğunuz yerden vermenizi, ürünlerinizi ise kendi aracımızla kapınıza ulaştırmayı ilke edindik.

## Ne yapıyoruz?

- **Kitap ve yayın:** Sınavlara hazırlık kitapları, ders kitapları, soru bankaları, okuma ve yardımcı kaynaklar; yayınevlerine göre düzenlenmiş geniş bir katalog.
- **Kırtasiye:** Okul, ofis ve sanat malzemeleri; dönem başı listeleriniz için toplu hazırlık.
- **Fotokopi ve belge baskısı:** PDF veya Word belgenizi internet üzerinden yükler, kâğıt, renk, yüz ve ciltleme seçeneklerini belirlersiniz; belgeniz basılarak siparişinizle birlikte teslim edilir.
- **Kapıda teslimat:** Eskişehir içinde hizmet verdiğimiz ilçelere kendi aracımızla teslimat yapar, ödemeyi teslimat anında nakit veya kartla alırız.

## Çalışma ilkelerimiz

1. **Şeffaf fiyat:** Ürün fiyatları, teslimat ücreti ve varsa ücretsiz teslimat sınırı sipariş onayından önce açıkça gösterilir. Sipariş sırasında gösterilen tutar dışında ek bir bedel talep edilmez.
2. **Güvenli alışveriş:** İnternet üzerinden kart bilgisi alınmaz; ödeme yalnızca teslimat sırasında yapılır. Kişisel verileriniz [KVKK Aydınlatma Metni](/sayfa/kvkk-aydinlatma-metni) kapsamında, yalnızca hizmetin gerektirdiği ölçüde işlenir.
3. **Belgelerinize özen:** Baskı için yüklenen dosyalar yalnızca baskı amacıyla kullanılır, üçüncü kişilerle paylaşılmaz ve belirlenen saklama süresinin sonunda sistemden silinir.
4. **Yerel ve sorumlu hizmet:** Teslimatı kendi ekibimizle yaparız; siparişinizin her aşamasından haberdar olursunuz.

## Bize ulaşın

Soru, öneri ve talepleriniz için [İletişim sayfamızdaki]({iletisim}) formu kullanabilirsiniz. Mesajlarınız kayıt altına alınır ve en kısa sürede yanıtlanır.
MD,

            'sales' => <<<'MD'
## 1. Taraflar

**1.1. Satıcı:** {site_adi} (bundan sonra “SATICI” olarak anılacaktır). SATICI'ya ait ticari unvan ve iletişim bilgileri [İletişim sayfasında]({iletisim}) yer alır.

**1.2. Alıcı:** Siparişi internet sitesi üzerinden veren ve sipariş formunda adı, teslimat adresi ve iletişim bilgileri yer alan gerçek veya tüzel kişi (bundan sonra “ALICI” olarak anılacaktır).

SATICI ve ALICI birlikte “Taraflar” olarak anılır.

## 2. Konu ve kapsam

**2.1.** İşbu sözleşmenin konusu, ALICI'nın SATICI'ya ait internet sitesi üzerinden elektronik ortamda sipariş verdiği ve nitelikleri ile satış fiyatı sipariş özetinde belirtilen mal ve hizmetlerin (kitap, kırtasiye ürünleri, fotokopi ve belge baskısı) satışı ve teslimi ile ilgili olarak 6502 sayılı Tüketicinin Korunması Hakkında Kanun ve Mesafeli Sözleşmeler Yönetmeliği hükümleri uyarınca tarafların hak ve yükümlülüklerinin belirlenmesidir.

**2.2.** Sipariş onay sayfasında ve ALICI'ya gönderilen sipariş bildiriminde yer alan ürün listesi, adet, birim fiyat, teslimat ücreti, toplam tutar, teslimat adresi ve ödeme yöntemi işbu sözleşmenin ayrılmaz parçasıdır.

## 3. Sözleşmenin kurulması

**3.1.** ALICI, siparişi onaylamadan önce malın temel nitelikleri, vergiler dâhil toplam fiyatı, teslimat ücreti, ödeme ve teslimat koşulları ile cayma hakkı konusunda bilgilendirildiğini kabul eder.

**3.2.** Sözleşme, ALICI'nın sipariş formunu onaylamasıyla kurulur. SATICI, siparişin alındığını ALICI'ya elektronik posta ve/veya sipariş takip sayfası aracılığıyla bildirir.

**3.3.** Stokta bulunmayan veya teslimat bölgesi dışında kalan siparişlerde SATICI, durumu ALICI'ya bildirerek siparişi iptal edebilir. Ödeme teslimatta alındığından bu hâlde ALICI'dan herhangi bir bedel talep edilmez.

## 4. Fiyat ve ödeme

**4.1.** Sitede gösterilen fiyatlar Türk lirası cinsindendir ve tüm vergiler dâhildir. Sipariş sırasında geçerli olan fiyat esas alınır; sonradan yapılan fiyat değişiklikleri verilmiş siparişleri etkilemez.

**4.2.** Ödeme, malın tesliminde **nakit veya kredi/banka kartıyla** yapılır. İnternet sitesi üzerinden kart bilgisi alınmaz, çevrim içi ödeme yapılmaz ve ön ödeme talep edilmez.

**4.3.** Teslimat ücreti, teslimat yapılacak bölgeye göre belirlenir ve sipariş özetinde ayrıca gösterilir. Belirlenen tutarın üzerindeki siparişlerde teslimat ücretsiz olabilir.

## 5. Teslimat

**5.1.** Mal, ALICI'nın sipariş formunda belirttiği Eskişehir içindeki adrese, SATICI'nın kendi aracı ve personeli tarafından teslim edilir.

**5.2.** Teslimat, siparişin verilmesinden itibaren yasal **otuz (30) günlük süreyi aşmamak kaydıyla**, sipariş sırasında seçilen teslimat zaman aralığında yapılır. Olağanüstü durumlar (hava koşulları, afet, salgın, resmî kısıtlamalar vb.) nedeniyle oluşabilecek gecikmeler ALICI'ya bildirilir.

**5.3.** ALICI'nın adreste bulunmaması veya teslimatı kabul etmemesi hâlinde SATICI, ALICI ile iletişime geçerek yeni bir teslimat zamanı belirler. İkinci teslimat girişimi de sonuçsuz kalırsa sipariş iptal edilebilir.

**5.4.** ALICI, teslim aldığı malı teslimat sırasında kontrol etmeli; ezik, kırık, eksik veya hatalı ürün görmesi hâlinde durumu teslimat görevlisine bildirmelidir.

## 6. Cayma hakkı

**6.1.** ALICI, malın kendisine veya gösterdiği kişiye teslim edildiği günden itibaren **on dört (14) gün** içinde herhangi bir gerekçe göstermeksizin ve cezai şart ödemeksizin sözleşmeden cayma hakkına sahiptir.

**6.2.** Cayma hakkının kullanıldığına ilişkin bildirimin süresi içinde, açık bir beyanla [İletişim sayfasındaki]({iletisim}) form veya üye hesabındaki mesajlar bölümü aracılığıyla SATICI'ya yöneltilmesi yeterlidir.

**6.3.** ALICI, cayma bildiriminden itibaren on (10) gün içinde malı, SATICI'nın teslim almasına hazır hâlde bulundurur. Mal, SATICI tarafından ALICI'nın adresinden ücretsiz olarak teslim alınır.

**6.4.** SATICI, cayma bildiriminin kendisine ulaştığı tarihten itibaren on dört (14) gün içinde, varsa ALICI'nın ödediği bedeli ödemenin yapıldığı yönteme uygun biçimde iade eder.

**6.5.** Malın olağan kullanımı dışında kullanılması, ambalajının bozulması veya malda meydana gelen değişiklik ve bozulmalardan ALICI sorumludur.

## 7. Cayma hakkının kullanılamayacağı hâller

Mesafeli Sözleşmeler Yönetmeliği'nin 15. maddesi uyarınca aşağıdaki mal ve hizmetlerde cayma hakkı kullanılamaz:

- ALICI'nın istekleri veya kişisel ihtiyaçları doğrultusunda hazırlanan mallar; bu kapsamda **ALICI'nın yüklediği belgeye göre yapılan fotokopi, baskı ve ciltleme işleri**,
- Tesliminden sonra ambalaj, bant, mühür, paket gibi koruyucu unsurları açılmış olan ve maddi ortamda sunulan kitaplar, dijital içerikler ve bilgisayar sarf malzemeleri,
- Abonelik sözleşmesi kapsamında sağlananlar dışındaki gazete, dergi gibi süreli yayınlar,
- İlgili mevzuatta cayma hakkının kullanılamayacağı belirtilen diğer mal ve hizmetler.

## 8. Ayıplı mal

**8.1.** Teslim edilen malın sipariş edilen maldan farklı, eksik ya da ayıplı olması hâlinde ALICI, 6502 sayılı Kanun'un 11. maddesi uyarınca; sözleşmeden dönme, satış bedelinden indirim isteme, ücretsiz onarım isteme veya malın ayıpsız misli ile değiştirilmesini isteme haklarından birini kullanabilir.

**8.2.** Baskı işlerinde, ALICI'nın yüklediği dosyadan kaynaklanan hatalar (düşük çözünürlük, sayfa düzeni, yazım hataları vb.) ayıp sayılmaz. Baskı kalitesi, kesim veya ciltlemede SATICI'dan kaynaklanan kusurlarda iş ücretsiz olarak yeniden yapılır.

**8.3.** Ayıplı mala ilişkin talepler, Kanun'da öngörülen iki (2) yıllık zamanaşımı süresine tabidir.

## 9. Kişisel veriler

ALICI'ya ait kişisel veriler, sözleşmenin kurulması ve ifası amacıyla 6698 sayılı Kişisel Verilerin Korunması Kanunu'na uygun olarak işlenir. Ayrıntılı bilgi [KVKK Aydınlatma Metni'nde](/sayfa/kvkk-aydinlatma-metni) yer alır.

## 10. Uyuşmazlıkların çözümü

**10.1.** İşbu sözleşmeden doğan uyuşmazlıklarda, Ticaret Bakanlığınca her yıl ilan edilen parasal sınırlar dâhilinde ALICI'nın yerleşim yerindeki veya işlemin yapıldığı yerdeki **Tüketici Hakem Heyetleri**, bu sınırları aşan uyuşmazlıklarda **Tüketici Mahkemeleri** yetkilidir.

**10.2.** Mevzuatta dava şartı olarak öngörülen hâllerde dava açılmadan önce arabulucuya başvurulması gerekir.

## 11. Yürürlük

ALICI, siparişini onaylamakla işbu sözleşmenin tüm koşullarını okuduğunu, anladığını ve kabul ettiğini beyan eder. Sözleşme, siparişin onaylandığı tarihte yürürlüğe girer. Sözleşmenin sipariş tarihindeki metni, sipariş kaydıyla birlikte saklanır.
MD,

            'kvkk' => <<<'MD'
> Bu metin, 6698 sayılı Kişisel Verilerin Korunması Kanunu'nun (“KVKK”) 10. maddesi ve Aydınlatma Yükümlülüğünün Yerine Getirilmesinde Uyulacak Usul ve Esaslar Hakkında Tebliğ uyarınca, veri sorumlusu sıfatıyla {site_adi} tarafından hazırlanmıştır.

## 1. Veri sorumlusu

Kişisel verileriniz, veri sorumlusu sıfatıyla **{site_adi}** (“Şirket”) tarafından aşağıda açıklanan kapsamda işlenmektedir. Veri sorumlusuna ait unvan ve iletişim bilgileri [İletişim sayfasında]({iletisim}) yer alır.

## 2. İşlenen kişisel veri kategorileri

| Veri kategorisi | Örnek veriler |
|---|---|
| Kimlik | Ad, soyad |
| İletişim | Telefon numarası, e-posta adresi, teslimat adresi |
| Müşteri işlem | Sipariş, sepet, favori ve sipariş geçmişi bilgileri; mesaj ve talepleriniz |
| İşlem güvenliği | Parola (geri döndürülemez biçimde şifrelenmiş), IP adresi, oturum ve erişim kayıtları |
| Belge içeriği | Baskı için yüklediğiniz dosyalar ve bu dosyalarda yer alabilecek bilgiler |
| Pazarlama | E-bülten için verdiğiniz e-posta adresi ve onay kaydı |

Özel nitelikli kişisel veri talep edilmez. Baskı için yüklenen belgelerde özel nitelikli veri veya üçüncü kişilere ait veri bulunması hâlinde, bu verileri paylaşma yetkisine sahip olduğunuzu kabul etmiş sayılırsınız.

## 3. İşleme amaçları ve hukuki sebepler

| Amaç | Hukuki sebep (KVKK m.5) |
|---|---|
| Siparişin alınması, hazırlanması, teslimatı ve tahsilatı | Sözleşmenin kurulması ve ifası (m.5/2-c) |
| Üyelik hesabının oluşturulması ve yönetimi | Sözleşmenin kurulması ve ifası (m.5/2-c) |
| Belge baskısı hizmetinin sunulması | Sözleşmenin kurulması ve ifası (m.5/2-c) |
| Fatura, muhasebe ve yasal saklama yükümlülükleri | Hukuki yükümlülüğün yerine getirilmesi (m.5/2-ç) |
| Mesaj ve taleplerinizin yanıtlanması, şikâyet yönetimi | Meşru menfaat (m.5/2-f) |
| Bilgi ve işlem güvenliğinin sağlanması, kötüye kullanımın önlenmesi | Hukuki yükümlülük ve meşru menfaat (m.5/2-ç, f) |
| Hukuki uyuşmazlıklarda hakların korunması | Bir hakkın tesisi, kullanılması veya korunması (m.5/2-e) |
| E-bülten ve kampanya duyurularının gönderilmesi | Açık rıza (m.5/1) ve 6563 sayılı Kanun kapsamında onay |

## 4. Kişisel verilerin toplanma yöntemi

Kişisel verileriniz; üyelik, sipariş, belge yükleme, iletişim ve e-bülten formları aracılığıyla elektronik ortamda, ayrıca teslimat sırasında sözlü olarak doğrudan sizden; IP adresi ve oturum kayıtları ise siteyi kullanımınız sırasında otomatik yollarla toplanır.

## 5. Kişisel verilerin aktarılması

Kişisel verileriniz, yukarıdaki amaçlarla sınırlı olmak üzere ve KVKK'nın 8. ve 9. maddelerine uygun olarak;

- İnternet sitesinin barındırıldığı sunucu ve altyapı hizmeti sağlayıcılarına,
- E-posta bildirimlerinin gönderilmesini sağlayan hizmet sağlayıcılarına,
- Mali müşavirlik ve hukuki danışmanlık hizmeti alınan kişilere,
- Talep hâlinde yetkili kamu kurum ve kuruluşları ile adli makamlara

aktarılabilir. Kullanılan hizmet sağlayıcılarının sunucularının yurt dışında bulunması hâlinde aktarım, KVKK'nın 9. maddesinde öngörülen uygun güvenceler (standart sözleşme vb.) sağlanarak gerçekleştirilir. Kişisel verileriniz satılmaz ve reklam amacıyla üçüncü kişilerle paylaşılmaz.

## 6. Saklama süreleri

| Veri | Saklama süresi |
|---|---|
| Sipariş ve fatura kayıtları | İlgili vergi ve ticaret mevzuatında öngörülen süre (en az 10 yıl) |
| Üyelik bilgileri | Üyelik süresince ve sona ermesinden itibaren genel zamanaşımı süresince |
| Siparişe dönüşmeyen baskı dosyaları | Yüklemeden itibaren {belge_gun} gün |
| Siparişe eklenen baskı dosyaları | Siparişten itibaren {siparis_belge_gun} gün |
| Mesaj ve talepler | Talebin sonuçlandırılmasından itibaren 2 yıl |
| E-bülten onay kaydı | Onayın geri alınmasından itibaren 3 yıl |
| Erişim ve güvenlik kayıtları | İlgili mevzuatta öngörülen süre |

Süresi dolan veriler, Kişisel Verilerin Silinmesi, Yok Edilmesi veya Anonim Hâle Getirilmesi Hakkında Yönetmelik uyarınca silinir, yok edilir veya anonim hâle getirilir. Baskı dosyaları süre sonunda otomatik olarak silinir.

## 7. İlgili kişinin hakları

KVKK'nın 11. maddesi uyarınca veri sorumlusuna başvurarak;

1. Kişisel verilerinizin işlenip işlenmediğini öğrenme,
2. İşlenmişse buna ilişkin bilgi talep etme,
3. İşlenme amacını ve amacına uygun kullanılıp kullanılmadığını öğrenme,
4. Yurt içinde veya yurt dışında aktarıldığı üçüncü kişileri bilme,
5. Eksik veya yanlış işlenmişse düzeltilmesini isteme,
6. KVKK'nın 7. maddesindeki şartlar çerçevesinde silinmesini veya yok edilmesini isteme,
7. Düzeltme, silme ve yok etme işlemlerinin verilerin aktarıldığı üçüncü kişilere bildirilmesini isteme,
8. İşlenen verilerin münhasıran otomatik sistemler vasıtasıyla analiz edilmesi suretiyle aleyhinize bir sonucun ortaya çıkmasına itiraz etme,
9. Kanuna aykırı işleme sebebiyle zarara uğramanız hâlinde zararın giderilmesini talep etme

haklarına sahipsiniz.

## 8. Başvuru yöntemi

Haklarınıza ilişkin taleplerinizi, Veri Sorumlusuna Başvuru Usul ve Esasları Hakkında Tebliğ'e uygun olarak; kimliğinizi ve talebinizi açıkça belirterek yazılı olarak veya [İletişim sayfasında]({iletisim}) belirtilen kanallar aracılığıyla iletebilirsiniz. Üye iseniz başvurunuzu sitede kayıtlı e-posta adresinizden yapmanız, kimlik doğrulamasını kolaylaştırır.

Başvurular, talebin niteliğine göre en geç **otuz (30) gün** içinde ücretsiz olarak sonuçlandırılır. İşlemin ayrıca bir maliyet gerektirmesi hâlinde Kişisel Verileri Koruma Kurulunca belirlenen tarifedeki ücret alınabilir. Başvurunuzun reddedilmesi, verilen cevabı yetersiz bulmanız veya süresinde cevap verilmemesi hâllerinde Kişisel Verileri Koruma Kurulu'na şikâyette bulunma hakkınız saklıdır.
MD,

            'membership' => <<<'MD'
## 1. Taraflar ve konu

İşbu Üyelik Koşulları (“Koşullar”), {site_adi} (“Site”) ile Site'ye üye olan kullanıcı (“Üye”) arasında, Site'nin kullanımına ilişkin hak ve yükümlülükleri düzenler. Üye olmak isteyen kişi, kayıt formunu doldurup onaylamakla işbu Koşulları okuduğunu ve kabul ettiğini beyan eder.

## 2. Üyelik şartları

**2.1.** Üyelik için on sekiz (18) yaşını doldurmuş ve fiil ehliyetine sahip olmak gerekir. On sekiz yaşından küçükler Site'yi veli veya vasilerinin gözetiminde kullanabilir.

**2.2.** Üye, kayıt sırasında verdiği bilgilerin doğru, güncel ve kendisine ait olduğunu kabul eder. Bilgilerin yanlış veya eksik olmasından doğacak sonuçlardan Üye sorumludur.

**2.3.** Bir kişi yalnızca bir üyelik hesabı oluşturabilir. Üyelik hesabı devredilemez.

## 3. Hesap güvenliği

**3.1.** Parolanın gizliliğinden ve hesabın yetkisiz kişilerce kullanılmasını önlemekten Üye sorumludur. Parolalar Site'de geri döndürülemez biçimde şifrelenerek saklanır; Site çalışanları dâhil hiç kimse parolanızı göremez.

**3.2.** Hesabın yetkisiz kullanıldığından şüphelenen Üye, parolasını derhâl değiştirmeli ve durumu [İletişim sayfası]({iletisim}) aracılığıyla bildirmelidir.

**3.3.** Ortak kullanılan cihazlarda “Beni hatırla” seçeneğinin kullanılmaması ve işlem sonrasında oturumun kapatılması önerilir.

## 4. Üyenin yükümlülükleri

Üye, Site'yi kullanırken;

- Yürürlükteki mevzuata, genel ahlaka ve işbu Koşullara uygun davranmayı,
- Başkalarına ait kişisel verileri, fikrî mülkiyet haklarını ve ticari sırları ihlal eden içerik yüklememeyi,
- Baskı için yüklediği belgeler üzerinde gerekli hak ve izinlere sahip olmayı; 5846 sayılı Fikir ve Sanat Eserleri Kanunu'na aykırı çoğaltma talebinde bulunmamayı,
- Site'nin işleyişini bozacak, güvenliğini tehlikeye atacak yazılım, otomatik araç veya yöntem kullanmamayı,
- Gerçeğe aykırı sipariş vermemeyi ve teslimat görevlilerine karşı saygılı davranmayı

kabul ve taahhüt eder.

## 5. Fikrî mülkiyet

Site'nin tasarımı, yazılımı, logosu, metinleri ve görselleri {site_adi}'na veya lisans verenlerine aittir. Bunlar izinsiz kopyalanamaz, çoğaltılamaz ve ticari amaçla kullanılamaz. Ürün görselleri ve tanıtım bilgileri ilgili yayınevlerine ve üreticilere aittir.

## 6. Baskı için yüklenen belgeler

**6.1.** Üye, baskı için yüklediği belgenin içeriğinden ve bu belgenin çoğaltılmasının hukuka uygunluğundan bizzat sorumludur. Telif hakkıyla korunan eserlerin hak sahibinin izni olmaksızın çoğaltılması talepleri reddedilebilir.

**6.2.** Yüklenen belgeler yalnızca baskı amacıyla kullanılır; siparişe dönüşmeyen belgeler {belge_gun} gün, siparişe eklenen belgeler {siparis_belge_gun} gün sonra otomatik olarak silinir.

## 7. Hizmetin askıya alınması ve üyeliğin sona ermesi

**7.1.** Üye, dilediği zaman [İletişim sayfası]({iletisim}) aracılığıyla üyeliğinin sona erdirilmesini talep edebilir. Bu durumda yasal saklama yükümlülüğü bulunan sipariş kayıtları dışındaki üyelik bilgileri silinir veya anonim hâle getirilir.

**7.2.** İşbu Koşulların ihlali, gerçeğe aykırı bilgi verilmesi veya Site'nin kötüye kullanılması hâlinde {site_adi}, Üye'ye bildirimde bulunarak üyeliği askıya alma veya sona erdirme hakkına sahiptir.

## 8. Sorumluluğun sınırlandırılması

{site_adi}, Site'nin kesintisiz ve hatasız çalışması için gerekli özeni gösterir. Bakım çalışmaları, internet altyapısından kaynaklanan sorunlar veya mücbir sebepler nedeniyle oluşabilecek kesintilerden doğan dolaylı zararlardan, kanunen sorumlu tutulamayacağı ölçüde sorumlu değildir. Bu hüküm, tüketicinin kanundan doğan haklarını sınırlamaz.

## 9. Elektronik iletiler

Sipariş ve hesap işlemlerine ilişkin bilgilendirme iletileri, hizmetin gereği olarak Üye'nin kayıtlı e-posta adresine gönderilir. Kampanya ve duyuru içeren ticari elektronik iletiler yalnızca Üye'nin onayıyla gönderilir; Üye bu onayı her iletideki bağlantı aracılığıyla dilediği zaman geri alabilir.

## 10. Değişiklikler

{site_adi}, işbu Koşulları mevzuattaki değişiklikler veya hizmetin gereklilikleri doğrultusunda güncelleyebilir. Güncel metin bu sayfada yayımlandığı tarihte yürürlüğe girer; verilmiş siparişler sipariş tarihindeki koşullara tabidir.

## 11. Uygulanacak hukuk ve yetki

İşbu Koşullar Türkiye Cumhuriyeti hukukuna tabidir. Uyuşmazlıklarda tüketici işlemleri bakımından Tüketici Hakem Heyetleri ve Tüketici Mahkemeleri, diğer hâllerde Eskişehir Mahkemeleri ve İcra Daireleri yetkilidir.
MD,

            'cookies' => <<<'MD'
## 1. Amaç ve kapsam

İşbu Çerez Politikası, {site_adi} internet sitesinde kullanılan çerezler ve benzeri teknolojiler hakkında 6698 sayılı Kişisel Verilerin Korunması Kanunu ve 5809 sayılı Elektronik Haberleşme Kanunu kapsamında sizi bilgilendirmek amacıyla hazırlanmıştır.

## 2. Çerez nedir?

Çerezler, bir internet sitesini ziyaret ettiğinizde tarayıcınız aracılığıyla cihazınıza kaydedilen küçük metin dosyalarıdır. Benzer amaçla tarayıcının yerel depolama alanı (localStorage) da kullanılabilir. Bu teknolojiler sitenin çalışmasını, oturumunuzun sürdürülmesini ve tercihlerinizin hatırlanmasını sağlar.

## 3. Kullandığımız çerezler

Sitemizde **yalnızca sitenin çalışması için zorunlu olan** çerezler kullanılır. Reklam, profil oluşturma veya ziyaretçi izleme amacıyla çerez kullanılmaz; sitemizde üçüncü taraf analiz veya reklam aracı bulunmaz.

| Ad | Tür | Amaç | Süre |
|---|---|---|---|
| …-session (oturum çerezi) | Zorunlu, birinci taraf | Oturumunuzu, sepetinizi ve giriş durumunuzu sürdürür | Tarayıcı kapanınca veya 2 saat işlem yapılmayınca |
| XSRF-TOKEN | Zorunlu, birinci taraf | Formların sizin adınıza başkalarınca gönderilmesini önleyen güvenlik çerezi | 2 saat |
| remember_web_… | İşlevsel, birinci taraf | Yalnızca “Beni hatırla” seçildiğinde oturumunuzu açık tutar | En fazla 400 gün veya çıkış yapana kadar |
| cookie-notice (yerel depolama) | Tercih | Çerez bilgilendirmesini kapattığınızı hatırlar | Tarayıcı verilerini silene kadar |

Zorunlu çerezler, KVKK'nın 5. maddesinin 2. fıkrası uyarınca sözleşmenin ifası ve meşru menfaat hukuki sebeplerine dayanılarak kullanılır ve açık rızanıza bağlı değildir.

## 4. Üçüncü taraf içerikler

Sitenin alt bölümünde mağaza konumunu göstermek amacıyla Google Haritalar içeriği yer alabilir. Bu içerik Google LLC tarafından sunulur ve Google kendi çerezlerini kendi gizlilik politikası çerçevesinde kullanabilir. Bu çerezler üzerinde {site_adi}'nın denetimi bulunmaz. Ayrıntılı bilgi için Google'ın gizlilik politikasını inceleyebilirsiniz.

## 5. Çerezlerin yönetimi

Çerezleri tarayıcınızın ayarlarından görüntüleyebilir, silebilir veya engelleyebilirsiniz:

- **Google Chrome:** Ayarlar › Gizlilik ve güvenlik › Üçüncü taraf çerezleri / Site verileri
- **Mozilla Firefox:** Ayarlar › Gizlilik ve Güvenlik › Çerezler ve site verileri
- **Safari:** Ayarlar › Gizlilik › Web sitesi verilerini yönet
- **Microsoft Edge:** Ayarlar › Çerezler ve site izinleri

Zorunlu çerezlerin engellenmesi hâlinde oturum açma, sepet ve sipariş işlemleri çalışmayabilir.

## 6. Haklarınız

Çerezler aracılığıyla işlenen kişisel verilerinize ilişkin KVKK'nın 11. maddesindeki haklarınız ve başvuru yöntemi hakkında [KVKK Aydınlatma Metni'ni](/sayfa/kvkk-aydinlatma-metni) inceleyebilirsiniz.

## 7. Değişiklikler

İşbu Çerez Politikası, kullanılan teknolojilerdeki veya mevzuattaki değişikliklere göre güncellenebilir. Güncel metin bu sayfada yayımlandığı tarihte yürürlüğe girer.
MD,

            'delivery' => <<<'MD'
## 1. Teslimat bölgesi

Siparişleriniz yalnızca **Eskişehir il sınırları içinde**, teslimat yaptığımız ilçelerdeki adreslere teslim edilir. Teslimat yapılan ilçeler, ilçeye göre teslimat ücreti ve varsa ücretsiz teslimat sınırı bu sayfanın üstündeki kartta güncel olarak gösterilir. Teslimat bölgesi dışındaki adresler sipariş sırasında seçilemez.

## 2. Teslimat süresi ve saatleri

- Siparişiniz, sipariş sırasında seçtiğiniz **teslimat zaman aralığında** adresinize getirilir.
- Seçtiğiniz aralık için kontenjan dolmuşsa veya sipariş saati geçmişse bir sonraki uygun aralık önerilir.
- Belge baskısı içeren siparişlerde baskı ve ciltleme süresi teslimat planına dâhil edilir.
- Teslimat süresi hiçbir durumda yasal **30 günlük** süreyi aşmaz. Hava koşulları, afet veya resmî kısıtlamalar gibi olağanüstü durumlarda yaşanabilecek gecikmeler size bildirilir.

## 3. Teslimat nasıl yapılır?

1. **Sipariş alınır:** Siparişiniz kaydedilir; sipariş numaranız ekranda ve e-posta bildiriminde yer alır.
2. **Onaylanır ve hazırlanır:** Ürünleriniz paketlenir, belgeleriniz basılır.
3. **Yola çıkar:** Siparişiniz kendi aracımızla ve kendi ekibimiz tarafından adresinize getirilir.
4. **Teslim edilir:** Ürünlerinizi kontrol ederek teslim alır, ödemenizi yaparsınız.

Siparişinizin hangi aşamada olduğunu [Sipariş takibi](/siparis-takip) sayfasından veya üye hesabınızdaki **Siparişlerim** bölümünden izleyebilirsiniz.

## 4. Ödeme

- Ödeme yalnızca **teslimat sırasında, kapıda** yapılır; nakit veya kredi/banka kartı kabul edilir.
- İnternet sitemizde kart bilgisi istenmez, çevrim içi ödeme ve ön ödeme alınmaz.
- Ödeyeceğiniz tutar, sipariş özetinde gösterilen tutardır; teslimat sırasında ek ücret talep edilmez.

## 5. Teslim alırken dikkat edilecekler

- Paketi teslimat görevlisinin yanında kontrol etmenizi öneririz.
- Hasarlı, eksik veya yanlış ürün görürseniz durumu görevliye hemen bildirin; ürünü teslim almayabilir veya yalnızca sorunsuz ürünleri teslim alabilirsiniz.
- Adreste bulunamayacaksanız, siparişinizi teslim alacak bir kişiyi sipariş notunda belirtebilirsiniz.
- Adreste kimseye ulaşılamazsa sizinle iletişime geçilerek yeni bir teslimat zamanı belirlenir.

## 6. Sipariş iptali

Siparişiniz **yola çıkmadan önce** [İletişim sayfasındaki]({iletisim}) form veya üye hesabınızdaki mesajlar bölümü aracılığıyla iptal talebinde bulunabilirsiniz. Ödeme teslimatta alındığı için iptal edilen siparişlerde sizden herhangi bir bedel talep edilmez. Baskısına başlanmış belge siparişleri, kişiye özel üretim niteliğinde olduğundan iptal edilemeyebilir.

## 7. Cayma hakkı ve iade

- Ürünü teslim aldığınız günden itibaren **14 gün** içinde gerekçe göstermeksizin cayma hakkınızı kullanabilirsiniz.
- Cayma talebinizi [İletişim sayfası]({iletisim}) veya üye hesabınızdaki mesajlar aracılığıyla bildirmeniz yeterlidir.
- Talebinizi bildirdikten sonra ürün, **10 gün** içinde ücretsiz olarak adresinizden teslim alınır.
- Ürünün kullanılmamış, yeniden satılabilir durumda ve mümkünse orijinal ambalajında olması gerekir.
- Ödediğiniz bedel, cayma bildiriminizin bize ulaşmasından itibaren **14 gün** içinde ödeme yönteminize uygun şekilde iade edilir; kartla yapılan ödemelerde iade aynı karta yapılır.

## 8. İade edilemeyen ürünler

Mesafeli Sözleşmeler Yönetmeliği'nin 15. maddesi uyarınca aşağıdaki ürünlerde cayma hakkı kullanılamaz:

- Yüklediğiniz belgeye göre hazırlanan **fotokopi, baskı ve ciltleme işleri** (kişiye özel üretim),
- Koruyucu ambalajı, bandı veya jelatini açılmış kitaplar ve dijital içerikler,
- Gazete ve dergi gibi süreli yayınlar.

## 9. Hasarlı, eksik veya hatalı ürün

Size ulaşan ürün sipariş ettiğinizden farklı, eksik ya da kusurlu ise bize bildirmeniz yeterlidir. Ürün ücretsiz olarak değiştirilir, eksik ürün tamamlanır veya bedeli iade edilir. Belge baskısında bizden kaynaklanan kalite, kesim veya ciltleme hatalarında baskı ücretsiz olarak yenilenir; yüklenen dosyadan kaynaklanan hatalar (düşük çözünürlük, yanlış sayfa düzeni vb.) bu kapsamda değildir. Ayıplı ürünlere ilişkin yasal haklarınız 6502 sayılı Tüketicinin Korunması Hakkında Kanun kapsamında saklıdır.
MD,

            'faq' => <<<'MD'
# Sipariş

## Sipariş vermek için üye olmam gerekiyor mu?
Hayır. Üye olmadan da sipariş verebilirsiniz. Üye olursanız adreslerinizi kaydedebilir, siparişlerinizi tek yerden izleyebilir, geçmiş siparişlerinizi tek tıkla tekrarlayabilir ve favori ürünlerinizi saklayabilirsiniz.

## Siparişimi nasıl takip ederim?
[Sipariş takibi](/siparis-takip) sayfasına sipariş numaranızı ve siparişte kullandığınız bilgileri girerek siparişinizin durumunu görebilirsiniz. Üyeyseniz **Hesabım › Siparişlerim** bölümünden de izleyebilirsiniz. Siparişinizin durumu değiştikçe e-posta ile bilgilendirilirsiniz.

## Siparişim hangi aşamalardan geçer?
Siparişiniz sırasıyla **Alındı**, **Onaylandı**, **Hazırlanıyor**, **Yola çıktı** ve **Teslim edildi** aşamalarından geçer. Her aşama sipariş takip sayfasında tarihiyle birlikte gösterilir.

## Siparişimi değiştirebilir veya iptal edebilir miyim?
Siparişiniz yola çıkmadan önce [İletişim sayfası]({iletisim}) veya hesabınızdaki mesajlar bölümü üzerinden değişiklik ya da iptal talebinde bulunabilirsiniz. Ödeme teslimatta alındığından iptalde herhangi bir ücret ödemezsiniz. Baskısına başlanmış belgeler iptal edilemeyebilir.

## Aradığım ürünü bulamıyorum, ne yapmalıyım?
Sayfanın üstündeki arama kutusuna ürün adı, yazar, yayınevi veya ISBN yazarak arama yapabilirsiniz. Ürün listede yoksa [İletişim sayfasından]({iletisim}) bize yazın; tedarik edebileceğimiz ürünler için size dönüş yaparız.

## En düşük sipariş tutarı var mı?
Teslimat bölgesine göre en düşük sipariş tutarı uygulanabilir. Güncel tutar [Teslimat ve İade](/sayfa/teslimat-ve-iade) sayfasında ve sepetinizde gösterilir.

# Teslimat

## Hangi bölgelere teslimat yapıyorsunuz?
Yalnızca Eskişehir içinde, teslimat yaptığımız ilçelerdeki adreslere teslimat yapıyoruz. Güncel ilçe listesi ve ücretler [Teslimat ve İade](/sayfa/teslimat-ve-iade) sayfasında yer alır. Kargo ile şehir dışına gönderim yapılmamaktadır.

## Siparişim ne zaman gelir?
Siparişiniz, sipariş sırasında seçtiğiniz teslimat zaman aralığında adresinize getirilir. Teslimat süresi hiçbir durumda yasal 30 günlük süreyi aşmaz.

## Teslimat ücreti ne kadar?
Teslimat ücreti ilçeye göre belirlenir ve sipariş özetinde ayrıca gösterilir. Belirlenen tutarın üzerindeki siparişlerde teslimat ücretsiz olabilir.

## Teslimatı kim yapıyor?
Teslimatları kargo firması aracılığıyla değil, kendi aracımız ve kendi ekibimizle yapıyoruz. Bu sayede siparişiniz özenle ve zamanında elinize ulaşır.

## Adreste olmazsam ne olur?
Sipariş notuna siparişi teslim alacak bir kişiyi yazabilirsiniz. Adreste kimseye ulaşılamazsa sizinle iletişime geçilir ve yeni bir teslimat zamanı belirlenir.

# Ödeme

## Hangi ödeme yöntemlerini kabul ediyorsunuz?
Ödemeyi teslimat sırasında kapıda **nakit** veya **kredi/banka kartı** ile yapabilirsiniz.

## İnternetten kartla ödeme yapabilir miyim?
Hayır. Güvenliğiniz için internet sitemizde kart bilgisi istenmez ve çevrim içi ödeme alınmaz. Ödemenin tamamı teslimatta yapılır.

## Kapıda ek ücret ödeyecek miyim?
Hayır. Teslimatta ödeyeceğiniz tutar, sipariş özetinde gördüğünüz toplam tutardır; kapıda ödeme için ek ücret alınmaz.

## Fatura alabilir miyim?
Evet. Siparişinize ilişkin fatura, yasal mevzuata uygun olarak düzenlenir. Kurumsal fatura talebinizi sipariş notunda belirtebilirsiniz.

# Belge baskısı

## Hangi dosya türlerini yükleyebilirim?
PDF, Word (DOC, DOCX), Excel (XLS, XLSX), PowerPoint (PPT, PPTX) ve OpenDocument (ODT, ODS, ODP) belgelerinizi yükleyebilirsiniz. En iyi sonuç için belgenizi PDF olarak yüklemenizi öneririz; böylece yazı tipi ve sayfa düzeni olduğu gibi korunur.

## Baskı fiyatı nasıl hesaplanır?
Belgeniz yüklendiğinde sayfa sayısı otomatik olarak hesaplanır. Seçtiğiniz kâğıt boyutu, renk, tek/çift yüz, kopya sayısı ve ciltleme seçeneklerine göre fiyat anında gösterilir; sepete eklemeden önce toplam tutarı görürsünüz.

## Belgemi basmadan önce görebilir miyim?
Evet. Yükleme sonrasında belgenizin sayfa önizlemesini görebilir, seçeneklerinizi buna göre düzenleyebilirsiniz.

## Yüklediğim belgeler güvende mi?
Belgeleriniz yalnızca baskı amacıyla kullanılır ve üçüncü kişilerle paylaşılmaz. Siparişe eklenmeyen belgeler {belge_gun} gün, siparişe eklenen belgeler {siparis_belge_gun} gün sonra sistemden otomatik olarak silinir.

## Basılan belgemde hata olursa ne olur?
Baskı kalitesi, kesim veya ciltlemeden kaynaklanan hatalarda belgeniz ücretsiz olarak yeniden basılır. Yüklenen dosyadaki içerik veya düzen hataları bu kapsama girmez; bu nedenle göndermeden önce önizlemeyi kontrol etmenizi öneririz.

## Her belgeyi basıyor musunuz?
Telif hakkıyla korunan eserlerin hak sahibinin izni olmadan çoğaltılması 5846 sayılı Kanun'a aykırıdır. Bu nitelikteki baskı talepleri reddedilebilir.

# İade ve cayma

## Aldığım ürünü iade edebilir miyim?
Evet. Ürünü teslim aldığınız günden itibaren 14 gün içinde gerekçe göstermeden cayma hakkınızı kullanabilirsiniz. Ürünün kullanılmamış ve yeniden satılabilir durumda olması gerekir. Ayrıntılar [Teslimat ve İade](/sayfa/teslimat-ve-iade) sayfasında yer alır.

## İade için ürünü nasıl göndereceğim?
Ürünü göndermenize gerek yoktur. Cayma talebinizi bize ilettikten sonra ürün 10 gün içinde ücretsiz olarak adresinizden teslim alınır.

## Ödediğim para ne zaman iade edilir?
Bedel, cayma bildiriminizin bize ulaşmasından itibaren en geç 14 gün içinde iade edilir. Kartla ödemelerde iade aynı karta yapılır; kartınıza yansıma süresi bankanıza bağlıdır.

## Hangi ürünler iade edilemez?
Yüklediğiniz belgeye göre hazırlanan fotokopi, baskı ve ciltleme işleri kişiye özel üretim olduğundan iade edilemez. Koruyucu ambalajı veya jelatini açılmış kitaplar ve dijital içerikler ile süreli yayınlarda da cayma hakkı kullanılamaz.

## Ürün hasarlı veya eksik gelirse ne yapmalıyım?
Durumu teslimat sırasında görevliye ya da teslimattan sonra [İletişim sayfası]({iletisim}) üzerinden bize bildirin. Ürün ücretsiz olarak değiştirilir, eksik ürün tamamlanır veya bedeli iade edilir.

# Üyelik ve gizlilik

## Parolamı unuttum, ne yapmalıyım?
Giriş sayfasındaki **Parolamı unuttum** bağlantısına tıklayıp kayıtlı e-posta adresinizi yazın. Size gönderilen bağlantı ile yeni parolanızı belirleyebilirsiniz.

## Bilgilerimi nasıl güncellerim?
**Hesabım › Bilgilerim ve parola** bölümünden ad, iletişim bilgileri ve parolanızı; **Adreslerim** bölümünden teslimat adreslerinizi güncelleyebilirsiniz.

## Üyeliğimi nasıl silerim?
Üyeliğinizin sona erdirilmesini [İletişim sayfası]({iletisim}) üzerinden talep edebilirsiniz. Yasal olarak saklanması zorunlu olan sipariş kayıtları dışındaki bilgileriniz silinir veya anonim hâle getirilir.

## Kişisel verilerim nasıl kullanılıyor?
Verileriniz yalnızca siparişinizin alınması, teslimatı ve yasal yükümlülüklerin yerine getirilmesi amacıyla işlenir; satılmaz ve reklam amacıyla paylaşılmaz. Ayrıntılar [KVKK Aydınlatma Metni'nde](/sayfa/kvkk-aydinlatma-metni) yer alır.

## E-bültenden nasıl ayrılırım?
Gönderdiğimiz her e-bültenin altındaki ayrılma bağlantısına tıklayarak aboneliğinizi tek adımda sonlandırabilirsiniz.
MD,
        ];
    }
};
