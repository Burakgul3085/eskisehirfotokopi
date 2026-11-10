<?php

namespace Tests\Feature;

use App\Domain\Catalog\CategoryTree;
use App\Domain\Catalog\PriceSheet;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use App\Models\Publisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class CatalogAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(Admin::factory()->create(), 'admin');
    }

    public function test_category_tree_paths_and_move(): void
    {
        $tree = app(CategoryTree::class);
        $leaf = $tree->ensurePath(['TARİH', 'Avrupa Tarihi', 'Almanya Tarihi']);
        $this->assertSame(2, $leaf->depth);
        $this->assertSame('Tarih › Avrupa Tarihi › Almanya Tarihi', $leaf->fullName());

        // Aynı yol farklı yazımla tekrar gelirse yeni kategori açılmaz.
        $again = $tree->ensurePath(['tarih', 'AVRUPA TARİHİ', 'almanya tarihi']);
        $this->assertTrue($leaf->is($again));
        $this->assertSame(3, Category::count());

        // "Avrupa Tarihi" alt ağacıyla birlikte yeni köke taşınır.
        $kpss = $tree->create(['name' => 'KPSS']);
        $avrupa = Category::where('name', 'Avrupa Tarihi')->first();
        $tree->update($avrupa, ['name' => 'Avrupa Tarihi'], $kpss);
        $leaf->refresh();
        $this->assertSame($kpss->id.'/'.$avrupa->id.'/'.$leaf->id.'/', $leaf->path);
        $this->assertSame(2, $leaf->depth);
        $this->assertSame('KPSS › Avrupa Tarihi › Almanya Tarihi', $leaf->fullName());

        // Kendi altına taşınamaz.
        $this->put("/yonetim/kategoriler/{$avrupa->id}", ['name' => 'Avrupa Tarihi', 'parent_id' => $leaf->id, 'is_active' => '1'])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_category_crud_screens(): void
    {
        $this->get('/yonetim/kategoriler')->assertOk();
        $this->post('/yonetim/kategoriler', ['name' => 'YKS', 'is_active' => '1', 'show_in_menu' => '1'])->assertRedirect('/yonetim/kategoriler');
        $yks = Category::where('name', 'YKS')->firstOrFail();
        $this->post('/yonetim/kategoriler', ['name' => 'TYT', 'parent_id' => $yks->id, 'is_active' => '1'])->assertRedirect();
        $this->post('/yonetim/kategoriler', ['name' => 'tyt', 'parent_id' => $yks->id])->assertSessionHasErrors('name');
        $this->get('/yonetim/kategoriler')->assertSee('TYT');

        $this->delete("/yonetim/kategoriler/{$yks->id}")->assertSessionHasErrors('admin'); // alt kategori var
        $this->get("/yonetim/kategoriler/{$yks->id}/edit")->assertOk();
    }

    public function test_publisher_aliases_and_merge(): void
    {
        $this->post('/yonetim/yayinevleri', ['name' => 'Hız Yayınları', 'is_active' => '1', 'aliases' => "Hız ve Renk\n"])->assertRedirect();
        $hiz = Publisher::firstOrFail();
        $this->assertSame('hiz', $hiz->match_key);
        $this->assertTrue($hiz->is(Publisher::findByName('HIZ VE RENK YAYINLARI')));
        $this->assertTrue($hiz->is(Publisher::findByName('HIZ YAYINEVİ EKRAN GÖRÜNTÜLERİ')));

        $this->post('/yonetim/yayinevleri', ['name' => 'HIZ YAYINLARI'])->assertSessionHasErrors('name');

        $dup = Publisher::create(['name' => 'Hızlı Yayın', 'slug' => 'hizli', 'match_key' => 'hizli']);
        $product = Product::create(['title' => 'Deneme', 'slug' => 'deneme', 'publisher_id' => $dup->id]);
        $this->post("/yonetim/yayinevleri/{$dup->id}/birlestir", ['target_id' => $hiz->id])->assertRedirect();
        $this->assertSame($hiz->id, $product->fresh()->publisher_id);
        $this->assertModelMissing($dup);
        $this->assertTrue($hiz->is(Publisher::findByName('Hızlı Yayınları')));
    }

    public function test_product_create_with_image_and_price_rules(): void
    {
        Storage::fake('public');
        $pub = Publisher::create(['name' => 'Palme', 'slug' => 'palme', 'match_key' => 'palme']);

        // Fiyatsız ürün yayına alınamaz.
        $this->post('/yonetim/urunler', ['title' => 'TYT Matematik', 'status' => 'active'])->assertSessionHasErrors('status');

        $this->post('/yonetim/urunler', [
            'title' => 'TYT Matematik Soru Bankası', 'author' => 'Ali Veli', 'publisher_id' => $pub->id, 'isbn' => '978-605-123-456-7',
            'page_count' => 320, 'price_bw' => '149,90', 'compare_bw' => '199,90', 'status' => 'active',
            'image' => UploadedFile::fake()->image('mockup.png', 1600, 2200),
        ])->assertRedirect();

        $p = Product::firstOrFail();
        $this->assertSame('9786051234567', $p->isbn);
        $this->assertSame('149.90', $p->price_bw);
        $this->assertNull($p->price_color);
        $this->assertSame(['bw'], $p->printTypes());
        $this->assertSame(25, $p->discountPercent('bw'));
        $this->assertNotNull($p->published_at);
        $this->assertSame('Ali Veli', $p->author->name);
        $this->assertStringContainsString('palme', $p->search_text);
        foreach (['sm', 'md', 'lg'] as $size) {
            Storage::disk('public')->assertExists("products/{$p->image_key}/{$size}.webp");
        }
        $this->assertSame(1400, $p->image_width);

        $this->get('/yonetim/urunler?q=tyt+matematik')->assertOk()->assertSee('TYT Matematik Soru Bankası');
        $this->get('/yonetim/urunler?q=9786051234567')->assertSee('TYT Matematik Soru Bankası');
        $this->get("/yonetim/urunler/{$p->id}/edit")->assertOk();
    }

    public function test_bulk_publish_skips_unpriced(): void
    {
        $a = Product::create(['title' => 'A', 'slug' => 'a', 'price_bw' => '10']);
        $b = Product::create(['title' => 'B', 'slug' => 'b']);
        $this->post('/yonetim/urunler/toplu', ['ids' => [$a->id, $b->id], 'action' => 'active'])
            ->assertSessionHas('status', fn ($m) => str_contains($m, '1 ürün fiyatı olmadığı'));
        $this->assertSame('active', $a->fresh()->status);
        $this->assertSame('draft', $b->fresh()->status);
    }

    public function test_bulk_delete_removes_selected_products(): void
    {
        $keep = Product::create(['title' => 'Kalsın', 'slug' => 'kalsin']);
        $ids = collect(range(1, 3))->map(fn ($i) => Product::create(['title' => "Sil {$i}", 'slug' => "sil-{$i}"])->id)->all();
        $this->get(route('admin.products.index'))->assertSee('aria-label="Tümünü seç"', false);

        $this->post(route('admin.products.bulk'), ['ids' => $ids, 'action' => 'delete'])->assertSessionHas('status', '3 ürün silindi.');
        $this->assertSame([$keep->id], Product::pluck('id')->all());
        $this->post(route('admin.products.bulk'), ['action' => 'delete'])->assertSessionHasErrors('ids'); // seçim yoksa uyarı
    }

    public function test_bulk_delete_publishers_skips_ones_with_products(): void
    {
        $empty = collect(['a', 'b'])->map(fn ($k) => Publisher::create(['name' => "Boş {$k}", 'slug' => "bos-{$k}", 'match_key' => "bos{$k}"]));
        $used = Publisher::create(['name' => 'Dolu', 'slug' => 'dolu', 'match_key' => 'dolu']);
        Product::create(['title' => 'X', 'slug' => 'x', 'publisher_id' => $used->id]);
        $this->get(route('admin.publishers.index'))->assertSee('form="publisher-bulk"', false);

        $this->post(route('admin.publishers.bulk-destroy'), ['ids' => [...$empty->pluck('id'), $used->id]])
            ->assertSessionHas('status', fn ($m) => str_starts_with($m, '2 yayınevi silindi.') && str_contains($m, 'Dolu'));
        $this->assertSame([$used->id], Publisher::pluck('id')->all());
        $this->post(route('admin.publishers.bulk-destroy'))->assertSessionHasErrors('ids');
    }

    public function test_bulk_delete_categories_deepest_first(): void
    {
        $tree = app(CategoryTree::class);
        $leaf = $tree->ensurePath(['Ana', 'Alt', 'En Alt']);
        $alt = $leaf->parent;
        $ana = $alt->parent;
        $dolu = $tree->ensurePath(['Dolu']);
        Product::create(['title' => 'Y', 'slug' => 'y', 'category_id' => $dolu->id]);
        $this->get(route('admin.categories.index'))->assertSee('form="category-bulk"', false);

        // Dal tümüyle seçildi → üçü de silinir; ürünlü kategori kalır.
        $this->post(route('admin.categories.bulk-destroy'), ['ids' => [$ana->id, $alt->id, $leaf->id, $dolu->id]])
            ->assertSessionHas('status', fn ($m) => str_starts_with($m, '3 kategori silindi.') && str_contains($m, 'Dolu'));
        $this->assertSame([$dolu->id], Category::pluck('id')->all());

        // Alt kategorisi seçilmeyen üst kategori silinmez.
        $child = $tree->ensurePath(['Üst', 'Çocuk']);
        $this->post(route('admin.categories.bulk-destroy'), ['ids' => [$child->parent_id]])
            ->assertSessionHas('status', fn ($m) => str_starts_with($m, '0 kategori silindi.'));
        $this->assertSame(3, Category::count());
    }

    public function test_price_sheet_roundtrip(): void
    {
        $a = Product::create(['title' => 'Kitap A', 'slug' => 'a', 'price_bw' => '50']);
        $b = Product::create(['title' => 'Kitap B', 'slug' => 'b']);
        $c = Product::create(['title' => 'Kitap C', 'slug' => 'c', 'price_bw' => '70', 'price_color' => '90']);

        // Dışa aktarma: yalnızca fiyatı eksikler.
        $response = $this->get('/yonetim/urunler/fiyat-sablonu?fiyat=eksik')->assertOk();
        $exported = $response->baseResponse->getFile()->getPathname();
        $rows = $this->readRows($exported);
        $this->assertSame('ID', $rows[0][0]);
        $this->assertCount(2, $rows); // başlık + B

        // Doldurulmuş şablon: B'ye iki fiyat, A'nın renklisi, C'nin renklisi silinir, geçersiz satır raporlanır.
        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        $w = new Writer;
        $w->openToFile($path);
        $w->addRow(Row::fromValues(PriceSheet::HEADERS));
        $w->addRow(Row::fromValues([$b->id, '', '', 'Kitap B', '', '', '', '', '120,50', 160, '', '']));
        $w->addRow(Row::fromValues([$a->id, '', '', 'Kitap A', '', '', '', '', '', '75', '', '']));
        $w->addRow(Row::fromValues([$c->id, '', '', 'Kitap C', '', '', '', '', '', '-', '', '']));
        $w->addRow(Row::fromValues([999999, '', '', 'Yok', '', '', '', '', '10', '', '', '']));
        $w->addRow(Row::fromValues([$a->id, '', '', 'Kitap A', '', '', '', '', 'abc', '', '', '']));
        $w->close();

        $this->post('/yonetim/urunler/fiyat-yukle', ['file' => new UploadedFile($path, 'fiyat.xlsx', null, null, true), 'publish' => '1'])
            ->assertSessionHas('status')->assertSessionHas('import_errors', fn ($e) => count($e) === 2);

        $b->refresh();
        $this->assertSame('120.50', $b->price_bw);
        $this->assertSame('160.00', $b->price_color);
        $this->assertSame('active', $b->status);
        $this->assertSame('75.00', $a->fresh()->price_color);
        $this->assertSame('50.00', $a->fresh()->price_bw); // boş hücre değiştirmez
        $this->assertNull($c->fresh()->price_color);
    }

    private function readRows(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            break;
        }
        $reader->close();

        return $rows;
    }
}
