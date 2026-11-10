<?php

namespace Tests\Unit;

use App\Domain\Import\BookFiles;
use App\Domain\Import\FolderName;
use App\Domain\Import\ImportItemProcessor;
use App\Domain\Import\InfoSheetParser;
use App\Domain\Import\Isbn;
use App\Domain\Import\PageReader;
use App\Domain\Import\TitleCategory;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeOcrEngine;

class ImportParsingTest extends TestCase
{
    public function test_info_sheet_is_parsed(): void
    {
        $r = (new InfoSheetParser)->parse(FakeOcrEngine::sample()->words('x'));

        $this->assertSame("Almanya'nın Kısa Tarihi", $r['title']);
        $this->assertSame('AKT-583', $r['fields']['code']);
        $this->assertSame('Mary Fulbrook', $r['fields']['author']);
        $this->assertSame('Boğaziçi Üniversitesi Yayınevi', $r['fields']['publisher']);
        $this->assertSame('Tarih / Avrupa Tarihi / Almanya Tarihi', $r['fields']['category']);
        $this->assertNull($r['fields']['isbn']);   // "Henüz doğrulanmadı"
        $this->assertNull($r['fields']['pages']);
    }

    public function test_misread_labels_and_wrapped_values(): void
    {
        $engine = FakeOcrEngine::sample([
            'author' => ['Yazr', 'Ahmet Yılmaz'],          // OCR harf kaçırdı
            'category' => ['Turu', 'KPSS / Genel Kültür /'],
            'isbn' => ['lSBN / Barkod', '978-605-4238-60-6'],
            'pages' => ['Sayfa Sayisi', '312'],
        ]);
        // Türü değeri ikinci satıra taşmış gibi: etiketsiz, yalnız değer sütununda
        $engine->rows = array_merge(array_slice($engine->rows, 0, 5), [[null, 'Tarih Soru Bankası']], array_slice($engine->rows, 5));
        $r = (new InfoSheetParser)->parse($engine->words('x'));

        $this->assertSame('Ahmet Yılmaz', $r['fields']['author']);
        $this->assertSame('KPSS / Genel Kültür / Tarih Soru Bankası', $r['fields']['category']);
        $this->assertSame('978-605-4238-60-6', $r['fields']['isbn']);
        $this->assertSame('312', $r['fields']['pages']);
    }

    public function test_isbn_normalization(): void
    {
        $this->assertSame('9786054238606', Isbn::normalize('978-605-4238-60-6'));
        $this->assertSame('9786054238606', Isbn::normalize('ISBN: 978 6O5 4238 6O6'));  // O → 0
        $this->assertNull(Isbn::normalize('9786054238602'));                           // kontrol hanesi yanlış
        $this->assertSame('9780306406157', Isbn::normalize('0-306-40615-2'));            // ISBN-10
    }

    public function test_folder_names(): void
    {
        $this->assertSame('BOĞAZİÇİ ÜNİVERSİTESİ YAYINLARI', FolderName::publisher('BOĞAZİÇİ ÜNİVERSİTESİ YAYINLARI EKRAN GÖRÜNTÜLERİ'));

        $b = FolderName::book('BOĞAZİÇİ ÜNİVERSİTESİ YAYINLARI - ALMANYA\'NIN KISA TARİHİ - MARY FULBROOK', 'BOĞAZİÇİ ÜNİVERSİTESİ YAYINLARI EKRAN GÖRÜNTÜLERİ');
        $this->assertSame(['publisher' => 'BOĞAZİÇİ ÜNİVERSİTESİ YAYINLARI', 'title' => 'ALMANYA\'NIN KISA TARİHİ', 'author' => 'MARY FULBROOK', 'year' => null], $b);

        // Yayınevi önekli olmayan ve adında tire geçen kitap
        $b = FolderName::book('KPSS - TARİH - SORU BANKASI - ALİ VELİ', 'PALME YAYINLARI EKRAN GÖRÜNTÜLERİ');
        $this->assertSame('KPSS - TARİH - SORU BANKASI', $b['title']);
        $this->assertSame('ALİ VELİ', $b['author']);
        $this->assertSame('PALME YAYINLARI', $b['publisher']);

        $this->assertTrue(FolderName::similar("ALMANYA'NIN KISA TARİHİ", "Almanya'nın Kısa Tarihi"));
        $this->assertFalse(FolderName::similar('Fizik Soru Bankası', 'Kimya Konu Anlatımı'));
    }

    public function test_file_roles(): void
    {
        $this->assertSame(
            ['info' => 'Ekran görüntüsü 2025-01-02 101010.png', 'mockup' => 'ChatGPT Image 3 Oca 2025.png'],
            BookFiles::classify(['1.jpg', 'ChatGPT Image 3 Oca 2025.png', 'Ekran görüntüsü 2025-01-02 101010.png', 'notlar.txt'])
        );
        // "1" dosyası mockup yerine kullanılmaz (K5)
        $this->assertSame(['info' => 'Ekran görüntüsü.png', 'mockup' => null], BookFiles::classify(['Ekran görüntüsü.png', '1.png']));
    }

