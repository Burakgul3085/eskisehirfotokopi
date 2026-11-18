<?php

namespace Tests\Feature;

use App\Domain\Catalog\CategoryTree;
use App\Domain\Import\ImportItemProcessor;
use App\Domain\Import\LanguageGuess;
use App\Domain\Import\Ocr\OcrEngine;
use App\Models\Admin;
use App\Models\Author;
use App\Models\Category;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\Product;
use App\Models\Publisher;
use App\Support\EmptyValue;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeOcrEngine;
use Tests\TestCase;

/** Toplu yüklemede okuma doğruluğu, ek bilgiler, tekrar açmama ve bilgilerin müşteriye gösterimi. */
class ImportAccuracyTest extends TestCase
{
    use RefreshDatabase;

    private const PUB = 'BOĞAZİÇİ ÜNİVERSİTESİ YAYINLARI EKRAN GÖRÜNTÜLERİ';

    private const BOOK = "BOĞAZİÇİ ÜNİVERSİTESİ YAYINLARI - ALMANYA'NIN KISA TARİHİ - MARY FULBROOK";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->actingAs(Admin::factory()->create(), 'admin');
    }

    private function useOcr(FakeOcrEngine $engine): FakeOcrEngine
    {
        $this->app->instance(OcrEngine::class, $engine);

        return $engine;
    }

    private function importBook(string $folder = self::BOOK, string $publisher = self::PUB, array $roles = ['info', 'mockup']): ImportItem
    {
        $names = ['info' => 'Ekran görüntüsü.png', 'mockup' => 'ChatGPT Görseli.png', 'cover' => 'KAPAK.png'];
        $book = ['publisher_folder' => $publisher, 'folder' => $folder];
        foreach ($roles as $r) {
            $book[$r] = $names[$r];
        }
        $res = $this->postJson(route('admin.imports.store'), ['name' => 'KİTAPLAR', 'books' => [$book]])->assertOk();
        [$batch, $id] = [$res->json('batch'), $res->json('items.0')];
        $this->assertNotNull($id);
        foreach ($roles as $r) {
            $this->post(route('admin.imports.upload', [$batch, $id]), ['role' => $r, 'file' => UploadedFile::fake()->image($names[$r], 600, 400)])->assertOk();
        }

        return ImportItem::findOrFail($id);
    }

    public function test_three_readings_are_voted_and_minority_typo_loses(): void
    {
        $ocr = FakeOcrEngine::sample(['subtitle' => ['Alt Başlık', 'Acemi Bir Boks6riin Defterleri']]);
        $ocr->alternates = [
            ["Almanya'nın Kısa Tarihi", FakeOcrEngine::rows(['subtitle' => ['Alt Başlık', 'Acemi Bir Boksörün Defterleri']])],
            ["Almanya'nın Kısa Tarihi", FakeOcrEngine::rows(['subtitle' => ['Alt Başlık', 'Acemi Bir Boksörün Defterleri']])],
        ];
        $this->useOcr($ocr);
        $item = $this->importBook();

        $this->assertSame('Acemi Bir Boksörün Defterleri', $item->product->subtitle);
        $this->assertSame('imported', $item->status, json_encode($item->issues, JSON_UNESCAPED_UNICODE));
        $this->assertCount(3, $ocr->wordCalls);

        // Üç okuma da farklıysa (çoğunluk yok) en güvenilir değer yazılır ama insan kontrol eder
        $ocr = FakeOcrEngine::sample(['code' => ['Ürün Kodu', 'AKT-583']]);
        $ocr->alternates = [
            [null, FakeOcrEngine::rows(['code' => ['Ürün Kodu', 'AKT-588']])],
            [null, FakeOcrEngine::rows(['code' => ['Ürün Kodu', 'AKT-563']])],
        ];
        $this->useOcr($ocr);
        $item = $this->importBook('BOĞAZİÇİ ÜNİVERSİTESİ YAYINLARI - OSMANLI TARİHİ - HALİL İNALCIK');
        $this->assertSame('review', $item->status);
        $this->assertStringContainsString('Ürün kodu okumaları birbirini tutmuyor', implode(' ', $item->issues));
    }

    public function test_extra_rows_are_saved_and_chat_text_and_empty_values_are_not(): void
    {
        $this->useOcr(FakeOcrEngine::sample([
            'translator' => ['Çevirmen', 'Mehmet Evren Dinçer'],
            'cover_type' => ['Kapak Türü', 'Bulunamadı'],
            'isbn' => ['ISBN / Barkod', 'ISBN bulunamadı'],
            'chat' => ['Kanka, kitabın orijinal kapak', 'tasarımını koruyarak 3D ürün görselini hazırladım.'],
        ]));
        $item = $this->importBook();
        $p = $item->product;

        $this->assertSame([['label' => 'Çevirmen', 'value' => 'Mehmet Evren Dinçer']], $p->extra_specs);
        $this->assertNull($p->isbn);
        $this->assertSame('imported', $item->status, json_encode($item->issues, JSON_UNESCAPED_UNICODE));

        // Elle düzeltilen ek bilgi yeniden yüklemede ezilmez
        $p->update(['extra_specs' => [['label' => 'Çevirmen', 'value' => 'M. Evren Dinçer']]]);
        app(ImportItemProcessor::class)->apply(['title' => $p->title, 'extras' => [['label' => 'Çevirmen', 'value' => 'Mehmet Evren Dinçer'], ['label' => 'Dil', 'value' => 'Türkçe']]], $p);
        $this->assertSame([['label' => 'Çevirmen', 'value' => 'M. Evren Dinçer'], ['label' => 'Dil', 'value' => 'Türkçe']], $p->fresh()->extra_specs);
    }

    public function test_empty_value_phrases(): void
    {
        foreach (['Henüz doğrulanmadı', 'Bulunamadı', 'ISBN bulunamadı', '-', '—', 'Mevcut değil', 'Bilgi yok', 'Henüz dogrulanm vt', 'Belirtilmemiş', 'Unknown', 'Bulunamad1'] as $v) {
            $this->assertTrue(EmptyValue::is($v), $v);
        }
        foreach (['Mehmet Evren Dinçer', '318', 'Türkçe', 'Yok Olan Şehirler', 'Bulmaca Kitabı'] as $v) {
            $this->assertFalse(EmptyValue::is($v), $v);
        }
    }

    public function test_foreign_language_is_detected_and_added_to_ocr(): void
    {
        $this->assertSame('deu', LanguageGuess::guess('Die Verwandlung und andere Erzählungen von Franz Kafka'));
        $this->assertSame('ita', LanguageGuess::guess('Storia della lingua italiana per gli stranieri'));
        $this->assertNull(LanguageGuess::guess("Almanya'nın Kısa Tarihi Ürün Bilgisi Açıklama Yazar Mary Fulbrook İnsan Doğasının Modern İnkârı"));
        $this->assertSame('tur+deu+eng', ImportItemProcessor::withLanguage('tur+eng', 'deu'));

        $ocr = $this->useOcr(FakeOcrEngine::sample(['title' => ['Kitap Adı', 'Die Verwandlung und andere Erzählungen']], 'Die Verwandlung und andere Erzählungen'));
        $item = $this->importBook('REKLAM VERLAG - DIE VERWANDLUNG UND ANDERE ERZÄHLUNGEN - FRANZ KAFKA', 'REKLAM VERLAG EKRAN GÖRÜNTÜLERİ');
        $this->assertSame('deu', $item->parsed['language']);
        $this->assertContains([0, 'tur+deu+eng'], $ocr->wordCalls);
        $this->assertSame('Die Verwandlung und andere Erzählungen', $item->product->title);
    }

    public function test_dotless_i_follows_folder_and_rn_m_confusion_is_fixed(): void
    {
        $this->assertSame("Almanya'nın Kısa Tarihi", ImportItemProcessor::fixDotless("Almanya'nin Kisa Tarihi", "ALMANYA'NIN KISA TARİHİ"));
        $this->assertSame('İstanbul', ImportItemProcessor::fixDotless('Istanbul', 'İSTANBUL'));
        $this->assertTrue(ImportItemProcessor::similarWord('Loma', 'lorna'));
        $this->assertFalse(ImportItemProcessor::similarWord('Ali', 'alp'));
        $this->assertFalse(ImportItemProcessor::similarWord('Fulbrook', 'marshall'));

        $this->useOcr(FakeOcrEngine::sample(['author' => ['Yazar', 'Yoshi Olda, Loma Marshall']], "Almanya’'nin Kısa Tarihi"));
        $item = $this->importBook("BOĞAZİÇİ ÜNİVERSİTESİ YAYINLARI - ALMANYA'NIN KISA TARİHİ - YOSHİ OİDA LORNA");
        $this->assertSame("Almanya'nın Kısa Tarihi", $item->product->title);
        $this->assertSame('Yoshi Oida, Lorna Marshall', $item->product->author->name);
    }

    public function test_existing_publisher_category_author_and_product_are_not_opened_again(): void
    {
        $this->useOcr(FakeOcrEngine::sample(['code' => null, 'category' => ['Türü', 'Tarih / Avrupa Tarihi / Almanya Tarihi']]));
        $first = $this->importBook();
        $this->assertSame([], $first->parsed['new_categories']); // kısa listedeki Tarih seçildi, dal açılmadı
        $counts = fn () => [Product::count(), Publisher::count(), Author::count(), Category::count()];
        $before = $counts();

        // Başka bir yüklemede farklı yazımlarla aynı kitap: büyük harf, kesme işareti yok, yayınevi kısa adı, OCR harf hatası
        $this->useOcr(FakeOcrEngine::sample([
            'code' => null,
            'publisher' => ['Yayınevi', 'Boğaziçi Yayınları'],
            'author' => ['Yazar', 'Mary Fulbrok'],
            'category' => ['Türü', 'Tarih / Avrupa Tarıhi / Almanya Tarihi'],
        ], 'ALMANYANIN KISA TARİHİ'));
        $second = $this->importBook('BOĞAZİÇİ YAYINLARI - ALMANYANIN KISA TARİHİ - MARY FULBROOK', 'BOĞAZİÇİ YAYINLARI');
        $this->assertSame($before, $counts());
        $this->assertSame($first->product_id, $second->product_id);
        $this->assertSame([], $second->parsed['new_categories']);

        $this->assertSame(Author::where('match_key', 'mary fulbrook')->value('id'), Author::resolve('Fulbrook Mary')->id);
    }

    public function test_cover_is_used_as_product_image_when_mockup_is_missing(): void
    {
        $this->useOcr(FakeOcrEngine::sample());
        $item = $this->importBook(roles: ['info', 'cover']);
        $this->assertNotNull($item->product->image_key);
        $this->assertContains('MOCKUP görseli yoktu; ürün görseli olarak kapak kullanıldı.', $item->issues);
        $this->assertNull($item->cover_path);
    }

    public function test_spec_visibility_global_bulk_batch_and_product_level(): void
    {
        $this->useOcr(FakeOcrEngine::sample(['isbn' => ['ISBN / Barkod', '978-605-4238-60-6'], 'translator' => ['Çevirmen', 'Mehmet Evren Dinçer']]));
        $item = $this->importBook();
        $p = $item->product;
        $p->update(['page_count' => 300, 'status' => 'active']);
        $url = route('product.show', $p->slug);
        $this->get($url)->assertOk()->assertSee('9786054238606')->assertSee('Mehmet Evren Dinçer')->assertSee('AKT-583');

        // Genel ayar: ISBN gizli (Google bilgisi dahil), aramada yine bulunur
        $this->put(route('admin.settings.update', 'urun-bilgileri'), ['show' => array_map(fn ($k) => $k === 'isbn' ? '0' : '1', \array_combine(array_keys(Product::SPECS), array_keys(Product::SPECS)))])
            ->assertRedirect();
        $this->assertSame(['isbn'], Settings::hiddenSpecs());
        $this->get($url)->assertOk()->assertDontSee('9786054238606')->assertDontSee('gtin13')->assertSee('AKT-583');
        $this->get('/ara?q=9786054238606')->assertRedirect(route('product.show', $p)); // ISBN birebir: doğrudan ürün

        // Ürün kendi seçimi genel ayarın önüne geçer
        $this->put(route('admin.products.update', $p), [
            'title' => $p->title, 'status' => 'active', 'page_count' => 300, 'isbn' => $p->isbn, 'code' => $p->code,
            'spec_visibility' => ['isbn' => '1', 'code' => '0'],
            'extra_specs' => [['label' => 'Çevirmen', 'value' => 'Mehmet Evren Dinçer', 'visible' => '0'], ['label' => 'Dil', 'value' => 'Türkçe', 'visible' => ''], ['label' => '', 'value' => '']],
        ])->assertRedirect();
        $p->refresh();
        $this->assertEquals(['isbn' => true, 'code' => false, 'ek:cevirmen' => false], $p->spec_visibility);
        $this->get($url)->assertSee('9786054238606')->assertDontSee('AKT-583')->assertDontSee('Mehmet Evren Dinçer')->assertSee('Türkçe');

        // Toplu yükleme sonuç sayfasından: tüm üründe "Ek bilgi: Dil" gizle
        $this->get(route('admin.imports.show', $item->batch_id))->assertOk()->assertSee('Ek bilgi: Dil');
        $this->post(route('admin.imports.specs', $item->batch_id), ['spec' => 'ek:dil', 'state' => 'hide'])->assertRedirect();
        $this->get($url)->assertDontSee('Türkçe');

        // Ürünler listesinden toplu: ürün seçimi kaldırılır, genel ayara (ISBN gizli) döner
        $this->post(route('admin.products.bulk'), ['ids' => [$p->id], 'action' => 'spec_default', 'spec' => 'isbn'])->assertRedirect();
        $this->get($url)->assertDontSee('9786054238606');
        $this->get(route('admin.products.edit', $p))->assertOk()->assertSee('Müşteriye gösterilen bilgiler');
        $this->get(route('admin.settings.edit', 'urun-bilgileri'))->assertOk();
    }

    public function test_review_screen_shows_field_crops_and_saves_extras(): void
    {
        $ocr = FakeOcrEngine::sample(['code' => ['Ürün Kodu', 'AKT-583']]);
        $ocr->alternates = [[null, FakeOcrEngine::rows(['code' => ['Ürün Kodu', 'AKT-588']])], [null, FakeOcrEngine::rows(['code' => ['Ürün Kodu', 'AKT-563']])]];
        $this->useOcr($ocr);
        $item = $this->importBook();
        $this->assertSame('review', $item->status);

        $this->get(route('admin.imports.review', $item))->assertOk()->assertSee(route('admin.imports.crop', [$item, 'code']), false)->assertSee('Ek bilgiler');
        $this->get(route('admin.imports.crop', [$item, 'code']))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('admin.imports.crop', [$item, 'subtitle']))->assertNotFound();

        $this->put(route('admin.imports.update', $item), [
            'title' => "Almanya'nın Kısa Tarihi", 'author' => 'Mary Fulbrook', 'publisher' => 'Boğaziçi Üniversitesi Yayınevi',
            'category' => 'Tarih / Avrupa Tarihi', 'code' => 'AKT-583',
            'spec_visibility' => ['isbn' => '0'], 'extra_specs' => [['label' => 'Çevirmen', 'value' => 'Ahmet Yılmaz', 'visible' => '1']],
        ])->assertRedirect();
        $p = $item->fresh()->product;
        $this->assertSame([['label' => 'Çevirmen', 'value' => 'Ahmet Yılmaz']], $p->extra_specs);
        $this->assertEquals(['isbn' => false, 'ek:cevirmen' => true], $p->spec_visibility);
    }

    public function test_new_categories_can_be_confirmed_or_merged(): void
    {
        // Kısa listede olmayan sınav adı ("MSÜ") kitap adından okunursa Sınav Hazırlık altına açılır ve panelde görünür
        $tree = app(CategoryTree::class);
        $this->useOcr(FakeOcrEngine::sample([], 'MSÜ Tarih Soru Bankası'));
        $item = $this->importBook('BOĞAZİÇİ ÜNİVERSİTESİ YAYINLARI - MSÜ TARİH SORU BANKASI - MARY FULBROOK');
        $new = Category::where('name', 'MSÜ')->firstOrFail();
        $this->assertSame('Sınav Hazırlık › MSÜ', $item->product->category->fullName());
        $batch = ImportBatch::findOrFail($item->batch_id);

        $this->get(route('admin.imports.show', $batch))->assertOk()->assertSee('Bu yüklemede açılan yeni kategoriler (1)')->assertSee('Sınav Hazırlık › MSÜ');
        $this->post(route('admin.imports.category.merge', [$batch, $new]), ['into' => Category::where('name', 'TYT')->value('id')])->assertRedirect();
        $this->assertNull(Category::find($new->id));
        $this->assertSame('Sınav Hazırlık › TYT', $item->product->fresh()->category->fullName());
        $this->get(route('admin.imports.show', $batch))->assertDontSee('Bu yüklemede açılan yeni kategoriler');

        $other = $tree->ensurePath(['Felsefe']);
        $batch->update(['checked_categories' => null]);
        $item->update(['parsed' => ['new_categories' => [$other->id]] + $item->parsed]);
        $this->post(route('admin.imports.category.check', [$batch, $other]))->assertRedirect();
        $this->assertSame([$other->id], $batch->fresh()->checked_categories);
        $this->get(route('admin.imports.show', $batch))->assertDontSee('Bu yüklemede açılan yeni kategoriler');
    }
}
