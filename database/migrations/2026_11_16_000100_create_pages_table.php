<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kurumsal ve yasal sayfalar (Yönetim › Sayfalar). Sistem sayfaları (key dolu) silinemez; ödeme, üyelik ve iletişim
 * formları bunlara bağlanır. İçerik Markdown; {site_adi}, {telefon}, {eposta}, {adres} yer tutucuları ayarlardan dolar.
 * Varsayılan metinler başlangıç taslağıdır; yayına almadan önce işletme bilgileriyle gözden geçirilmelidir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->nullable()->unique();
            $table->string('title', 160);
            $table->string('slug', 180)->unique();
            $table->mediumText('body')->nullable();
            $table->string('meta_description', 170)->nullable();
            $table->string('footer_column', 20)->nullable(); // corporate | help | null (alt bilgide görünmez)
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $pages = [
            ['about', 'Hakkımızda', 'hakkimizda', 'corporate', <<<'MD'
{site_adi}, Eskişehir'de kitap, soru bankası, kırtasiye ve belge baskısı hizmeti veren yerel bir işletmedir.

Siparişlerinizi internetten verir, ödemenizi **kapıda nakit veya kartla** yaparsınız. Belgelerinizi yükleyin, basalım, ciltleyelim ve kapınıza getirelim.

## Neden biz?

- Eskişehir içinde hızlı teslimat
- Kapıda ödeme, ön ödeme yok
- Siyah-beyaz ve renkli baskı, spiral cilt
- Güncel yayınevlerinin kitapları

Sorularınız için {telefon} numarasından veya {eposta} adresinden bize ulaşabilirsiniz.
MD],
            ['delivery', 'Teslimat ve İade', 'teslimat-ve-iade', 'help', <<<'MD'
## Teslimat

Siparişler yalnız Eskişehir içinde, sipariş sayfasında listelenen ilçe ve mahallelere teslim edilir. Teslimat ücreti ve ücretsiz teslimat sınırı sipariş sayfasında gösterilir.

Ödeme teslimatta **nakit** veya **kredi / banka kartı** ile yapılır.

## İade ve cayma

Teslim aldığınız ürünü 14 gün içinde, kullanılmamış ve yeniden satılabilir durumda iade edebilirsiniz. İade için {telefon} numarasını arayın veya {eposta} adresine yazın.

Size özel basılan belgeler (fotokopi, çıktı, ciltleme) kişiye özel hazırlandığı için cayma hakkı kapsamında değildir; baskı hatası olursa ücretsiz yeniden basılır.
MD],
            ['faq', 'Sıkça Sorulan Sorular', 'sikca-sorulan-sorular', 'help', <<<'MD'
## Ödemeyi nasıl yaparım?
Teslimatta nakit veya kartla ödersiniz. İnternette kart bilgisi istenmez.

## Üye olmam gerekir mi?
Hayır. Üye olmadan da sipariş verebilirsiniz. Üye olursanız adreslerinizi ve siparişlerinizi kaydedebilirsiniz.

## Siparişimi nasıl takip ederim?
Sipariş numaranız ve telefonunuzla **Sipariş Takibi** sayfasından durumunu görebilirsiniz. Her aşamada e-posta da gönderilir.

## Hangi belgeleri basabilirsiniz?
PDF, Word, PowerPoint, Excel ve resim dosyalarını yükleyebilirsiniz. Fiyat, sayfa sayısına göre anında hesaplanır.
MD],
            ['sales', 'Mesafeli Satış Sözleşmesi', 'mesafeli-satis-sozlesmesi', 'corporate', <<<'MD'
## 1. Taraflar

**Satıcı:** {site_adi} — Adres: {adres} — Telefon: {telefon} — E-posta: {eposta}

**Alıcı:** Sipariş formunda adı, adresi ve iletişim bilgileri yazılı kişi.

## 2. Konu

Bu sözleşme, alıcının internet sitesi üzerinden sipariş verdiği ürün ve hizmetlerin satışı ve teslimine ilişkin tarafların hak ve yükümlülüklerini, 6502 sayılı Tüketicinin Korunması Hakkında Kanun ve Mesafeli Sözleşmeler Yönetmeliği hükümlerine göre düzenler.

## 3. Ürün, fiyat ve ödeme

Ürünlerin türü, miktarı, satış fiyatı ve teslimat ücreti sipariş özetinde ve sipariş onayı e-postasında yer alır. Fiyatlara KDV dahildir. Ödeme teslimatta nakit veya kartla yapılır.

## 4. Teslimat

Teslimat, alıcının sipariş formunda belirttiği adrese, sipariş sayfasında gösterilen tahmini tarihte yapılır. Yasal teslim süresi 30 günü aşamaz.

## 5. Cayma hakkı

Alıcı, teslimden itibaren 14 gün içinde gerekçe göstermeden cayma hakkını kullanabilir. Alıcının istekleri doğrultusunda kişiye özel hazırlanan ürünler (baskı, fotokopi, ciltleme) cayma hakkı kapsamı dışındadır.

## 6. Uyuşmazlık

Uyuşmazlıklarda Ticaret Bakanlığınca ilan edilen parasal sınırlar içinde Tüketici Hakem Heyetleri, aşan durumlarda Eskişehir Tüketici Mahkemeleri yetkilidir.
MD],
            ['kvkk', 'KVKK Aydınlatma Metni', 'kvkk-aydinlatma-metni', 'corporate', <<<'MD'
{site_adi} olarak kişisel verilerinizi 6698 sayılı Kişisel Verilerin Korunması Kanunu'na uygun olarak işliyoruz.

## İşlenen veriler
Ad soyad, telefon, e-posta, teslimat adresi, sipariş bilgileri, yüklediğiniz belgeler ve mesajlarınız.

## Amaç
Siparişinizi hazırlamak ve teslim etmek, sizinle iletişim kurmak, yasal yükümlülükleri yerine getirmek.

## Saklama
Yüklediğiniz belgeler siparişten sonra yönetim panelinde belirtilen süre sonunda otomatik silinir. Sipariş kayıtları yasal süreler boyunca saklanır.

## Haklarınız
Verilerinizin işlenip işlenmediğini öğrenme, düzeltilmesini veya silinmesini isteme haklarına sahipsiniz. Başvurularınızı {eposta} adresine iletebilirsiniz.
MD],
            ['membership', 'Üyelik Koşulları', 'uyelik-kosullari', 'corporate', <<<'MD'
Üye olarak hesabınızın güvenliğinden ve verdiğiniz bilgilerin doğruluğundan sorumlu olduğunuzu kabul edersiniz.

- Hesabınızı başkasıyla paylaşmayın; parolanızı düzenli olarak değiştirin.
- Kurallara aykırı kullanımda {site_adi} hesabı kapatabilir.
- Hesabınızın silinmesini {eposta} adresine yazarak isteyebilirsiniz.
MD],
            ['cookies', 'Çerez Politikası', 'cerez-politikasi', 'corporate', <<<'MD'
Sitemiz yalnız çalışması için zorunlu çerezleri kullanır: oturum, sepet ve güvenlik (CSRF) çerezleri. Reklam veya izleme çerezi kullanılmaz.

Tarayıcınızın ayarlarından çerezleri silebilirsiniz; ancak bu durumda sepet ve giriş gibi özellikler çalışmaz.
MD],
        ];
        foreach ($pages as $i => [$key, $title, $slug, $column, $body]) {
            DB::table('pages')->insert(['key' => $key, 'title' => $title, 'slug' => $slug, 'body' => $body, 'footer_column' => $column,
                'position' => $i, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