    public function test_soru_bankasi_archive_layout(): void
    {
        // Gerçek arşiv: kapak + künye (BASIM) + içindekiler; mockup varsa o, yoksa kapak ürün görselidir.
        $this->assertSame(['info' => null, 'mockup' => 'MOCKUP.png'], BookFiles::classify([
            '3D AYT BİYOLOJİ SORU BANKASI KAPAK.png', '3D YAYINLARI AYT BİYOLLOJİ SORU BANKASI BASIM.png', '3D YAYINLARI AYT BİYOLOJİ SORU BANKASI İÇİN.png', 'MOCKUP.png']));
        $this->assertSame(['info' => null, 'mockup' => '3D YAYINLARI AYT FİZ.KAP..png'], BookFiles::classify([
            '3D AYT FİZ.BASIM.png', '3D AYT FİZ.İÇİN.png', '3D YAYINLARI AYT FİZ.KAP..png']));
        $this->assertSame('3D TYT TÜRKÇE KAP.png', BookFiles::classify(['3D TYT TÜRKÇE KAP.png', '3D TYT TÜRKÇE İÇİN.png'])['mockup']);
        $this->assertSame('3D TYT DİN KAPAK.png', BookFiles::classify(['3D TYT DİN BASIM.png', '3D TYT DİN KAPAK.png', '3D TYT DİN ÇİN..png'])['mockup']);
        $this->assertSame('X KAPAK.png', BookFiles::classify(['X İÇİNDEKİLER İLK SAYFA.png', 'X İÇİNDEKİLER 2.png', 'X KAPAK.png', 'X BBASIM.png'])['mockup']);
        // Yalnız künye ve içindekiler varsa ürün görseli yoktur (içindekiler sayfası ürün görseli olmaz).
        $this->assertNull(BookFiles::classify(['X BASIM.png', 'X İÇİN-1.png'])['mockup']);
        $this->assertNull(BookFiles::classify(['X İÇİN2.png'])['mockup']);
        // "DİL BİLGİSİ" kelimesi bilgi tablosu sanılmaz
        $this->assertSame(['info' => null, 'mockup' => '3D DİL BİLGİSİ KAPAK.png'],
            BookFiles::classify(['3D DİL BİLGİSİ BASIM.png', '3D DİL BİLGİSİ KAPAK.png', '3D DİL BİLGİSİ İÇİNDEKİLER 2.png']));
        $this->assertSame('Bilgi.png', BookFiles::classify(['Bilgi.png'])['info']);

        $b = FolderName::book('3D YAYINLARI AYT BİYOLOJİ SORU BANKASI 2023', '3D YAYINLARI EKRAN GÖRÜNTÜLERİ');
        $this->assertSame(['publisher' => '3D YAYINLARI', 'title' => 'AYT BİYOLOJİ SORU BANKASI 2023', 'author' => null, 'year' => 2023], $b);
        $b = FolderName::book('4K YAYINLARI AYT SAYISAL TAM TEKRAR 6 FASİKÜL 2 DENEME 2023.pdf', '4K YAYINLARI EKRAN GÖRÜNTÜLERİ');
        $this->assertSame('AYT SAYISAL TAM TEKRAR 6 FASİKÜL 2 DENEME 2023', $b['title']);
        $this->assertSame(2023, $b['year']);
        // Üst klasör olmadan da "… YAYINLARI" öneki yayınevi sayılır.
        $b = FolderName::book('4K YAYINLARI TYT TARİH SORU BANKASI');
        $this->assertSame(['publisher' => '4K YAYINLARI', 'title' => 'TYT TARİH SORU BANKASI', 'author' => null, 'year' => null], $b);
    }

