<?php

namespace Tests\Feature;

use App\Domain\Print\DocumentTools;
use App\Domain\Print\PrintCart;
use App\Domain\Print\PrintOptions;
use App\Domain\Print\PrintPricing;
use App\Domain\Print\PrintSettings;
use App\Models\Admin;
use App\Models\DeliveryDistrict;
use App\Models\Order;
use App\Models\PrintFile;
use App\Models\PrintJob;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Fotokopi: parçalı yükleme, sayfa sayımı, sunucuda fiyat, sepet ve sipariş, saklama süresi, yönetim ayarları. */
class PrintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['print.chunk_bytes' => 1024]);

        // Belge araçları sahte: Word → PDF çevrimi ve sayfa sayısı sunucudaki LibreOffice / poppler olmadan.
        $this->instance(DocumentTools::class, new class extends DocumentTools
        {
            public function toPdf(string $source, string $outDir): string
            {
                file_put_contents($outDir.'/cevrildi.pdf', PrintTest::pdf(3));

                return $outDir.'/cevrildi.pdf';
            }

            public function pageCount(string $pdf): int
            {
                return $this->pageCountFromContent($pdf);
            }

            public function preview(string $pdf, string $outBase): ?string
            {
                return null;
            }
        });
    }

    public static function pdf(int $pages): string
    {
        return "%PDF-1.4\n1 0 obj << /Type /Pages /Count {$pages} >> endobj\n".str_repeat("2 0 obj << /Type /Page /Parent 1 0 R >> endobj\n", $pages)."%%EOF\n";
    }

    /** En küçük geçerli Word belgesi (zip). */
    private static function docx(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>'.str_repeat('Ödev ', 400).'</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();
        $content = (string) file_get_contents($path);
        unlink($path);

        return $content;
    }

    /** Dosyayı parça parça yükler, son durumu döndürür. */
    private function upload(string $name, string $content): array
    {
        $start = $this->postJson(route('print.start'), ['name' => $name, 'size' => strlen($content), 'consent' => 1])->assertOk()->json();
        $offset = 0;
        $state = $start;
        foreach (str_split($content, 1024) as $part) {
            $state = $this->post(route('print.chunk', $start['token']), [
                'offset' => $offset, 'chunk' => UploadedFile::fake()->createWithContent('parca', $part),
            ], ['Accept' => 'application/json'])->assertOk()->json();
            $offset += strlen($part);
        }

        return $state;
    }

    public function test_pricing_tiers_layout_duplex_and_binding(): void
    {
        $pricing = app(PrintPricing::class);
        // 10 sayfa, tek yüz, S-B: 10 yaprak × 2,00
        $this->assertSame('20.00', $pricing->quote(10, new PrintOptions)['total']);
        // Kademe yaprağa göre (1–99: 2,00 · 100–199: 1,50 · 200+: 1,00) — yöneticinin verdiği örnekler:
        $this->assertSame('150.00', $pricing->quote(100, new PrintOptions)['total']);                // 100 sayfa tek yüz = 100 yaprak × 1,50
        $this->assertSame('100.00', $pricing->quote(100, new PrintOptions(duplex: true))['total']);  // 100 sayfa önlü arkalı = 50 yaprak × 2,00
        $this->assertSame('150.00', $pricing->quote(200, new PrintOptions(duplex: true))['total']);  // 200 sayfa önlü arkalı = 100 yaprak × 1,50
        $this->assertSame('198.00', $pricing->quote(99, new PrintOptions)['total']);                 // 99 yaprak hâlâ ilk kademe
        $this->assertSame('200.00', $pricing->quote(200, new PrintOptions)['total']);                // 200 yaprak × 1,00
        $this->assertSame('500.00', $pricing->quote(100, new PrintOptions(color: 'color'))['total']); // renkli 100 yaprak × 5,00
        // Kopya kademeyi değiştirmez: 150 sayfa önlü arkalı = 75 yaprak (ilk kademe), 2 kopya, spiral 40 × 2
        $q = $pricing->quote(150, new PrintOptions(duplex: true, copies: 2, binding: 'spiral'));
        $this->assertSame(75, $q['sheets']);
        $this->assertSame('380.00', $q['total']); // 75 × 2 × 2,00 = 300 + 80
        // Bir yüze 4 sayfa, renkli: 10 sayfa → 3 yüz → 3 yaprak × 6,00
        $this->assertSame('18.00', $pricing->quote(10, new PrintOptions(color: 'color', layout: 4))['total']);
        // Sayfa aralığı kademeyi belirler
        $this->assertSame(5, $pricing->quote(200, new PrintOptions(range: '1-3, 10-11'))['pages']);

        // Sayfa sınırı yok: 800 sayfa da fiyatlanır (özel teklif yok). 800 sayfa önlü arkalı = 400 yaprak × 1,00
        $this->assertSame('400.00', $pricing->quote(800, new PrintOptions(duplex: true))['total']);
        $this->assertSame('5000.00', $pricing->quote(5000, new PrintOptions)['total']);
    }

    public function test_upload_count_quote_add_to_cart_and_order(): void
    {
        $this->get(route('print.index'))->assertOk()->assertSee('Belgeni yükle');

        $pdf = $this->upload('ders-notu.pdf', self::pdf(12));
        $this->assertSame('ready', $pdf['status']);
        $this->assertSame(12, $pdf['pages']);

        $doc = $this->upload('odev.docx', self::docx());
        $this->assertSame(3, $doc['pages']); // sahte çevirici 3 sayfalık PDF üretir
        $this->assertNotNull(PrintFile::where('token', $doc['token'])->value('pdf_path'));

        $quote = $this->postJson(route('print.quote', $pdf['token']), ['color' => 'bw', 'duplex' => '1', 'copies' => 2])->assertOk()->json();
        $this->assertSame('24,00 ₺', $quote['total']); // 12 sayfa önlü arkalı = 6 yaprak × 2 kopya × 2,00
        $this->postJson(route('print.quote', $pdf['token']), ['range' => '5-40'])->assertStatus(422)->assertJsonPath('errors.range.0', fn ($m) => str_contains($m, '1-12'));

        $this->postJson(route('print.add', $pdf['token']), ['color' => 'bw', 'duplex' => '1', 'copies' => 2])->assertOk()->assertJson(['added' => true, 'count' => 1]);
        $this->postJson(route('print.add', $doc['token']), ['color' => 'color', 'binding' => 'spiral'])->assertOk(); // 3 × 6 + 40 = 58

        $this->get(route('cart.index'))->assertOk()->assertSee('ders-notu.pdf')->assertSee('Önlü arkalı')->assertSee('82,00 ₺');

        Settings::put(['delivery_min_order' => null]);
        $district = DeliveryDistrict::where('name', 'Odunpazarı')->firstOrFail();
        $this->post(route('checkout.store'), [
            'name' => 'Öğrenci', 'phone' => '05321234567', 'email' => 'ogr@example.com', 'district_id' => $district->id, 'neighborhood' => 'Vişnelik',
            'address' => 'Kampüs yolu no 3 daire 1', 'slot' => '10:00 - 13:00', 'payment_method' => 'card', 'terms' => '1',
        ])->assertSessionHasNoErrors();

        $order = Order::firstOrFail();
        $this->assertSame('82.00', $order->subtotal);
        $this->assertSame(2, $order->items()->where('kind', 'print')->count());
        $job = PrintJob::where('order_id', $order->id)->firstOrFail();
        $this->assertTrue($job->file->expires_at->gt(now()->addDays(20)), 'Siparişteki belge uzun saklanır');
        $this->assertStringContainsString('Önlü arkalı', $order->items()->where('print_job_id', $job->id)->value('meta')['summary'] ?? json_encode($order->items()->first()->meta));

        // Yönetim baskı dosyasını indirir
        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Baskı dosyasını indir');
        $pdfJob = PrintJob::whereHas('file', fn ($q) => $q->where('extension', 'pdf'))->firstOrFail();
        $this->get(route('admin.print.download', $pdfJob))->assertOk()->assertDownload('ders-notu.pdf');
    }

    public function test_files_belong_to_their_session_and_wrong_content_is_rejected(): void
    {
        $file = $this->upload('notlar.pdf', self::pdf(2));
        $bad = $this->upload('sahte.pdf', 'bu bir pdf değil, düz metin');
        $this->assertSame('failed', $bad['status']);
        $this->assertStringContainsString('uyuşmuyor', $bad['error']);

        $this->postJson(route('print.start'), ['name' => 'virus.exe', 'size' => 10, 'consent' => 1])->assertStatus(422);
        $this->postJson(route('print.start'), ['name' => 'a.pdf', 'size' => 10])->assertStatus(422)->assertJsonValidationErrors('consent');

        // Başka oturum belgeye erişemez
        $this->flushSession();
        $this->getJson(route('print.status', $file['token']))->assertNotFound();
    }

    public function test_cart_line_drops_when_print_disabled_and_prune_deletes_expired(): void
    {
        $file = $this->upload('kitap.pdf', self::pdf(4));
        $this->postJson(route('print.add', $file['token']), [])->assertOk();
        $this->get(route('cart.index'))->assertSee('kitap.pdf');

        $settings = app(PrintSettings::class);
        $settings->save(['enabled' => '0', 'bw_enabled' => '1', 'color_enabled' => '1', 'tiers' => PrintSettings::DEFAULTS['tiers'], 'spiral_price' => '40',
            'a3_multiplier' => '2', 'max_mb' => 200, 'max_copies' => 100, 'unordered_days' => 7, 'ordered_days' => 30]);
        $this->assertFalse(app(PrintSettings::class)->enabled());
        $this->assertNull(app(PrintCart::class)->line(['id' => 1], 'print:1', 'x'));
        $this->get(route('cart.index'))->assertDontSee('kitap.pdf');
        $this->get(route('print.index'))->assertNotFound();

        $model = PrintFile::where('token', $file['token'])->firstOrFail();
        $model->update(['expires_at' => now()->subDay()]);
        $this->artisan('fotokopi:temizle')->assertSuccessful();
        $this->assertSame('deleted', $model->fresh()->status); // baskı işi olduğu için kayıt kalır, dosya silinir
        Storage::disk('local')->assertMissing($model->path);
    }

    public function test_admin_settings_validation(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->get(route('admin.print.edit'))->assertOk()->assertSee('Yaprak başı fiyat kademeleri');

        $form = ['enabled' => '1', 'bw_enabled' => '1', 'color_enabled' => '1', 'spiral_price' => '35,50', 'a3_multiplier' => '2', 'max_mb' => 100,
            'max_copies' => 50, 'unordered_days' => 5, 'ordered_days' => 20,
            'tiers' => [['from' => 1, 'bw' => '1,75', 'color' => '5'], ['from' => 51, 'bw' => '2,00', 'color' => '4,50'], ['from' => '', 'bw' => '', 'color' => '']]];
        $this->put(route('admin.print.update'), $form)->assertSessionHasNoErrors();
        $s = app(PrintSettings::class)->all();
        $this->assertSame('35.50', $s['spiral_price']);
        $this->assertCount(2, $s['tiers']);
        $this->get(route('admin.print.edit'))->assertSee('bir önceki kademeden'); // 51+ S-B fiyatı yüksek uyarısı

        $this->put(route('admin.print.update'), ['tiers' => [['from' => 5, 'bw' => '1', 'color' => '2']]] + $form)->assertSessionHasErrors('tiers');
        $this->put(route('admin.print.update'), ['bw_enabled' => '0', 'color_enabled' => '0'] + $form)->assertSessionHasErrors('color_enabled');
    }

    public function test_admin_toggles_color_and_storefront_shows_single_option(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->get(route('admin.print.edit'))->assertSee('Baskı türleri')->assertSee('Aktif — vitrinde görünüyor');

        // Renkli yazıcı arızası: renkli kapatılır, vitrinde yalnızca siyah-beyaz ortalı rozet olarak görünür.
        $this->post(route('admin.print.color', 'color'))->assertSessionHas('status');
        $this->assertSame(['bw'], app(PrintSettings::class)->colors());
        $this->get(route('print.index'))->assertSee('Siyah-beyaz baskı')->assertDontSee('data-value="color"', false);

        // Ana form kaydı renk durumunu değiştirmez.
        $form = ['enabled' => '1', 'spiral_price' => '40', 'a3_multiplier' => '2', 'max_mb' => 100, 'max_copies' => 50,
            'unordered_days' => 5, 'ordered_days' => 20, 'tiers' => PrintSettings::DEFAULTS['tiers']];
        $this->put(route('admin.print.update'), $form)->assertSessionHasNoErrors();
        $this->assertSame(['bw'], app(PrintSettings::class)->colors());

        // Son açık tür kapatılamaz.
        $this->post(route('admin.print.color', 'bw'))->assertSessionHasErrors('color');
        $this->assertSame(['bw'], app(PrintSettings::class)->colors());

        $this->post(route('admin.print.color', 'color'));
        $this->assertSame(['bw', 'color'], app(PrintSettings::class)->colors());
        $this->post(route('admin.print.color', 'mor'))->assertNotFound();
    }

    public function test_options_reject_invalid_input(): void
    {
        $this->expectException(ValidationException::class);
        PrintOptions::fromInput(['layout' => 3, 'copies' => 0], 10, app(PrintSettings::class));
    }
}
