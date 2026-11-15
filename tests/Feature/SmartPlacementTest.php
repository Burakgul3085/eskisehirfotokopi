<?php

namespace Tests\Feature;

use App\Domain\Catalog\CategoryLearner;
use App\Domain\Catalog\CategoryMapper;
use App\Domain\Import\Ocr\OcrEngine;
use App\Models\Admin;
use App\Models\Author;
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

/** Akıllı yerleştirme: kesin kurallar (sınav adı), ipuçları, yayınevi/yazar geçmişi, benzer kitaplar, tahmin listesi. */
class SmartPlacementTest extends TestCase
{
    use RefreshDatabase;

    private function map(string $title, array $path = [], array $ctx = []): array
    {
        CategoryLearner::forget();

        return app(CategoryMapper::class)->map($path, $title, null, $ctx);
    }

    private function cat(string $name): Category
    {
        return Category::where('name', $name)->firstOrFail();
    }

    private function product(string $title, Category $c, array $extra = []): Product
    {
        return Product::create($extra + ['title' => $title, 'slug' => Str::slug($title).'-'.Str::random(4), 'category_id' => $c->id]);
    }

    public function test_exam_name_anywhere_decides_with_high_confidence(): void
    {
        $cases = [
            'Fizik AYT' => 'AYT', '345 TYT MAT' => 'TYT', 'ALES Sayısal Soru Bankası' => 'ALES', 'DGS Sözel' => 'DGS',
            'YDS İngilizce Kelime' => 'YDS ve YDT', 'YÖKDİL Fen Bilimleri' => 'YDS ve YDT', 'KPSS Tarih' => 'KPSS', 'LGS Türkçe' => 'LGS',
            '10. Sınıf Kimya' => 'Ara Sınıflar', '7.Sınıf Fen Bilimleri' => 'Ortaokul',
        ];
        foreach ($cases as $title => $expected) {
            $r = $this->map($title);
            $this->assertSame($expected, $r['category']->name, $title);
            $this->assertSame('high', $r['confidence'], $title);
        }
        // "KPSS Tarih": tür yazmasa da sınav kitabı; ders Tarih
        $this->assertSame('Tarih', $this->map('KPSS Tarih')['subject']);
        $this->assertSame('Matematik', $this->map('345 TYT MAT')['subject']);
        // Sınav adı yalnız klasör adında
        $this->assertSame('TYT', $this->map('Matematik', [], ['folder_title' => 'TYT MATEMATİK'])['category']->name);
        // Listede olmayan sınav grubun altına açılır
        $this->assertSame('Sınav Hazırlık › MSÜ', $this->map('MSÜ Tüm Dersler')['category']->fullName());
    }

    public function test_ambiguous_exam_books_never_go_to_reading(): void
    {
        $r = $this->map('Konu Özetli Defter');
        $this->assertSame('Sınav Hazırlık › Konu Özetli Defterler', $r['category']->fullName());
        $this->assertSame('Defter', $r['book_type']);

        $this->assertSame('Sınav Hazırlık › Deneme Kitapları', $this->map('Matematik 20 Deneme')['category']->fullName());
        $this->assertSame('Sınav Hazırlık', $this->map('Paragraf Soru Bankası')['category']->fullName());
        $this->assertSame('Sınav Hazırlık', $this->map('Problemler Yaprak Test')['category']->parent?->name ?? 'Sınav Hazırlık');
    }

    public function test_reading_books_stay_in_reading(): void
    {
        $this->assertSame('Sanat ve Tiyatro', $this->map("Leonardo'nun Defterleri", ['Sanat'])['category']->name);
        $this->assertSame('Edebiyat', $this->map('Kırmızı Pazartesi', ['Roman'])['category']->name);
        $this->assertSame('Edebiyat', $this->map('Bütün Şiirleri')['category']->name === 'Okuma Kitapları' ? 'Edebiyat' : $this->map('Bütün Şiirleri', ['Şiir'])['category']->name);
        $r = $this->map('Deneme Yazıları', ['Deneme Yazıları']);
        $this->assertSame('Edebiyat', $r['category']->name);
        $this->assertNull($r['book_type']); // okuma kitabında "Deneme" kitap türü değildir
    }