    public function test_cover_and_imprint_pages(): void
    {
        // Gerçek kapakların Tesseract çıktısı (KİTAPLAR klasörü)
        $c = PageReader::cover("AYT\nBiYOLOJİ\nSORU BANKASI\nKerim-Korkmaz\naad\n=\nDETAYLI ©\nVİDEO\nÇÖZÜMLÜ YAYILLARI");
        $this->assertSame('Kerim Korkmaz', $c['author']);
        $this->assertSame([], $c['features']);

        $c = PageReader::cover("BiYOLOJİ\nKONU ÖZETLİ SORU BANKASI\nElif Sarıışık\n> Kavramlarla Konu Özeti\n> Kazanım Odaklı Konu Testi\n"
            ."> Kazandıran ÖSYM Testi\n> Kurgusal Beceri Temelli Testler\n> Kişiye Özel Kazanım Tarama Testi\nUR\n(CD cözcesiyorctden\nDETAYLI");
        $this->assertSame('Elif Sarıışık', $c['author']);
        $this->assertSame(['Kavramlarla Konu Özeti', 'Kazanım Odaklı Konu Testi', 'Kazandıran ÖSYM Testi', 'Kurgusal Beceri Temelli Testler',
            'Kişiye Özel Kazanım Tarama Testi'], $c['features']);
        $this->assertSame('Kağan Kerem Kıvanç', PageReader::cover("b ıZıK\nKONU ÖZETLİ SORU BANKASI\nKağan Kerem Kıvanç\n> Kavramlarla Konu Özeti")['author']);
        $this->assertSame('Özgür Balcı', PageReader::cover("GEOMETRİ\nSORU BANKASI\nEditör: Özgür Balcı")['author']);
        $this->assertSame('Ali Rıza Özata, Fatih Kazova, Şerif Karataş', PageReader::cover(
            "MATEMATIiK\nKONU OZETLi SORU BANKASI\nAli Rıza Özata - Fatih Kazova - Şerif Karataş Editör: Özgür Balcı\n> Kavramlarla Konu Özeti")['author']);
        $this->assertNull(PageReader::cover("TYT\nFİZİK\nVİDEO DESTEKLİ\nDEFTER\nAlper Hoca'dan")['author']); // slogan ad sayılmaz

        $i = PageReader::imprint("Kapak\n3D Grafik\nBaskı Tarihi\nMart 2022\nBaskı\nÖzyurt Matbaacılık\nSertifika No: 46772");
        $this->assertSame(['year' => 2022, 'isbn' => null], $i);
        $this->assertSame(2022, PageReader::imprint("BASKI TARİHİ\nMart 2022")['year']); // büyük "İ"
        $this->assertSame(2022, PageReader::imprint("4K Yayınları Dizgi Birimi\n(CeaskıTarini )\nMart 2022\nBASKI")['year']); // bozuk etiket
        $this->assertSame(2021, PageReader::imprint("Dizgi\nEylül 2021\nBaskı Yeri")['year']); // etiketsiz ay + yıl
        $this->assertNull(PageReader::imprint('Sertifika No: 51788 TEL: 0312 394 35 39')['year']);
        $this->assertSame('Özgür Balcı', PageReader::cover("GEOMETRİ\nSORU BANKASI\nEDİTÖR: Özgür Balcı")['author']);
        $this->assertSame('Ali Veli', PageReader::cover("FASİKÜL\nAli Veli EDİTÖR: Can Cem")['author']);
        $this->assertNull(PageReader::imprint('Baskı Tarihi 2028')['year']); // OCR hatası: gelecek yıl kabul edilmez
        $this->assertSame('9789754184426', PageReader::imprint("ISBN: 978-975-418-442-6\nBaskı Tarihi: Ocak 2024")['isbn']);
        $this->assertNull(PageReader::isbn('978-975-418-442-7')); // kontrol hanesi tutmuyor

        $this->assertSame(['cover' => '3D TYT KİMYA KAP..png', 'imprint' => '3D TYT KİMYA BASIM.png'],
            BookFiles::pages(['3D TYT KİMYA BASIM.png', '3D TYT KİMYA KAP..png', '3D TYT KİMYA İÇİN-1.png', 'MOCKUP.png']));
    }

    public function test_category_guessed_from_title(): void
    {
        $cases = [
            'AYT Biyoloji Soru Bankası 2023' => ['Soru Bankası', 'AYT', 'Biyoloji'],
            'TYT Matematik Konu Özetli Soru Bankası 2023' => ['Soru Bankası', 'TYT', 'Matematik'],
            'TYT Kimya Video Destekli Defter 2024' => ['Defter', 'TYT', 'Kimya'],
            'TYT Biyoloji Video Destekli Defteri 2024' => ['Defter', 'TYT', 'Biyoloji'],
            'AYT Eşit Ağırlık Sözel Tam Tekrar 6 Fasikül 2 Deneme 2023' => ['Tam Tekrar', 'AYT', 'Eşit Ağırlık Sözel'],
            'TYT Din Kültürü Soru Bankası 2022' => ['Soru Bankası', 'TYT', 'Din Kültürü'],
            'Dil Bilgisi Soru Bankası 2023' => ['Soru Bankası', 'Dil Bilgisi'],
            'KPSS TARİH SORU BANKASI' => ['Soru Bankası', 'KPSS', 'Tarih'],
            'TYT Fizik Video Destekli Defter' => ['Defter', 'TYT', 'Fizik'],
            "Almanya'nın Kısa Tarihi" => [], // roman/inceleme: tahmin yok
        ];
        foreach ($cases as $title => $expected) {
            $this->assertSame($expected, TitleCategory::guess($title), $title);
        }
    }

    public function test_category_split(): void
    {
        $this->assertSame(['Tarih', 'Avrupa Tarihi', 'Almanya Tarihi'], ImportItemProcessor::splitCategory('Tarih / Avrupa Tarihi > Almanya Tarihi'));
        $this->assertSame([], ImportItemProcessor::splitCategory(null));
    }
}
