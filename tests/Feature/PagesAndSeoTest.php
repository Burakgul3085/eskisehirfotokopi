<?php

namespace Tests\Feature;

use App\Domain\Catalog\CategoryTree;
use App\Models\Admin;
use App\Models\Page;
use App\Models\Product;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagesAndSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_pages_render_with_placeholders_and_safe_markdown(): void
    {
        Settings::put(['site_name' => 'Örnek Kırtasiye', 'phone' => '0222 111 22 33']);
        $page = Page::where('key', 'sales')->firstOrFail();
        $page->update(['body' => "## Taraflar\n\nSatıcı: {site_adi} — {telefon}\n\n<script>alert(1)</script>\n\n[kötü](javascript:alert(1))"]);

        $response = $this->get($page->url())->assertOk();
        $response->assertSee('<h2>Taraflar</h2>', false)->assertSee('Satıcı: Örnek Kırtasiye — 0222 111 22 33');
        $response->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('javascript:alert', false);
    }

    public function test_footer_columns_consent_links_and_cookie_notice(): void
    {
        $home = $this->get(route('home'))->assertOk();
        $home->assertSee('Kurumsal')->assertSee('Hakkımızda')->assertSee('Sıkça Sorulan Sorular')->assertSee('x-data="cookieNotice"', false);

        $this->get(route('register'))->assertSee('Üyelik Koşulları')->assertSee('KVKK Aydınlatma Metni');
        $this->get(route('contact'))->assertSee('KVKK Aydınlatma Metni');

        // Gizlenen sayfa alt bilgide ve bağlantılarda görünmez, adresi 404 verir.
        $about = Page::where('key', 'about')->firstOrFail();
        $about->update(['is_active' => false]);
        $this->get(route('home'))->assertDontSee('Hakkımızda');
        $this->get($about->url())->assertNotFound();

        Settings::put(['cookie_notice' => null]);
        $this->get(route('home'))->assertDontSee('x-data="cookieNotice"', false);
    }

    public function test_admin_manages_pages_and_cannot_delete_system_pages(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->get(route('admin.pages.index'))->assertOk()->assertSee('Mesafeli Satış Sözleşmesi');

        $this->post(route('admin.pages.store'), ['title' => 'Kampanyalar', 'body' => 'Okula dönüş indirimi', 'footer_column' => 'help', 'is_active' => '1'])
            ->assertRedirect(route('admin.pages.index'));
        $page = Page::where('slug', 'kampanyalar')->firstOrFail();
        $this->get(route('home'))->assertSee('Kampanyalar');
        $this->get($page->url())->assertSee('Okula dönüş indirimi');

        $this->put(route('admin.pages.update', $page), ['title' => 'Kampanyalar', 'slug' => 'hakkimizda', 'is_active' => '1'])->assertSessionHasErrors('slug');
        $this->delete(route('admin.pages.destroy', $page))->assertRedirect();
        $this->assertModelMissing($page);

        $kvkk = Page::where('key', 'kvkk')->firstOrFail();
        $this->delete(route('admin.pages.destroy', $kvkk))->assertForbidden();
    }

    public function test_sitemap_and_robots(): void
    {
        $category = app(CategoryTree::class)->ensurePath(['KPSS']);
        Product::create(['title' => 'KPSS Tarih', 'slug' => 'kpss-tarih', 'category_id' => $category->id, 'price_bw' => 100, 'status' => 'active']);
        Product::create(['title' => 'Taslak', 'slug' => 'taslak-urun', 'category_id' => $category->id, 'price_bw' => 100, 'status' => 'draft']);

        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        $this->assertStringContainsString(route('product.show', 'kpss-tarih'), $xml);
        $this->assertStringNotContainsString('taslak-urun', $xml);
        $this->assertStringContainsString(route('category.show', $category->slug), $xml);
        $this->assertStringContainsString(route('page.show', 'kvkk-aydinlatma-metni'), $xml);
        $this->assertNotFalse(simplexml_load_string($xml));

        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /yonetim')->assertSee('Sitemap: '.route('sitemap'));
    }
}