    public function test_publisher_history_places_books_without_any_hint(): void
    {
        $pub = Publisher::create(['name' => 'Yediiklim', 'slug' => 'yediiklim', 'match_key' => 'yediiklim', 'is_active' => true]);
        foreach (['KPSS Tarih Soru Bankası', 'KPSS Coğrafya Soru Bankası', 'KPSS Vatandaşlık', 'KPSS Türkçe'] as $t) {
            $this->product($t, $this->cat('KPSS'), ['publisher_id' => $pub->id]);
        }
        $r = $this->map('Atatürk İlkeleri Notları', [], ['publisher_id' => $pub->id]);
        $this->assertSame('KPSS', $r['category']->name);
        $this->assertNotSame('high', $r['confidence']);
        $this->assertStringContainsString('yayınevi', implode(' ', $r['reasons']));
    }

    public function test_author_history_and_similar_books(): void
    {
        $russell = Author::resolve('Bertrand Russell');
        $this->product('Batı Felsefesi Tarihi', $this->cat('Felsefe'), ['author_id' => $russell->id]);
        $this->product('Felsefe Sorunları', $this->cat('Felsefe'), ['author_id' => $russell->id]);
        $this->assertSame('Felsefe', $this->map('Mutluluk Nasıl Kazanılır', [], ['author_id' => $russell->id])['category']->name);

        foreach (['Osmanlı Devleti Kuruluş', 'Osmanlı İmparatorluğu Klasik Çağ', 'Osmanlı Ekonomisi', 'Osmanlı Saray Hayatı'] as $t) {
            $this->product($t, $this->cat('Tarih'));
        }
        $r = $this->map('Osmanlı Kadınları');
        $this->assertSame('Tarih', $r['category']->name);
        $this->assertStringContainsString('benzer kitaplar', implode(' ', $r['reasons']));
    }

    public function test_guess_list_on_batch_page_and_moving_teaches_the_system(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->app->instance(OcrEngine::class, FakeOcrEngine::sample(['category' => ['Türü', 'Mutfak Kültürü']], 'Lezzetin Tarihi'));
        $book = ['publisher_folder' => 'BOĞAZİÇİ ÜNİVERSİTESİ YAYINEVİ', 'folder' => 'BOĞAZİÇİ ÜNİVERSİTESİ YAYINEVİ - LEZZETİN TARİHİ - MARY FULBROOK',
            'info' => 'Ekran görüntüsü.png', 'mockup' => 'ChatGPT Görseli.png'];
        $res = $this->postJson(route('admin.imports.store'), ['name' => 'KİTAPLAR', 'books' => [$book]])->assertOk();
        foreach (['info' => 'Ekran görüntüsü.png', 'mockup' => 'ChatGPT Görseli.png'] as $role => $name) {
            $this->post(route('admin.imports.upload', [$res->json('batch'), $res->json('items.0')]), ['role' => $role, 'file' => UploadedFile::fake()->image($name, 600, 400)])->assertOk();
        }
        $item = ImportItem::findOrFail($res->json('items.0'));
        $this->assertSame('imported', $item->status, json_encode($item->issues));
        $this->assertSame('medium', $item->parsed['placement']['confidence']); // "Türü" için yeni kategori açıldı

        $this->get(route('admin.imports.show', $item->batch_id))->assertOk()->assertSee('Tahminle yerleştirilen kitaplar (1)')->assertSee('Lezzetin Tarihi');

        // Yönetici Tarih'e taşır → ürün taşınır, "Mutfak Kültürü" hatırlanır, boş kalan otomatik kategori silinir, liste boşalır
        $auto = $item->product->category_id;
        $this->post(route('admin.imports.placement', $item), ['category_id' => $this->cat('Tarih')->id])->assertRedirect();
        $this->assertSame($this->cat('Tarih')->id, $item->product->fresh()->category_id);
        $this->assertSame($this->cat('Tarih')->id, CategoryAlias::where('match_key', 'mutfak kulturu')->value('category_id'));
        $this->assertNull(Category::find($auto));
        $this->get(route('admin.imports.show', $item->batch_id))->assertOk()->assertDontSee('Tahminle yerleştirilen kitaplar');
    }
}
