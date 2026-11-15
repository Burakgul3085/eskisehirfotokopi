<?php

namespace Tests\Feature;

use App\Domain\Catalog\CategoryLearner;
use App\Domain\Catalog\CategoryMapper;
use App\Domain\Catalog\CategoryTree;
use App\Models\Admin;
use App\Models\Category;
use App\Models\CategoryMove;
use App\Models\PlacementRule;
use App\Models\Product;
use App\Models\Publisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Ürün listesinden aramayla toplu kategori taşıma: tümünü seç, yeni kategori, onay sayısı, yerleştirme kuralı, geri al. */
class BulkMoveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(Admin::factory()->create(), 'admin');
    }

    private function cat(string $name): Category
    {
        return Category::where('name', $name)->firstOrFail();
    }

    private function product(string $title, ?Category $c, array $extra = []): Product
    {
        return Product::create($extra + ['title' => $title, 'slug' => Str::slug($title).'-'.Str::random(6), 'category_id' => $c?->id]);
    }

    private function map(string $title, array $path = [], array $ctx = []): array
    {
        CategoryLearner::forget();

        return app(CategoryMapper::class)->map($path, $title, null, $ctx);
    }

    public function test_list_filters_by_subject_and_type(): void
    {
        $tyt = $this->cat('TYT');
        $this->product('TYT Biyoloji Soru Bankası', $tyt, ['subject' => 'Biyoloji', 'book_type' => 'Soru Bankası']);
        $this->product('TYT Biyoloji Konu Anlatımı', $tyt, ['subject' => 'Biyoloji', 'book_type' => 'Konu Anlatımı']);
        $this->product('TYT Fizik Soru Bankası', $tyt, ['subject' => 'Fizik', 'book_type' => 'Soru Bankası']);

        $this->get(route('admin.products.index', ['ders' => 'Biyoloji']))->assertOk()
            ->assertSee('TYT Biyoloji Soru Bankası')->assertSee('TYT Biyoloji Konu Anlatımı')->assertDontSee('TYT Fizik Soru Bankası')
            ->assertSee('Tüm dersler')->assertSee('Tüm kitap türleri')->assertSee('Aramaya uyan tüm 2 ürünü seç')
            ->assertSee('Ders: Biyoloji');
        $this->get(route('admin.products.index', ['ders' => 'Biyoloji', 'tur' => 'Soru Bankası']))->assertOk()
            ->assertSee('TYT Biyoloji Soru Bankası')->assertDontSee('TYT Biyoloji Konu Anlatımı');
    }

    public function test_select_all_matching_moves_every_product_beyond_the_page(): void
    {
        $tyt = $this->cat('TYT');
        $ayt = $this->cat('AYT');
        $pub = Publisher::create(['name' => '3D Yayınları', 'slug' => '3d', 'match_key' => '3d']);
        for ($i = 1; $i <= 60; $i++) {
            $this->product("Biyoloji Test {$i}", $i % 2 ? $tyt : $ayt, ['subject' => 'Biyoloji', 'publisher_id' => $pub->id]);
        }
        $other = $this->product('Fizik Test', $tyt, ['subject' => 'Fizik']);
        $parent = $this->cat('Sınav Hazırlık');

        // Onaydaki sayı değiştiyse (ör. 59 sanılan arama şimdi 60) hiçbir şey taşınmaz
        $this->post(route('admin.products.bulk'), [
            'scope' => 'all', 'f' => ['ders' => 'Biyoloji'], 'expected' => 59, 'action' => 'category',
            'category_id' => 'new', 'new_category_name' => 'Biyoloji', 'new_category_parent' => $parent->id,
        ])->assertSessionHasErrors('ids');
        $this->assertSame(0, Category::where('name', 'Biyoloji')->where('parent_id', $parent->id)->count());

        $this->post(route('admin.products.bulk'), [
            'scope' => 'all', 'ids' => [1], 'f' => ['ders' => 'Biyoloji'], 'expected' => 60, 'action' => 'category',
            'category_id' => 'new', 'new_category_name' => '  Biyoloji ', 'new_category_parent' => $parent->id,
        ])->assertSessionHasNoErrors()->assertSessionHas('status');

        $bio = Category::where('name', 'Biyoloji')->where('parent_id', $parent->id)->firstOrFail();
        $this->assertSame(1, $bio->depth);
        $this->assertSame(60, Product::where('category_id', $bio->id)->count());
        $this->assertSame($tyt->id, $other->fresh()->category_id);
        $this->assertStringContainsString('biyoloji', Product::where('category_id', $bio->id)->first()->search_text);

        // Aynı adla yeniden "yeni kategori" açılmaz, var olana taşınır; zaten orada olanlar sayılmaz
        $this->post(route('admin.products.bulk'), [
            'scope' => 'all', 'f' => ['ders' => 'Biyoloji'], 'action' => 'category',
            'category_id' => 'new', 'new_category_name' => 'biyoloji', 'new_category_parent' => $parent->id,
        ])->assertSessionHas('status', fn ($s) => str_contains($s, '0 ürün') && str_contains($s, '60 ürün zaten oradaydı'));
        $this->assertSame(1, Category::where('normalized_name', 'biyoloji')->where('parent_id', $parent->id)->count());
    }

    public function test_new_category_only_under_a_top_level_category(): void
    {
        $p = $this->product('Biyoloji', $this->cat('TYT'));
        $this->post(route('admin.products.bulk'), [
            'ids' => [$p->id], 'action' => 'category', 'category_id' => 'new', 'new_category_name' => 'X', 'new_category_parent' => $this->cat('TYT')->id,
        ])->assertSessionHasErrors('new_category_parent');
        $this->post(route('admin.products.bulk'), [
            'ids' => [$p->id], 'action' => 'category', 'category_id' => 'new', 'new_category_parent' => $this->cat('Sınav Hazırlık')->id,
        ])->assertSessionHasErrors('new_category_name');
        $this->post(route('admin.products.bulk'), ['ids' => [$p->id], 'action' => 'category', 'category_id' => 999999])->assertSessionHasErrors('category_id');
        $this->assertSame($this->cat('TYT')->id, $p->fresh()->category_id);
    }

    public function test_selected_ids_move_only_those_products(): void
    {
        $tyt = $this->cat('TYT');
        $a = $this->product('A Biyoloji', $tyt, ['subject' => 'Biyoloji']);
        $b = $this->product('B Biyoloji', $tyt, ['subject' => 'Biyoloji']);
        $this->post(route('admin.products.bulk'), ['scope' => 'selected', 'ids' => [$a->id], 'f' => ['ders' => 'Biyoloji'], 'action' => 'category', 'category_id' => $this->cat('AYT')->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($this->cat('AYT')->id, $a->fresh()->category_id);
        $this->assertSame($tyt->id, $b->fresh()->category_id);
    }

    public function test_rule_sends_future_books_there_before_the_exam_rule(): void
    {
        $tyt = $this->cat('TYT');
        $this->product('TYT Biyoloji Soru Bankası', $tyt, ['subject' => 'Biyoloji']);
        $this->assertSame('AYT', $this->map('AYT Biyoloji Soru Bankası')['category']->name);

        $this->post(route('admin.products.bulk'), [
            'scope' => 'all', 'f' => ['ders' => 'Biyoloji'], 'action' => 'category', 'save_rule' => '1',
            'category_id' => 'new', 'new_category_name' => 'Biyoloji', 'new_category_parent' => $this->cat('Sınav Hazırlık')->id,
        ])->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, 'Kural kaydedildi'));
        $rule = PlacementRule::sole();
        $this->assertSame(['subject' => 'Biyoloji'], $rule->conditions);
        $this->assertSame('Ders: Biyoloji', $rule->label);

        // Sınav adı geçse de kural önce gelir; ders kısaltması da tanınır
        $r = $this->map('AYT Biyoloji Soru Bankası');
        $this->assertSame('Sınav Hazırlık › Biyoloji', $r['category']->fullName());
        $this->assertSame('high', $r['confidence']);
        $this->assertStringContainsString('Yerleştirme kuralı: Ders: Biyoloji', $r['reasons'][0]);
        $this->assertSame($rule->id, $r['rule']);
        $this->assertSame('Biyoloji', $r['subject']);
        // Kurala uymayanlar eskisi gibi
        $this->assertSame('AYT', $this->map('AYT Fizik Soru Bankası')['category']->name);
        $this->assertNotSame('Biyoloji', $this->map('Kırmızı Pazartesi', ['Roman'])['category']->name);

        // Kategoriler sayfasında listelenir ve silinir
        $this->get(route('admin.categories.index'))->assertOk()->assertSee('Yerleştirme kuralları')->assertSee('Ders: Biyoloji');
        $this->delete(route('admin.categories.rules.destroy', $rule))->assertSessionHas('status');
        $this->assertSame(0, PlacementRule::count());
        $this->assertSame('AYT', $this->map('AYT Biyoloji Soru Bankası')['category']->name);
    }

    public function test_text_publisher_and_category_conditions(): void
    {
        $pub = Publisher::create(['name' => 'Palme Yayınları', 'slug' => 'palme', 'match_key' => 'palme']);
        $target = $this->cat('Konu Özetli Defterler');
        $rule = PlacementRule::create(['conditions' => ['q' => 'kimya', 'publisher_id' => $pub->id], 'label' => 'x', 'category_id' => $target->id]);

        $this->assertSame($target->id, $this->map('Organik Kimya Notları', [], ['publisher_id' => $pub->id])['category']->id);
        $this->assertNotSame($target->id, $this->map('Organik Kimya Notları')['category']?->id); // yayınevi tutmuyor
        $rule->delete();

        // Kategori koşulu otomatik yerleştirmenin sonucuna bakar: Sınav Hazırlık'a düşen "geometri" kitapları → hedef
        $geo = app(CategoryTree::class)->create(['name' => 'Geometri'], $this->cat('Sınav Hazırlık'));
        PlacementRule::create(['conditions' => ['q' => 'geometri', 'category_id' => $this->cat('Sınav Hazırlık')->id], 'label' => 'y', 'category_id' => $geo->id]);
        $this->assertSame($geo->id, $this->map('TYT Geometri Soru Bankası')['category']->id);
        $this->assertSame('high', $this->map('TYT Geometri Soru Bankası')['confidence']);
        // Okuma kitabına düşen aynı kelimeli kitaba dokunmaz
        $this->assertNotSame($geo->id, $this->map('Geometrinin Tarihi', ['Bilim Tarihi'])['category']?->id);
    }

    public function test_rule_needs_a_meaningful_search(): void
    {
        $p = $this->product('Bir Kitap', $this->cat('TYT'), ['status' => 'draft']);
        $this->post(route('admin.products.bulk'), [
            'scope' => 'all', 'f' => ['durum' => 'draft'], 'action' => 'category', 'save_rule' => '1', 'category_id' => $this->cat('AYT')->id,
        ])->assertSessionHasErrors('save_rule');
        $this->assertSame($this->cat('TYT')->id, $p->fresh()->category_id);
        $this->assertSame(0, PlacementRule::count());

        // Kural kitapları zaten bulundukları kategoriye taşıyacaksa kaydedilmez
        $this->post(route('admin.products.bulk'), [
            'scope' => 'all', 'f' => ['kategori' => (string) $this->cat('TYT')->id], 'action' => 'category', 'save_rule' => '1', 'category_id' => $this->cat('TYT')->id,
        ])->assertSessionHasErrors('save_rule');
        $this->assertSame(0, PlacementRule::count());
    }

    public function test_undo_restores_old_categories_and_removes_new_category_and_rule(): void
    {
        $tyt = $this->cat('TYT');
        $ayt = $this->cat('AYT');
        $a = $this->product('A Biyoloji', $tyt, ['subject' => 'Biyoloji']);
        $b = $this->product('B Biyoloji', $ayt, ['subject' => 'Biyoloji']);
        $c = $this->product('C Biyoloji', null, ['subject' => 'Biyoloji']);
        $this->post(route('admin.products.bulk'), [
            'scope' => 'all', 'f' => ['ders' => 'Biyoloji'], 'action' => 'category', 'save_rule' => '1',
            'category_id' => 'new', 'new_category_name' => 'Biyoloji', 'new_category_parent' => $this->cat('Sınav Hazırlık')->id,
        ])->assertSessionHasNoErrors();
        $move = CategoryMove::sole();
        $this->assertSame(3, $move->count);
        $this->get(route('admin.products.index'))->assertSee('Son toplu taşıma')->assertSee('Sınav Hazırlık › Biyoloji');

        $this->post(route('admin.products.undo-move', $move))->assertSessionHas('status', fn ($s) => str_contains($s, '3 ürün') && str_contains($s, 'silindi'));
        $this->assertSame($tyt->id, $a->fresh()->category_id);
        $this->assertSame($ayt->id, $b->fresh()->category_id);
        $this->assertNull($c->fresh()->category_id);
        $this->assertSame(0, PlacementRule::count());
        $this->assertFalse(Category::where('name', 'Biyoloji')->exists());
        $this->assertNotNull($move->fresh()->undone_at);
        $this->post(route('admin.products.undo-move', $move))->assertSessionHasErrors('ids');
        $this->get(route('admin.products.index'))->assertDontSee('Son toplu taşıma');
    }

    public function test_undo_skips_products_moved_again_and_keeps_used_category(): void
    {
        $tyt = $this->cat('TYT');
        $a = $this->product('A Biyoloji', $tyt, ['subject' => 'Biyoloji']);
        $b = $this->product('B Biyoloji', $tyt, ['subject' => 'Biyoloji']);
        $this->post(route('admin.products.bulk'), [
            'ids' => [$a->id, $b->id], 'action' => 'category', 'category_id' => 'new', 'new_category_name' => 'Biyoloji', 'new_category_parent' => $this->cat('Sınav Hazırlık')->id,
        ])->assertSessionHasNoErrors();
        $bio = Category::where('name', 'Biyoloji')->firstOrFail();
        $b->update(['category_id' => $this->cat('AYT')->id]); // sonradan elle taşındı
        $other = $this->product('Başka', $bio); // yeni kategoriye başka ürün de kondu

        $this->post(route('admin.products.undo-move', CategoryMove::sole()))->assertSessionHas('status', fn ($s) => str_contains($s, '1 ürün eski') && str_contains($s, '1 ürün o arada'));
        $this->assertSame($tyt->id, $a->fresh()->category_id);
        $this->assertSame($this->cat('AYT')->id, $b->fresh()->category_id);
        $this->assertTrue(Category::whereKey($bio->id)->exists());
        $this->assertSame($bio->id, $other->fresh()->category_id);
    }

    public function test_existing_rule_retarget_is_reverted_on_undo(): void
    {
        $tyt = $this->cat('TYT');
        $this->product('A Kimya', $tyt, ['subject' => 'Kimya']);
        $rule = PlacementRule::create(['conditions' => ['subject' => 'Kimya'], 'label' => 'Ders: Kimya', 'category_id' => $tyt->id]);
        $this->post(route('admin.products.bulk'), ['scope' => 'all', 'f' => ['ders' => 'Kimya'], 'action' => 'category', 'save_rule' => '1', 'category_id' => $this->cat('AYT')->id])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, PlacementRule::count());
        $this->assertSame($this->cat('AYT')->id, $rule->fresh()->category_id);
        $this->post(route('admin.products.undo-move', CategoryMove::sole()));
        $this->assertSame($tyt->id, $rule->fresh()->category_id);
    }

    public function test_other_bulk_actions_work_on_all_matching(): void
    {
        $tyt = $this->cat('TYT');
        $a = $this->product('A Biyoloji', $tyt, ['subject' => 'Biyoloji', 'status' => 'active']);
        $b = $this->product('B Fizik', $tyt, ['subject' => 'Fizik', 'status' => 'active']);
        $this->post(route('admin.products.bulk'), ['scope' => 'all', 'f' => ['ders' => 'Biyoloji'], 'action' => 'passive'])->assertSessionHasNoErrors();
        $this->assertSame('passive', $a->fresh()->status);
        $this->assertSame('active', $b->fresh()->status);
        $this->post(route('admin.products.bulk'), ['scope' => 'all', 'f' => ['ders' => 'Kimya'], 'action' => 'passive'])->assertSessionHasErrors('ids');
        $this->post(route('admin.products.bulk'), ['action' => 'passive'])->assertSessionHasErrors('ids');
    }
}
