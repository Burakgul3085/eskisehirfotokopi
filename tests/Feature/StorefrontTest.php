<?php

namespace Tests\Feature;

use App\Domain\Catalog\CategoryTree;
use App\Domain\Storefront\HomePage;
use App\Models\Admin;
use App\Models\Category;
use App\Models\HomeBlock;
use App\Models\Page;
use App\Models\Product;
use App\Models\Publisher;
use App\Models\SearchMiss;
use App\Models\SearchRedirect;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    private Publisher $palme;

    private Publisher $hiz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutStarterCategories();
        $tree = app(CategoryTree::class);
        $this->palme = Publisher::create(['name' => 'Palme Yayınları', 'slug' => 'palme', 'match_key' => 'palme', 'is_active' => true]);
        $this->hiz = Publisher::create(['name' => 'Hız Yayınları', 'slug' => 'hiz', 'match_key' => 'hiz', 'is_active' => true]);
        $tyt = $tree->ensurePath(['YKS', 'TYT', 'Matematik']);
        $kpss = $tree->ensurePath(['KPSS', 'Tarih']);

        $this->product('TYT Matematik Soru Bankası', $tyt->id, $this->palme->id, 150, ['isbn' => '9786054238606', 'sales_count' => 5]);
        $this->product('TYT Geometri Konu Anlatımı', $tyt->id, $this->hiz->id, 90, ['price_color' => 180]);
        $this->product('KPSS Tarih Denemeleri', $kpss->id, $this->hiz->id, 200);
        $this->product('Taslak Kitap', $tyt->id, $this->palme->id, 50, ['status' => 'draft']);
    }

    private function product(string $title, int $category, int $publisher, float $price, array $extra = []): Product
    {
        return Product::create($extra + [
            'title' => $title, 'slug' => Product::uniqueSlug($title), 'category_id' => $category, 'publisher_id' => $publisher,
            'price_bw' => $price, 'status' => 'active',
        ]);
    }

    public function test_home_page_renders_blocks(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Kategoriler')
            ->assertSee('Yeni eklenenler')
            ->assertSee('TYT Matematik Soru Bankası')
            ->assertDontSee('Taslak Kitap')
            ->assertSee('Sıkça sorulan sorular');

        // Gizlenen blok görünmez; içeriği olmayan ürün satırı (boş kategori) hiç çizilmez.
        HomeBlock::where('type', 'faq')->update(['is_active' => false]);
        HomeBlock::create(['type' => 'products', 'title' => 'Boş satır', 'settings' => ['source' => 'category', 'category_id' => 999999, 'limit' => 8], 'position' => 99, 'is_active' => true]);
        $this->get('/')->assertOk()->assertDontSee('Sıkça sorulan sorular')->assertDontSee('Boş satır');
    }

    public function test_hero_3d_scene_gets_visible_products_with_images(): void
    {
        $this->assertTrue(HomeBlock::where('type', 'hero')->where('is_active', true)->exists());
        Product::where('title', 'KPSS Tarih Denemeleri')->update(['image_key' => 'abc123', 'is_featured' => true]);
        Product::where('title', 'Taslak Kitap')->update(['image_key' => 'gizli']);
        HomePage::flush();

        $html = $this->get('/')->assertOk()->assertSee('data-hero-scene', false)->getContent();
        preg_match('/<script type="application\/json" data-hero-books>(.*?)<\/script>/s', $html, $m);
        $books = json_decode($m[1], true);
        $this->assertSame(['KPSS Tarih Denemeleri'], array_column($books, 'title')); // görselsiz ve taslak ürün yok
        $this->assertStringContainsString('/storage/products/abc123/', $books[0]['img']);
    }

    public function test_home_sections_carry_3d_scene_mounts(): void
    {
        $response = $this->get('/')->assertOk()->assertSee('data-scene="footer-wave"', false);
        foreach (['print_banner' => 'print-banner', 'faq' => 'faq'] as $type => $scene) {
            if (HomeBlock::where('type', $type)->where('is_active', true)->exists()) {
                $response->assertSee('data-scene="'.$scene.'"', false);
            }
        }
    }

    public function test_category_page_lists_subtree_with_filters_and_sorting(): void
    {
        $this->get('/kategori/yks')->assertOk()
            ->assertSee('TYT Matematik Soru Bankası')->assertSee('TYT Geometri Konu Anlatımı')
            ->assertDontSee('KPSS Tarih Denemeleri')->assertDontSee('Taslak Kitap')
            ->assertSee('TYT'); // alt kategori çipi

        $this->get('/kategori/yks?yayinevi[]='.$this->hiz->id)->assertOk()
            ->assertSee('TYT Geometri Konu Anlatımı')->assertDontSee('TYT Matematik Soru Bankası');
        $this->get('/kategori/yks?tip=renkli')->assertOk()->assertSee('TYT Geometri')->assertDontSee('TYT Matematik');
        $this->get('/kategori/yks?min=100')->assertOk()->assertSee('TYT Matematik')->assertDontSee('TYT Geometri');

        $html = $this->get('/kategori/yks?sirala=fiyat-artan')->getContent();
        $this->assertLessThan(strpos($html, 'TYT Matematik Soru'), strpos($html, 'TYT Geometri Konu'));

        Category::where('slug', 'yks')->update(['is_active' => false]);
        app(CategoryTree::class)->flush();
        $this->get('/kategori/yks')->assertRedirect(route('search', ['q' => 'yks'])); // gizli: hata yerine arama
        $this->get('/kategori/tyt')->assertRedirect(route('search', ['q' => 'tyt'])); // üstü gizli
    }

    public function test_product_page_and_draft_preview(): void
    {
        $p = Product::where('title', 'TYT Matematik Soru Bankası')->first();
        $this->get('/urun/'.$p->slug)->assertOk()
            ->assertSee('TYT Matematik Soru Bankası')->assertSee('Palme Yayınları')->assertSee('9786054238606')
            ->assertSee('"@type":"Product"', false)
            ->assertSee('TYT Geometri Konu Anlatımı'); // benzer ürünler
        $this->assertSame(1, $p->fresh()->view_count);

        $draft = Product::where('title', 'Taslak Kitap')->first();
        $this->get('/urun/'.$draft->slug)->assertRedirect(route('search', ['q' => 'taslak kitap']));
        $this->actingAs(Admin::factory()->create(), 'admin')->get('/urun/'.$draft->slug)->assertOk()->assertSee('Önizleme');
    }

    public function test_search_and_suggestions(): void
    {
        $this->get('/ara?q=tyt+mat')->assertOk()->assertSee('TYT Matematik Soru Bankası')->assertDontSee('KPSS Tarih Denemeleri')
            ->assertSee('noindex', false)->assertSee('2 ürün');
        $this->get('/ara?q=tyt')->assertRedirect(route('category.show', Category::where('name', 'TYT')->first())); // kategori adı birebir
        $this->get('/urunler')->assertOk()->assertSee('Tüm ürünler')->assertSee('KPSS Tarih Denemeleri');

        $this->getJson('/ara/oneri?q=tyt')->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(2, 'products')
            ->assertJsonPath('categories.0.title', 'YKS › TYT')
            ->assertJsonPath('products.0.hl', fn ($hl) => str_contains($hl, '<mark class="search-mark">TYT</mark>'));
        $this->getJson('/ara/oneri?q=palme')->assertOk()->assertJsonPath('direct.url', route('publisher.show', $this->palme))
            ->assertJsonPath('direct.type', 'Yayınevi');
        // Kutu boşken öneri yok
        $this->getJson('/ara/oneri?q=')->assertOk()->assertJsonPath('total', 0)->assertJsonPath('direct', null)->assertJsonMissingPath('quick');
    }

    public function test_search_goes_directly_to_exact_names(): void
    {
        $tyt = Product::where('title', 'TYT Matematik Soru Bankası')->first();
        Page::updateOrCreate(['slug' => 'hakkimizda'], ['title' => 'Hakkımızda', 'body' => 'Bize ulaşmak için iletişim sayfası.', 'is_active' => true]);
        $faq = HomeBlock::where('type', 'faq')->first();
        $firstQuestion = collect($faq->setting('items', []))->first(fn ($i) => ! empty($i['q']) && ! empty($i['a']))['q'];

        // Sayfa ve bölüm adları (Türkçe karakter ve büyük/küçük harf farkı gözetilmez)
        $this->get('/ara?q=hakkımızda')->assertRedirect(url('/sayfa/hakkimizda'));
        $this->get('/ara?q=HAKKIMIZDA')->assertRedirect(url('/sayfa/hakkimizda'));
        $this->get('/ara?q=iletisim')->assertRedirect(route('contact'));
        $this->get('/ara?q=sepet')->assertRedirect(route('cart.index'));
        $this->get('/ara?q=sıkça+sorulan+sorular')->assertRedirect(route('home').'#sss');
        $this->get('/ara?q=sss')->assertRedirect(route('home').'#sss');
        // SSS sorusu birebir: o soru açık gelir
        $this->get('/ara?q='.urlencode($firstQuestion))->assertRedirect(route('home').'#sss-1');
        // Kategori, yayınevi, ürün adı ve ISBN
        $this->get('/ara?q=kpss')->assertRedirect(route('category.show', Category::where('slug', 'kpss')->first()));
        $this->get('/ara?q=hız+yayınları')->assertRedirect(route('publisher.show', $this->hiz));
        $this->get('/ara?q=hiz')->assertRedirect(route('publisher.show', $this->hiz));
        $this->get('/ara?q=tyt+matematik+soru+bankasi')->assertRedirect(route('product.show', $tyt));
        $this->get('/ara?q=978-605-4238-60-6')->assertRedirect(route('product.show', $tyt));
        // Tek sonuç kalırsa doğrudan; filtre/sıralama varsa liste kalır
        $geo = Product::where('title', 'TYT Geometri Konu Anlatımı')->first();
        $this->get('/ara?q=geometri')->assertRedirect(route('product.show', $geo));
        $this->get('/ara?q=geometri&sirala=fiyat-artan')->assertOk()->assertSee('TYT Geometri Konu Anlatımı');

        // Bilgi sayfaları yalnız başlığından bulunur (içerikte "iletişim" geçmesi yetmez)
        $this->getJson('/ara/oneri?q=ileti')->assertOk()->assertJsonCount(1, 'pages')->assertJsonPath('pages.0.url', route('contact'));
        $this->getJson('/ara/oneri?q=hakkımızda')->assertOk()->assertJsonPath('direct.url', url('/sayfa/hakkimizda'))->assertJsonPath('direct.type', 'Sayfa');
    }

    public function test_site_search_shows_only_real_matches_and_404_otherwise(): void
    {
        // Yazım düzeltmesi ve "kelimelerden biri" eşleşmesi yok → 404 sayfası; aranan kelime rapora yazılır
        $this->get('/ara?q=matemtik')->assertNotFound()->assertSee('data-scene="not-found"', false)->assertSee('“matemtik”', false);
        $this->get('/ara?q=matemtik')->assertNotFound();
        $this->assertSame(2, SearchMiss::where('match_key', 'matemtik')->value('hits'));
        $this->getJson('/ara/oneri?q=matemtik')->assertOk()->assertJsonCount(0, 'products')->assertJsonPath('direct', null);
        $this->get('/ara?q=kpss+zzqx')->assertNotFound();
        // Hiçbir şey yoksa sade 404 sayfası: 3D sahne, başlık, arama kutusu, ana sayfa; öneri yok
        Product::where('title', 'TYT Matematik Soru Bankası')->update(['image_key' => 'abc123', 'sales_count' => 50]);
        $this->get('/ara?q=asdfgh')->assertNotFound()->assertSee('Aradığınızı')->assertSee('Ana sayfaya dön')
            ->assertDontSee('En çok tercih edilenler')->assertDontSee('TYT Matematik Soru Bankası');
        Settings::put(['notfound_title' => 'Burada bir şey yok']);
        $this->get('/ara?q=asdfgh')->assertNotFound()->assertSee('Burada bir şey yok');

        // Yönetimden yönlendirme: kelime → adres; eklenince "bulunamayan" kaydı silinir, kullanım sayılır
        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->get(route('admin.search.index'))->assertOk()->assertSee('matemtik')->assertSee('kargo');
        $this->post(route('admin.search.store'), ['term' => 'Matemtik', 'url' => '/urunler'])->assertRedirect(route('admin.search.index'));
        $this->post(route('admin.search.store'), ['term' => 'matemtik', 'url' => '/x'])->assertSessionHasErrors('match_key');
        $this->post(route('admin.search.store'), ['term' => 'kötü', 'url' => 'javascript:alert(1)'])->assertSessionHasErrors('url');
        $this->assertNull(SearchMiss::where('match_key', 'matemtik')->first());
        $this->get('/ara?q=matemtik')->assertRedirect('/urunler');
        $this->assertSame(1, SearchRedirect::where('match_key', 'matemtik')->value('hits'));
        $this->get('/ara?q=kargo')->assertRedirect('/siparis-takip');
        $this->delete(route('admin.search.destroy', SearchRedirect::where('match_key', 'matemtik')->first()))->assertRedirect();
        $this->get('/ara?q=matemtik')->assertNotFound();
        $this->delete(route('admin.search.misses.clear'))->assertRedirect();
        $this->assertSame(0, SearchMiss::count());

        // Bulunamayan kayıt aramaya yönlenir; bilinmeyen adres doğrudan 404
        $this->get('/urun/tyt-matematk')->assertRedirect(route('search', ['q' => 'tyt matematk']));
        $this->get('/boyle-bir-sayfa/kimya-kitabi')->assertNotFound()->assertSee('data-scene="not-found"', false);
        $this->get(route('search', ['q' => 'zzqxw yyy']))->assertNotFound();
        $this->get('/yonetim/olmayan')->assertNotFound();
        $this->get('/olmayan.pdf')->assertNotFound();
    }

    public function test_publishers_index_and_page(): void
    {
        $this->get('/yayinevleri')->assertOk()->assertSee('Palme Yayınları')->assertSee('Hız Yayınları')->assertSee('harf-', false)
            ->assertSee('tek tıkla rafında.')->assertSee('Öne çıkan yayınevleri')   // öne çıkan işaretli yoksa en çok ürünü olanlar
            ->assertSee('data-scene="publisher-carousel"', false)->assertSee('"url":', false);
        Settings::put(['publishers_title_accent' => 'Hepsi burada', 'publishers_all' => 'Bütün liste']);
        $this->get('/yayinevleri')->assertSee('Hepsi burada')->assertSee('Bütün liste')->assertDontSee('tek tıkla rafında.');
        $this->get('/yayinevi/hiz')->assertOk()->assertSee('KPSS Tarih Denemeleri')->assertSee('TYT Geometri')->assertDontSee('TYT Matematik Soru');

        $this->hiz->update(['is_active' => false]);
        $this->get('/yayinevi/hiz')->assertRedirect(route('search', ['q' => 'hiz']));
    }

    public function test_menu_links_and_trust_items_come_from_settings(): void
    {
        Settings::put(['header_links' => "Kampanyalar|/kampanya\nkötü satır\nDış|javascript:alert(1)", 'trust_items' => 'shield|Güvenli alışveriş|Kapıda ödeme']);
        $this->assertSame([['label' => 'Kampanyalar', 'url' => '/kampanya']], Settings::links('header_links'));

        $this->get('/')->assertOk()->assertSee('Kampanyalar')->assertSee('Güvenli alışveriş')->assertDontSee('javascript:alert', false);

        // Kayan üst bant ayardan gelir; duyuru başa eklenir; boşsa bant hiç çizilmez.
        Settings::put(['topbar_items' => 'truck|Aynı gün|HIZLI KARGO', 'announcement' => 'Bayramda açığız']);
        $this->get('/')->assertSee('header-ticker', false)->assertSeeInOrder(['Bayramda açığız', 'Aynı gün', 'HIZLI KARGO']);
        Settings::put(['topbar_items' => '', 'announcement' => '']);
        $this->get('/')->assertDontSee('header-ticker', false);
    }

    public function test_admin_manages_home_blocks(): void
    {
        Storage::fake('public');
        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->get(route('admin.home-blocks.index'))->assertOk()->assertSee('Yeni eklenenler');

        // El ile seçilen ürünler: ürün kodu / ISBN / numara karışık yazılabilir, sıra korunur.
        $kpss = Product::where('title', 'KPSS Tarih Denemeleri')->first();
        $this->post(route('admin.home-blocks.store'), [
            'type' => 'products', 'title' => 'Editörün seçtikleri', 'source' => 'manual', 'limit' => 8, 'is_active' => 1,
            'product_list' => $kpss->id."\n978-605-4238-60-6",
        ])->assertRedirect(route('admin.home-blocks.index'));
        $block = HomeBlock::where('title', 'Editörün seçtikleri')->firstOrFail();
        $tyt = Product::where('isbn', '9786054238606')->value('id');
        $this->assertSame([$kpss->id, $tyt], $block->setting('product_ids'));
        $html = $this->get('/')->assertSee('Editörün seçtikleri')->getContent();

        // Slayt görseli yüklenir; sıra değiştirilir; gizlenir; silinince görsel de silinir.
        $this->get(route('admin.home-blocks.create', ['tur' => 'slider']))->assertOk();
        $this->post(route('admin.home-blocks.store'), [
            'type' => 'slider', 'is_active' => 1,
            'slides' => [['file' => UploadedFile::fake()->image('s.jpg', 1600, 530), 'title' => 'Yaz kampanyası', 'url' => '/kategori/yks']],
        ])->assertRedirect();
        $slider = HomeBlock::where('type', 'slider')->firstOrFail();
        $image = $slider->setting('slides.0.image');
        Storage::disk('public')->assertExists($image);
        $this->get('/')->assertSee('Yaz kampanyası');

        $before = $slider->position;
        $this->post(route('admin.home-blocks.move', $slider), ['yon' => 'yukari'])->assertRedirect();
        $this->assertLessThan($before, $slider->fresh()->position);

        $this->post(route('admin.home-blocks.toggle', $slider))->assertRedirect();
        $this->get('/')->assertDontSee('Yaz kampanyası');

        $this->delete(route('admin.home-blocks.destroy', $slider))->assertRedirect();
        Storage::disk('public')->assertMissing($image);
    }
}
