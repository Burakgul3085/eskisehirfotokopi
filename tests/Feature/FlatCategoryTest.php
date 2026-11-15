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
        $book = ['publisher_folder' => 'YAYINLAR', 'folder' => $folder, 'info' => 'Ekran görüntüsü.png', 'mockup' => 'ChatGPT Görseli.png'];
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

        // Tanınmayan yol: yeni kategori açılmaz
        $r = $mapper->map(['Kuantum Gastronomisi'], 'Bilinmeyen');
        $this->assertNull($r['category']);
        $this->assertFalse($r['matched']);
        $this->assertSame($before, Category::count());
    }

    public function test_unmatched_book_uses_default_category_and_review_choice_is_remembered(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $default = Category::where('name', 'Edebiyat')->firstOrFail();
        $ocr = FakeOcrEngine::sample(['category' => ['Türü', 'Kuantum Gastronomisi']], 'Mutfakta Fizik');
        $item = $this->importBook($ocr, 'YAYINLAR - MUTFAKTA FİZİK - MARY FULBROOK', ['category_id' => $default->id]);

        $this->assertSame('review', $item->status);
        $this->assertStringContainsString('eşlenemedi', implode(' ', $item->issues));
        $this->assertSame($default->id, $item->product->category_id);

        // Yönetici kontrol ekranında Popüler Bilim'i seçer → "Kuantum Gastronomisi" artık oraya gider
        $target = Category::where('name', 'Popüler Bilim')->firstOrFail();
        $this->get(route('admin.imports.review', $item))->assertOk()->assertSee('Kuantum Gastronomisi');
        $this->put(route('admin.imports.update', $item), [
            'title' => 'Mutfakta Fizik', 'author' => 'Mary Fulbrook', 'publisher' => 'Yayınlar',
            'category_id' => $target->id, 'book_type' => '', 'subject' => '', 'topics' => 'Mutfak',
        ])->assertRedirect();
        $this->assertSame($target->id, $item->product->fresh()->category_id);
        $this->assertSame(['Mutfak'], $item->product->fresh()->topics);
        $this->assertSame($target->id, CategoryAlias::where('match_key', 'kuantum gastronomisi')->value('category_id'));

        $second = $this->importBook($ocr, 'YAYINLAR - MUTFAKTA KİMYA - MARY FULBROOK');
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
