<?php

namespace Tests\Feature;

use App\Domain\Catalog\CategoryMapper;
use App\Domain\Catalog\CategoryPlan;
use App\Domain\Catalog\CategoryTree;
use App\Domain\Import\Ocr\OcrEngine;
use App\Domain\Import\TitleCategory;
use App\Models\Admin;
use App\Models\Category;
use App\Models\CategoryAlias;
use App\Models\ImportItem;
use App\Models\Product;
use App\Models\Publisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\FakeOcrEngine;
use Tests\TestCase;

/** Kısa kategori düzeni: en fazla iki kat; yayınevi, kitap türü ve ders ayrı süzgeç; eski derin ağacın sadeleştirilmesi. */
class FlatCategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function importBook(FakeOcrEngine $ocr, string $folder, array $extra = []): ImportItem
    {
        $this->app->instance(OcrEngine::class, $ocr);
        $book = ['publisher_folder' => 'BOĞAZİÇİ ÜNİVERSİTESİ YAYINEVİ', 'folder' => $folder, 'info' => 'Ekran görüntüsü.png', 'mockup' => 'ChatGPT Görseli.png'];
        $res = $this->postJson(route('admin.imports.store'), ['name' => 'KİTAPLAR', 'books' => [$book]] + $extra)->assertOk();
        foreach (['info' => 'Ekran görüntüsü.png', 'mockup' => 'ChatGPT Görseli.png'] as $role => $name) {
            $this->post(route('admin.imports.upload', [$res->json('batch'), $res->json('items.0')]), ['role' => $role, 'file' => UploadedFile::fake()->image($name, 600, 400)])->assertOk();
        }

        return ImportItem::findOrFail($res->json('items.0'));
    }

    public function test_title_parsing_for_exam_books(): void
    {
        $this->assertSame(['type' => 'Soru Bankası', 'exam' => 'TYT', 'grade' => null, 'subject' => 'Matematik'], TitleCategory::parse('345 TYT Matematik Soru Bankası 2024'));
        $this->assertSame(['type' => 'Konu Anlatımı', 'exam' => null, 'grade' => 'Ara Sınıflar', 'subject' => 'Fizik'], TitleCategory::parse('10. Sınıf Fizik Konu Anlatımı'));
        // Okuma kitabında "deneme" edebî türdür
        $this->assertSame(['type' => null, 'exam' => null, 'grade' => null, 'subject' => null], TitleCategory::parse('Deneme Yazıları'));
    }

    public function test_mapper_picks_short_category_and_keeps_details_as_topics(): void
    {
        $mapper = app(CategoryMapper::class);
        $before = Category::count();

        $r = $mapper->map(['Popüler Bilim', 'Fizik', 'Parçacık Fiziği'], 'Antimadde');
        $this->assertSame('Popüler Bilim', $r['category']->name);
        $this->assertSame(['Fizik', 'Parçacık Fiziği'], $r['topics']);

        // Alias: "Spor Sosyolojisi" → Sosyoloji; ad içinde geçen kategori: "Psikoloji Popüler Bilim" → Psikoloji
        $this->assertSame('Sosyoloji', $mapper->map(['Spor Sosyolojisi'], 'Ruh ve Beden')['category']->name);
        $this->assertSame('Psikoloji', $mapper->map(['Psikoloji Popüler Bilim', 'İnsan Davranışları'], 'Boş Sayfa')['category']->name);

        // Sınav kitabı: kitap adındaki sınav kategoriyi belirler (yayınevi/tür/ders kategori olmaz)
        $this->assertSame('Sınav Hazırlık › AYT', $mapper->map([], '345 AYT Biyoloji Soru Bankası')['category']->fullName());
        $this->assertSame('Sınav Hazırlık › Ara Sınıflar', $mapper->map([], '9. Sınıf Kimya Yaprak Test')['category']->fullName());

        $this->assertSame($before, Category::count()); // buraya kadar yeni kategori açılmadı

        // Tanınmayan yol: kitap kategorisiz kalmaz; ilk adla Diğer Kitaplar altına tek katlı kategori açılır (okuma türü değil)
        $r = $mapper->map(['Kuantum Gastronomisi', 'Moleküler Mutfak'], 'Bilinmeyen');
        $this->assertSame('Diğer Kitaplar › Kuantum Gastronomisi', $r['category']->fullName());
        $this->assertSame(['Moleküler Mutfak'], $r['topics']);
        $this->assertCount(1, $r['created']);
        $this->assertSame($r['category']->id, $mapper->map(['Kuantum Gastronomisi'], 'Başka')['category']->id); // ikinci kez açılmaz
    }

    public function test_exam_book_without_exam_name_goes_to_exam_group(): void
    {
        $mapper = app(CategoryMapper::class);
        // Türü bir okuma kategorisine benzese de ("Edebiyat") soru bankası Sınav Hazırlık'ta kalır
        $r = $mapper->map(['Edebiyat', 'Paragraf'], 'Paragraf Soru Bankası');
        $this->assertSame('Sınav Hazırlık', $r['category']->fullName()); // ipucu yok: grubun kendisi
        $this->assertSame(['Edebiyat'], $r['topics']); // tür ve ders konu olarak tekrarlanmaz
        // Türü sınav grubundaki bir kategoriye uyuyorsa o seçilir
        $this->assertSame('Sınav Hazırlık › KPSS', $mapper->map(['KPSS', 'Tarih'], 'Tarih Konu Anlatımı')['category']->fullName());
        // Varsayılan kategori seçildiyse oraya gider
        $default = Category::where('name', 'TYT')->value('id');
        $this->assertSame($default, $mapper->map([], 'Problemler Yaprak Test', $default)['category']->id);
        // "Defter" ve "Deneme" sınavsız adda okuma kitabıdır
        $this->assertSame('Okuma Kitapları › Sanat ve Tiyatro', $mapper->map(['Sanat'], "Leonardo'nun Defterleri")['category']->fullName());
        $this->assertNull(TitleCategory::parse("Leonardo'nun Defterleri")['type']);
        $this->assertSame('Defter', TitleCategory::parse('TYT Kimya Defter')['type']);
    }

    public function test_starter_list_is_rebuilt_when_all_categories_were_deleted(): void
    {
        $this->withoutStarterCategories();
        $r = app(CategoryMapper::class)->map(['Tarih', 'Avrupa Tarihi'], "Fransa'nın Kısa Tarihi");
        $this->assertSame('Okuma Kitapları › Tarih', $r['category']->fullName());
        $this->assertSame(['Avrupa Tarihi'], $r['topics']);
    }

    public function test_upload_page_default_list_is_never_empty(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->withoutStarterCategories();
        $this->get(route('admin.imports.create'))->assertOk()->assertSee('Varsayılan kategori')->assertSee('Popüler Bilim')->assertSee('TYT');
    }

    public function test_unknown_type_is_automatic_and_a_later_review_choice_is_remembered(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $ocr = FakeOcrEngine::sample(['category' => ['Türü', 'Kuantum Gastronomisi']], 'Mutfakta Fizik');

        // Varsayılan kategori seçildiyse tanınmayan tür oraya gider; kontrol gerekmez
        $default = Category::where('name', 'Edebiyat')->firstOrFail();
        $first = $this->importBook($ocr, 'BOĞAZİÇİ ÜNİVERSİTESİ YAYINEVİ - MUTFAKTA FİZİK - MARY FULBROOK', ['category_id' => $default->id]);
        $this->assertSame('imported', $first->status, json_encode($first->issues));
        $this->assertSame($default->id, $first->product->category_id);

        // Varsayılan yoksa kendiliğinden yeni kategori açılır; yine kontrol gerekmez
        $item = $this->importBook(FakeOcrEngine::sample(['category' => ['Türü', 'Kuantum Gastronomisi']], 'Mutfakta Biyoloji'), 'BOĞAZİÇİ ÜNİVERSİTESİ YAYINEVİ - MUTFAKTA BİYOLOJİ - MARY FULBROOK');
        $this->assertSame('imported', $item->status, json_encode($item->issues));
        $this->assertSame('Diğer Kitaplar › Kuantum Gastronomisi', $item->product->category->fullName());
        $auto = $item->product->category_id;

        // Yönetici isterse kontrol ekranında Popüler Bilim'i seçer → boş kalan otomatik kategori silinir, seçim hatırlanır
        $target = Category::where('name', 'Popüler Bilim')->firstOrFail();
        $this->get(route('admin.imports.review', $item))->assertOk()->assertSee('Kuantum Gastronomisi');
        $this->put(route('admin.imports.update', $item), [
            'title' => 'Mutfakta Fizik', 'author' => 'Mary Fulbrook', 'publisher' => 'Yayınlar',
            'category_id' => $target->id, 'book_type' => '', 'subject' => '', 'topics' => 'Mutfak',
        ])->assertRedirect();
        $this->assertSame($target->id, $item->product->fresh()->category_id);
        $this->assertSame(['Mutfak'], $item->product->fresh()->topics);
        $this->assertSame($target->id, CategoryAlias::where('match_key', 'kuantum gastronomisi')->value('category_id'));
        $this->assertNull(Category::find($auto));

        $second = $this->importBook($ocr, 'BOĞAZİÇİ ÜNİVERSİTESİ YAYINEVİ - MUTFAKTA KİMYA - MARY FULBROOK');
        $this->assertSame($target->id, $second->product->category_id);
    }

    public function test_flatten_command_moves_deep_tree_into_short_list(): void
    {
        $tree = app(CategoryTree::class);
        $deep = $tree->ensurePath(['Tiyatro', 'Oyunculuk', 'Sahne Sanatları']);
        $exam = $tree->ensurePath(['Soru Bankası', 'TYT', 'Tarih']);
        $p1 = Product::create(['title' => 'Görünmez Oyuncu', 'slug' => 'gorunmez-oyuncu', 'category_id' => $deep->id]);
        $p2 = Product::create(['title' => 'TYT Tarih Soru Bankası', 'slug' => 'tyt-tarih', 'category_id' => $exam->id]);

        $this->artisan('kategori:sadelestir', ['--deneme' => true])->assertSuccessful();
        $this->assertSame($deep->id, $p1->fresh()->category_id); // deneme hiçbir şeyi değiştirmez

        $this->artisan('kategori:sadelestir')->assertSuccessful();
        $p1->refresh();
        $p2->refresh();
        $this->assertSame('Okuma Kitapları › Sanat ve Tiyatro', $p1->category->fullName());
        $this->assertSame(['Oyunculuk', 'Sahne Sanatları'], $p1->topics);
        $this->assertSame('Sınav Hazırlık › TYT', $p2->category->fullName());
        $this->assertSame('Soru Bankası', $p2->book_type);
        $this->assertSame('Tarih', $p2->subject);
        $this->assertNull(Category::find($deep->id));
        $this->assertSame(0, Category::where('depth', '>', 1)->count());
    }

    public function test_publisher_page_filters_by_category_type_and_subject(): void
    {
        $pub = Publisher::create(['name' => '345 Yayınları', 'slug' => '345', 'match_key' => '345', 'is_active' => true]);
        $tyt = Category::where('name', 'TYT')->firstOrFail();
        $ayt = Category::where('name', 'AYT')->firstOrFail();
        $make = fn (string $title, Category $c, string $type, string $subject) => Product::create([
            'title' => $title, 'slug' => Str::slug($title), 'publisher_id' => $pub->id, 'category_id' => $c->id,
            'book_type' => $type, 'subject' => $subject, 'price_bw' => 100, 'status' => 'active',
        ]);
        $make('TYT Matematik Soru Bankası', $tyt, 'Soru Bankası', 'Matematik');
        $make('TYT Matematik Deneme', $tyt, 'Deneme', 'Matematik');
        $make('AYT Fizik Soru Bankası', $ayt, 'Soru Bankası', 'Fizik');

        $this->get(route('publisher.show', $pub))->assertOk()
            ->assertSee('TYT')->assertSee('AYT')->assertSee('Kitap türü')->assertSee('Ders')
            ->assertSee('TYT Matematik Deneme')->assertSee('AYT Fizik Soru Bankası');
        $this->get(route('publisher.show', [$pub, 'kategori' => $tyt->id]))->assertOk()
            ->assertSee('TYT Matematik Deneme')->assertDontSee('AYT Fizik Soru Bankası');
        $this->get(route('publisher.show', [$pub, 'kategori' => $tyt->id, 'tur' => 'Soru Bankası']))->assertOk()
            ->assertSee('TYT Matematik Soru Bankası')->assertDontSee('TYT Matematik Deneme');
        $this->get(route('publisher.show', [$pub, 'ders' => 'Fizik']))->assertOk()
            ->assertSee('AYT Fizik Soru Bankası')->assertDontSee('TYT Matematik Soru Bankası');
    }

    public function test_admin_categories_are_limited_to_two_levels(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $child = Category::where('name', 'TYT')->firstOrFail();
        $this->post(route('admin.categories.store'), ['name' => 'Matematik', 'parent_id' => $child->id, 'is_active' => 1])
            ->assertSessionHasErrors('parent_id');
        $this->assertNull(Category::where('name', 'Matematik')->first());

        $group = CategoryPlan::examGroup();
        $this->post(route('admin.categories.store'), ['name' => 'MSÜ', 'parent_id' => $group->id, 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertSame(1, Category::where('name', 'MSÜ')->value('depth'));
    }
}
