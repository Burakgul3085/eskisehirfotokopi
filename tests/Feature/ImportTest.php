<?php

namespace Tests\Feature;

use App\Domain\Catalog\CategoryTree;
use App\Domain\Import\Ocr\OcrEngine;
use App\Models\Admin;
use App\Models\Category;
use App\Models\ImportItem;
use App\Models\Product;
use App\Models\Publisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeOcrEngine;
use Tests\TestCase;

class ImportTest extends TestCase
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

    /** Kitap listesini gönderir, dosyaları yükler; kalemi döndürür. */
    private function importBook(string $folder = self::BOOK, string $publisher = self::PUB): ImportItem
    {
        $res = $this->postJson(route('admin.imports.store'), [
            'name' => 'KİTAPLAR',
            'books' => [['publisher_folder' => $publisher, 'folder' => $folder, 'info' => 'Ekran görüntüsü.png', 'mockup' => 'ChatGPT Görseli.png']],
        ])->assertOk();
        $id = $res->json('items.0');
        $this->assertNotNull($id);
        $batch = $res->json('batch');

        $this->post(route('admin.imports.upload', [$batch, $id]), ['role' => 'info', 'file' => UploadedFile::fake()->image('Ekran görüntüsü.png', 600, 400)])
            ->assertOk()->assertJson(['status' => 'waiting_files']);
        $this->post(route('admin.imports.upload', [$batch, $id]), ['role' => 'mockup', 'file' => UploadedFile::fake()->image('ChatGPT Görseli.png', 900, 1200)])
            ->assertOk();

        return ImportItem::findOrFail($id);
    }

    public function test_ocr_noise_is_cleaned_subtitle_kept_and_categories_corrected(): void
    {
        // OCR "Popüler Bilim"i "Port ir Bilim", "Oyunculuk"u "Üyunculuk" okumuş: kısa listedeki Popüler Bilim seçilir,
        // ayrıntılar (düzeltilmiş) konu etiketi olur; yeni kategori açılmaz.
        $before = Category::count();
        $this->useOcr(FakeOcrEngine::sample([
            'publisher' => ['Yayınevi', 'Boğaziçi Yayınları'],
            'category' => ['Türü', '* Port ir Bilim / Fizik / Üyunculuk'],
        ], "Almanya'nın Kısa Tarihi — Bir Ulusun Doğuşu"));
        $item = $this->importBook();

        $p = Product::with('category')->findOrFail($item->product_id);
        $this->assertSame("Almanya'nın Kısa Tarihi", $p->title);
        $this->assertSame('Bir Ulusun Doğuşu', $p->subtitle);
        $this->assertSame('Okuma Kitapları › Popüler Bilim', $p->category->fullName());
        $this->assertSame(['Fizik', 'Oyunculuk'], $p->topics);
        $this->assertSame($before, Category::count());
        // Alt başlık ve kısa yayınevi adı "kontrol gerekli" sebebi değil
        $this->assertSame('imported', $item->status, json_encode($item->issues));

        // Görseldeki başlık "Ad — Yazar" biçimindeyse yazar alt başlık sayılmaz; OCR harf hatası klasörden düzelir.
        $this->useOcr(FakeOcrEngine::sample(['code' => ['Ürün Kodu', 'AKT-999']], "\Mimanya'nın Kısa Tarihi — Mary Fulbrook"));
        $item = $this->importBook('BOĞAZİÇİ ÜNİVERSİTESİ YAYINLARI - ALMANYANIN KISA TARİHİ - MARY FULBROOK');
        $this->assertNull($item->parsed['subtitle']);
        $this->assertSame('Almanyanın Kısa Tarihi', $item->parsed['title']);
        // Aynı yayınevinde yazımca aynı kitap yeniden açılmaz; mevcut adı ve alt başlığı korunur
        $p = Product::findOrFail($item->product_id);
        $this->assertSame(1, Product::count());
        $this->assertSame("Almanya'nın Kısa Tarihi", $p->title);
        $this->assertSame('Bir Ulusun Doğuşu', $p->subtitle);
    }

    public function test_turkish_letters_multi_authors_and_garbled_empty_markers(): void
    {
        // Görsel "ğ"yi "g" okumuş: klasördeki doğru yazım alınır. Bozuk okunan "Henüz doğrulanmadı" boş sayılır.
        // Klasörde kısaltılmış iki yazar: görseldeki liste klasördeki harflerle düzeltilip birleştirilir.
        $this->useOcr(FakeOcrEngine::sample([
            'author' => ['Yazar', 'Yoshi Olda, Lorna Marshall'],
            'isbn' => ['ISBN / Barkod', 'Henüz dogrulanm vt'],
            'pages' => ['Sayfa Sayısı', 'Henüz dağrulanmadı'],
        ], 'Tarihin Huzursuzlugu'));
        $item = $this->importBook('BOĞAZİÇİ ÜNİVERSİTESİ YAYINLARI - TARİHİN HUZURSUZLUĞU - YOSHİ OİDA LORNA');

        $p = Product::with('author')->findOrFail($item->product_id);
        $this->assertSame('Tarihin Huzursuzluğu', $p->title);
        $this->assertSame('Yoshi Oida, Lorna Marshall', $p->author->name);
        $this->assertSame('imported', $item->status, json_encode($item->issues));

        // Klasördeki ad yazarın uzun biçimi: "V S Ramachandran, Sandra Blakeslee"
        $this->useOcr(FakeOcrEngine::sample(['code' => ['Ürün Kodu', 'BHY-1'], 'author' => ['Yazar', '“Samachandran, Sandra Blakeslee']], 'Beyindeki Hayaletler'));
        $item = $this->importBook('BOĞAZİÇİ ÜNİVERSİTESİ YAYINLARI - BEYİNDEKİ HAYALETLER - V S RAMACHANDRAN');
        $this->assertSame('V S Ramachandran, Sandra Blakeslee', Product::findOrFail($item->product_id)->author->name);
        $this->assertSame('imported', $item->status, json_encode($item->issues));
    }

    public function test_category_merge_moves_products_and_merges_same_named_children(): void
    {
        $tree = app(CategoryTree::class);
        $good = $tree->ensurePath(['Eski Tarih', 'Dünya Tarihi']);
        $bad = $tree->ensurePath(['Ah', 'Dünya Tarihi', 'Rusya Tarihi']);
        $p = Product::create(['title' => 'Rusya', 'slug' => 'rusya', 'category_id' => $bad->id]);

        $tree->merge(Category::where('name', 'Ah')->first(), Category::where('name', 'Eski Tarih')->first());

        $this->assertSame('Eski Tarih › Dünya Tarihi › Rusya Tarihi', $p->fresh()->category->fullName());
        $this->assertSame(1, Category::where('name', 'Dünya Tarihi')->count());
        $this->assertNull(Category::where('name', 'Ah')->first());
        $this->assertTrue($good->fresh()->children()->exists());
    }

    public function test_book_folder_becomes_draft_product(): void
    {
        $this->useOcr(FakeOcrEngine::sample());
        $item = $this->importBook();

        $this->assertSame('imported', $item->status, json_encode($item->issues).' '.$item->error);
        $p = Product::with('publisher', 'author', 'category')->findOrFail($item->product_id);
        $this->assertSame("Almanya'nın Kısa Tarihi", $p->title);
        $this->assertSame('AKT-583', $p->code);
        $this->assertSame('Mary Fulbrook', $p->author->name);
        $this->assertSame('Boğaziçi Üniversitesi Yayınevi', $p->publisher->name);
        $this->assertSame('Okuma Kitapları › Tarih', $p->category->fullName()); // derin dal açılmaz
        $this->assertSame(['Avrupa Tarihi', 'Almanya Tarihi'], $p->topics);
        $this->assertSame('draft', $p->status);                 // fiyat Excel ile gelecek
        $this->assertNotNull($p->image_key);
        Storage::disk('public')->assertExists("products/{$p->image_key}/md.webp");
        Storage::disk('local')->assertMissing($item->storageDir().'/mockup.png'); // özgün mockup silinir
        $this->assertNotNull($item->info_path);                  // bilgi görseli kontrol için saklanır
        $this->assertSame('done', $item->batch->status);
    }

    public function test_same_folder_is_skipped_next_time_and_publisher_is_reused(): void
    {
        $this->useOcr(FakeOcrEngine::sample());
        $this->importBook();

        $res = $this->postJson(route('admin.imports.store'), [
            'name' => 'tekrar',
            'books' => [
                ['publisher_folder' => self::PUB, 'folder' => self::BOOK, 'info' => 'a.png', 'mockup' => 'b.png'],
                ['publisher_folder' => self::PUB, 'folder' => 'BOĞAZİÇİ ÜNİVERSİTESİ YAYINLARI - OSMANLI TARİHİ - HALİL İNALCIK', 'info' => 'a.png', 'mockup' => null],
            ],
        ])->assertOk();
        $this->assertNull($res->json('items.0'));
        $this->assertNotNull($res->json('items.1'));
        $this->assertSame(1, Publisher::count());
    }

    public function test_existing_product_is_updated_by_code_without_touching_prices(): void
    {
        $this->useOcr(FakeOcrEngine::sample());
        $existing = Product::create(['title' => 'Eski ad', 'slug' => 'eski', 'code' => 'AKT-583', 'price_bw' => 120, 'status' => 'active']);
        $item = $this->importBook();

        $this->assertSame('updated', $item->action);
        $existing->refresh();
        $this->assertSame("Almanya'nın Kısa Tarihi", $existing->title);
        $this->assertSame('120.00', $existing->price_bw);
        $this->assertSame('active', $existing->status);
        $this->assertSame(1, Product::count());
    }

    public function test_mismatch_goes_to_review_and_can_be_fixed(): void
    {
        $this->useOcr(FakeOcrEngine::sample([
            'isbn' => ['ISBN / Barkod', '978605423860X'],
            'pages' => ['Sayfa Sayısı', '312'],
        ], 'Osmanlı Tarihi'));
        $engine = $this->app->make(OcrEngine::class);
        $engine->lineAnswers = ['9786054238606', '318']; // ikinci okumalar: ISBN düzeldi, sayfa farklı

        $item = $this->importBook();
        $this->assertSame('review', $item->status);
        $issues = implode(' ', $item->issues);
        $this->assertStringContainsString('Kitap adı uyuşmuyor', $issues);
        $this->assertStringContainsString('Sayfa sayısı iki okumada farklı', $issues);
        $p = $item->product;
        $this->assertSame("Almanya'nın Kısa Tarihi", $p->title);   // klasör adı esas alınır (başlık biçimine çevrilir)
        $this->assertSame('9786054238606', $p->isbn);

        $this->get(route('admin.imports.review', $item))->assertOk()->assertSee('Sayfa sayısı iki okumada farklı')->assertSee('Alt başlık');

        $this->put(route('admin.imports.update', $item), [
            'title' => "Almanya'nın Kısa Tarihi", 'author' => 'Mary Fulbrook', 'publisher' => 'Boğaziçi Üniversitesi Yayınevi',
            'category_id' => Category::where('name', 'Tarih')->value('id'), 'topics' => 'Avrupa Tarihi, Almanya Tarihi', 'isbn' => '978-605-4238-60-6', 'pages' => 318, 'code' => 'akt-583',
            'subtitle' => 'Acemi Bir Boksörün Defterleri',
        ])->assertRedirect(route('admin.imports.show', $item->batch_id));

        $item->refresh();
        $this->assertSame('imported', $item->status);
        $this->assertNotNull($item->reviewed_at);
        $p->refresh();
        $this->assertSame(318, $p->page_count);
        $this->assertSame('Acemi Bir Boksörün Defterleri', $p->subtitle);
        $this->assertSame('AKT-583', $p->code);
        $this->assertSame('Okuma Kitapları › Tarih', $p->category->fullName());
        $this->assertSame(['Avrupa Tarihi', 'Almanya Tarihi'], $p->topics);
    }

    public function test_without_ocr_engine_folder_name_is_used_and_marked_for_review(): void
    {
        $this->useOcr(new FakeOcrEngine(null, [], ready: false));
        $item = $this->importBook();

        $this->assertSame('review', $item->status);
        $p = $item->product;
        $this->assertSame("Almanya'nın Kısa Tarihi", $p->title);
        $this->assertSame('Mary Fulbrook', $p->author->name);
        $this->assertNull($p->category_id);
    }

    public function test_soru_bankasi_folder_without_separators_and_bulk_approve(): void
    {
        $this->useOcr(new FakeOcrEngine(null, [], ready: false));
        // "İ" ayrık yazılmış (I + birleşik nokta) klasör adı; bilgi görseli yok, kapak ürün görseli.
        $folder = "3D YAYINLARI AYT SI\u{0307}STEMLER SORU BANKASI 2024";
        $res = $this->postJson(route('admin.imports.store'), ['name' => 'soru banka', 'books' => [
            ['publisher_folder' => '3D YAYINLARI EKRAN GÖRÜNTÜLERİ', 'folder' => $folder, 'info' => null, 'mockup' => '3D AYT SİSTEMLER KAPAK.png'],
            ['publisher_folder' => '3D YAYINLARI EKRAN GÖRÜNTÜLERİ', 'folder' => '3D YAYINLARI TYT FİZİK VİDEO DESTEKLİ DEFTER', 'info' => null, 'mockup' => null],
        ]])->assertOk();
        [$id, $id2] = $res->json('items');
        $batch = $res->json('batch');
        $this->post(route('admin.imports.upload', [$batch, $id]), ['role' => 'mockup', 'file' => UploadedFile::fake()->image('kapak.png', 600, 800)])->assertOk();

        $item = ImportItem::findOrFail($id);
        $this->assertSame('review', $item->status);
        $this->assertSame('AYT Sistemler Soru Bankası 2024', $item->product->title);
        $this->assertSame(2024, $item->product->print_year);
        $this->assertSame('3D Yayınları', $item->product->publisher->name);
        $this->assertNotNull($item->product->image_key);
        $this->assertSame('review', ImportItem::findOrFail($id2)->status); // görselsiz kitap da atlanmaz, kontrole düşer

        $this->post(route('admin.imports.approve-all', $batch))->assertSessionHas('status');
        $this->assertSame(0, ImportItem::where('batch_id', $batch)->where('status', 'review')->count());
        $this->assertSame('draft', $item->product->fresh()->status);
    }

    public function test_cover_and_imprint_fill_author_year_and_description(): void
    {
        $ocr = $this->useOcr(new FakeOcrEngine(null, []));
        $ocr->pageTexts = [
            'cover' => "TYT\nTARİH\nKONU ÖZETLİ SORU BANKASI\nAycan Söylemez\n> Kavramlarla Konu Özeti\n> Kazanım Odaklı Konu Testi",
            'imprint' => "Bu ürünün bütün hakları 4K YAYINLARI'na aittir.\nBASKI TARİHİ\nMart 2022\nBASKI\nGrupçağ Matbaa",
        ];
        $res = $this->postJson(route('admin.imports.store'), ['name' => 'KİTAPLAR', 'books' => [[
            'publisher_folder' => '4K YAYINLARI EKRAN GÖRÜNTÜLERİ', 'folder' => '4K YAYINLARI TYT TARİH SORU BANKASI', 'info' => null,
            'mockup' => '4K TYT TARİH KAPAK.png', 'cover' => '4K TYT TARİH KAPAK.png', 'imprint' => '4K TYT TARİH BASIM.png',
        ]]])->assertOk();
        [$batch, $id] = [$res->json('batch'), $res->json('items.0')];
        foreach (['mockup', 'cover', 'imprint'] as $role) {
            $this->post(route('admin.imports.upload', [$batch, $id]), ['role' => $role, 'file' => UploadedFile::fake()->image("{$role}.png", 600, 800)])->assertOk();
        }

        $item = ImportItem::findOrFail($id);
        $p = $item->product;
        $this->assertSame('TYT Tarih Soru Bankası', $p->title);
        $this->assertSame('Aycan Söylemez', $p->author->name);
        $this->assertSame(2022, $p->print_year);
        $this->assertSame("• Kavramlarla Konu Özeti\n• Kazanım Odaklı Konu Testi", $p->description);
        // Sınav kitabı: kategori kısa (Sınav Hazırlık › TYT); tür ve ders süzgeç olarak ayrı alanda
        $this->assertSame('Sınav Hazırlık › TYT', $p->category->fullName());
        $this->assertSame('Soru Bankası', $p->book_type);
        $this->assertSame('Tarih', $p->subject);
        $this->assertContains('Yazar kapaktan okundu: “Aycan Söylemez”; doğruluğunu kontrol edin.', $item->issues);
        $this->assertNull($item->cover_path); // okunduktan sonra silinir
        $this->assertStringContainsString('--- Kapak ---', $item->ocr_text);
    }

    public function test_upload_rejects_non_images_and_wrong_batch(): void
    {
        $this->useOcr(FakeOcrEngine::sample());
        $res = $this->postJson(route('admin.imports.store'), [
            'name' => 'x', 'books' => [['publisher_folder' => null, 'folder' => 'KİTAP - YAZAR', 'info' => 'a.png', 'mockup' => null]],
        ]);
        $id = $res->json('items.0');
        $this->postJson(route('admin.imports.upload', [$res->json('batch'), $id]), ['role' => 'info', 'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])
            ->assertStatus(422);

        $other = $this->postJson(route('admin.imports.store'), ['name' => 'y', 'books' => [['folder' => 'BAŞKA - YAZAR', 'info' => 'a.png']]])->json('batch');
        $this->postJson(route('admin.imports.upload', [$other, $id]), ['role' => 'info', 'file' => UploadedFile::fake()->image('a.png')])->assertNotFound();
    }

    public function test_pages_render(): void
    {
        $this->useOcr(FakeOcrEngine::sample());
        $item = $this->importBook();
        $this->get(route('admin.imports.index'))->assertOk()->assertSee('KİTAPLAR');
        $this->get(route('admin.imports.create'))->assertOk()->assertSee('Kök klasörü buraya sürükleyin');
        $this->get(route('admin.imports.show', $item->batch_id))->assertOk()->assertSee(self::BOOK);
        $this->get(route('admin.imports.image', $item))->assertOk();
        $this->getJson(route('admin.imports.status', $item->batch_id))->assertOk()->assertJsonPath('counts.imported', 1);
    }
}
