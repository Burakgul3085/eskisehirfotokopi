<?php

namespace Tests\Feature;

use App\Domain\Catalog\CategoryTree;
use App\Models\Category;
use App\Models\Product;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Vitrin kategori menüsü: içinde yayında ürün olmayan kategori gizli, 1 ürün bile varsa görünür; iki bölmeli panel. */
class StorefrontCategoryMenuTest extends TestCase
{
    use RefreshDatabase;

    private function cat(string $name): Category
    {
        return Category::where('name', $name)->firstOrFail();
    }

    private function product(Category $c, string $status = 'active'): Product
    {
        $title = 'Kitap '.Str::random(5);

        return Product::create(['title' => $title, 'slug' => Str::slug($title), 'category_id' => $c->id, 'status' => $status,
            'price_bw' => 1000, 'price_color' => 2000, 'page_count' => 10]);
    }

    private function names(): array
    {
        $out = [];
        foreach (app(CategoryTree::class)->storefront() as $root) {
            $out[$root['name']] = array_column($root['children'], 'name');
        }

        return $out;
    }

    public function test_empty_categories_are_hidden_and_one_published_product_shows_them(): void
    {
        $this->assertSame([], $this->names());

        // Taslak ürün müşteriye görünmediği için kategoriyi göstermez
        $draft = $this->product($this->cat('Tarih'), 'draft');
        $this->assertSame([], $this->names());

        $draft->update(['status' => 'active']);
        $this->assertSame(['Okuma Kitapları' => ['Tarih']], $this->names());

        $p = $this->product($this->cat('TYT'));
        $menu = app(CategoryTree::class)->storefront();
        $this->assertSame(['Okuma Kitapları' => ['Tarih'], 'Sınav Hazırlık' => ['TYT']], $this->names());
        $this->assertSame(1, $menu[1]['count']);

        // Son ürün başka kategoriye taşınınca ya da silinince kategori yine gizlenir
        $p->update(['category_id' => $this->cat('AYT')->id]);
        $this->assertSame(['Okuma Kitapları' => ['Tarih'], 'Sınav Hazırlık' => ['AYT']], $this->names());
        $p->delete();
        $this->assertSame(['Okuma Kitapları' => ['Tarih']], $this->names());

        // Toplu durum değişikliği (sorguyla güncelleme) de yansır
        Product::query()->update(['status' => 'passive']);
        $this->assertSame([], $this->names());
    }

    public function test_header_panel_lists_only_filled_categories_and_no_inline_category_links(): void
    {
        $this->product($this->cat('Felsefe'));
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('id="category-panel"', $html);
        $this->assertStringContainsString(route('category.show', $this->cat('Felsefe')->slug), $html);
        $this->assertStringContainsString(route('category.show', $this->cat('Okuma Kitapları')->slug), $html);
        $this->assertStringNotContainsString(route('category.show', $this->cat('Edebiyat')->slug), $html);
        $this->assertStringNotContainsString(route('category.show', $this->cat('Sınav Hazırlık')->slug), $html);
        $this->assertStringContainsString('Kategori ara…', $html);
        $this->assertStringContainsString('Tümünü gör', $html);
        // Menü çubuğundaki bağlantılar ayarlardan
        $this->assertStringContainsString('Tüm kitaplar', $html);
        $this->assertStringContainsString('Sipariş Takibi', $html);
    }

    public function test_empty_category_page_opens_with_message_and_noindex(): void
    {
        $edebiyat = $this->cat('Edebiyat');
        $this->get(route('category.show', $edebiyat->slug))->assertOk()
            ->assertSee('Bu kategoride henüz ürün yok.')->assertSee('noindex', false);

        $this->product($edebiyat);
        $this->get(route('category.show', $edebiyat->slug))->assertOk()->assertDontSee('Bu kategoride henüz ürün yok.');
        // Ana kategori sayfasının çiplerinde yalnız dolu alt kategoriler
        $this->get(route('category.show', $this->cat('Okuma Kitapları')->slug))->assertOk()
            ->assertSee('Edebiyat')->assertDontSee('>Felsefe', false);
    }

    public function test_menu_texts_come_from_settings(): void
    {
        $this->product($this->cat('Tarih'));
        Settings::put(['menu_all_label' => 'Kategoriler', 'menu_see_all' => 'Hepsini göster', 'category_empty' => 'Yakında burada.']);
        $this->get('/')->assertSee('Kategoriler')->assertSee('Hepsini göster');
        $this->get(route('category.show', $this->cat('Felsefe')->slug))->assertSee('Yakında burada.');
    }
}
