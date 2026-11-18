<?php

namespace Tests\Feature;

use App\Domain\Orders\OrderStatusService;
use App\Mail\SiteMail;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\ProductReview;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ReviewsAndQuestionsTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::put(['notify_emails' => 'patron@example.com']);
        $this->product = Product::create(['title' => 'TYT Fizik Soru Bankası', 'slug' => 'tyt-fizik', 'price_bw' => 200, 'status' => 'active']);
    }

    private function order(User $user, string $status = 'delivered', ?Product $product = null): Order
    {
        $order = Order::create([
            'number' => 'EF'.Str::upper(Str::random(6)), 'tracking_token' => Str::random(40), 'user_id' => $user->id, 'status' => $status,
            'customer_name' => $user->name, 'phone' => '5321234567', 'email' => $user->email, 'district_name' => 'Odunpazarı',
            'neighborhood' => 'Vişnelik', 'address' => 'Atatürk Cad. 1', 'payment_method' => 'cash', 'subtotal' => 200, 'total' => 200,
        ]);
        $order->items()->create(['kind' => 'product', 'product_id' => ($product ?? $this->product)->id, 'title' => 'TYT Fizik', 'print_type' => 'bw',
            'unit_price' => 200, 'quantity' => 1, 'line_total' => 200]);

        return $order;
    }

    private function review(User $user, array $data = []): TestResponse
    {
        return $this->actingAs($user)->post(route('product.reviews.store', $this->product), $data + ['rating' => 5, 'title' => 'Harika', 'body' => 'Baskısı çok net, ertesi gün geldi.']);
    }

    public function test_only_members_with_a_delivered_order_can_review(): void
    {
        Mail::fake();
        // Misafir: giriş sayfasına
        $this->post(route('product.reviews.store', $this->product), ['rating' => 5, 'body' => 'Çok güzel bir kitap.'])->assertRedirect(route('login'));

        // Ürünü almamış üye
        $stranger = User::factory()->create();
        $this->review($stranger)->assertSessionHasErrors('review');

        // Siparişi yolda olan üye
        $waiting = User::factory()->create();
        $this->order($waiting, 'shipping');
        $this->review($waiting)->assertSessionHasErrors('review');
        $this->get(route('product.show', $this->product))->assertSee(Settings::get('reviews_not_delivered'));

        $this->assertDatabaseCount('product_reviews', 0);
        Mail::assertNothingQueued();
    }

    public function test_review_waits_for_approval_and_mails_flow(): void
    {
        Mail::fake();
        $user = User::factory()->create(['name' => 'ayşe nur kaya']);
        $this->order($user);
        $this->review($user)->assertSessionHasNoErrors()->assertSessionHas('review_status');

        $review = ProductReview::firstOrFail();
        $this->assertSame('pending', $review->status);
        $this->assertNull($review->published_at);
        Mail::assertQueued(SiteMail::class, fn (SiteMail $m) => $m->type === 'review.received' && $m->hasTo($user->email));
        Mail::assertQueued(SiteMail::class, fn (SiteMail $m) => $m->type === 'review.admin' && $m->hasTo('patron@example.com') && str_contains($m->subjectLine, '(5/5)'));
        // Onaysız yayınlanmaz
        $this->get(route('product.show', $this->product))->assertDontSee('class="pf-review"', false);

        // Yönetici yayınlar ve aynı anda cevap yazar: müşteriye tek e-posta
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin')->post(route('admin.reviews.approve', $review), ['reply' => 'Teşekkür ederiz!', 'notify' => '1'])->assertSessionHas('status');
        $review->refresh();
        $this->assertSame('approved', $review->status);
        $this->assertSame('Teşekkür ederiz!', $review->reply);
        Mail::assertQueued(SiteMail::class, fn (SiteMail $m) => $m->type === 'review.published' && $m->hasTo($user->email) && $m->quote === 'Teşekkür ederiz!');
        Mail::assertNotQueued(SiteMail::class, fn (SiteMail $m) => $m->type === 'review.reply');

        $this->product->refresh();
        auth('web')->logout(); // ziyaretçi gözüyle
        $this->assertSame(1, $this->product->rating_count);
        $this->assertSame(5.0, $this->product->rating_avg);
        $this->get(route('product.show', $this->product))->assertSee('Baskısı çok net')->assertSee('Ayşe K.')->assertDontSee('ayşe nur kaya')
            ->assertSee('Teşekkür ederiz!')->assertSee('"aggregateRating"', false);

        // Sonradan cevabı değiştirmek: kutu işaretliyse "cevap verdik" e-postası
        $this->actingAs($admin, 'admin')->put(route('admin.reviews.reply', $review), ['reply' => 'Yeni cevabımız', 'notify' => '1']);
        Mail::assertQueued(SiteMail::class, fn (SiteMail $m) => $m->type === 'review.reply' && $m->quote === 'Yeni cevabımız');
    }

    public function test_editing_a_published_review_keeps_old_version_until_approved(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->order($user);
        $this->review($user);
        $review = ProductReview::firstOrFail();
        $this->actingAs(Admin::factory()->create(), 'admin')->post(route('admin.reviews.approve', $review), ['notify' => '0']);

        $this->review($user, ['rating' => 2, 'body' => 'Fikrim değişti, kapak yırtık geldi.'])->assertSessionHasNoErrors()->assertRedirect();
        $review->refresh();
        $this->assertTrue($review->isPendingEdit());
        Mail::assertQueued(SiteMail::class, fn (SiteMail $m) => $m->type === 'review.admin' && str_contains($m->subjectLine, 'düzenlendi'));
        auth('web')->logout(); // ziyaretçi gözüyle
        $this->get(route('product.show', $this->product))->assertSee('Baskısı çok net')->assertDontSee('kapak yırtık');
        $this->assertSame(5.0, $this->product->fresh()->rating_avg);

        // Düzenleme reddedilirse önceki hâl yayında kalır, müşteriye nedenle haber gider
        $this->actingAs(Admin::first(), 'admin')->post(route('admin.reviews.reject', $review), ['reason' => 'Lütfen siparişle ilgili konular için bize yazın.', 'notify' => '1'])->assertSessionHas('status');
        $this->assertTrue($review->fresh()->isPublished());
        Mail::assertQueued(SiteMail::class, fn (SiteMail $m) => $m->type === 'review.rejected' && $m->quote === 'Lütfen siparişle ilgili konular için bize yazın.');
        $this->assertSame(1, ProductReview::count()); // ürün başına bir değerlendirme
    }

    public function test_auto_publish_sends_published_mail_instead_of_received(): void
    {
        Mail::fake();
        Settings::put(['reviews_auto_publish' => '1']);
        $user = User::factory()->create();
        $this->order($user);
        $this->review($user);
        $this->assertTrue(ProductReview::firstOrFail()->isPublished());
        Mail::assertQueued(SiteMail::class, fn (SiteMail $m) => $m->type === 'review.published' && $m->hasTo($user->email));
        Mail::assertNotQueued(SiteMail::class, fn (SiteMail $m) => $m->type === 'review.received');
    }

    public function test_review_photos_are_reencoded_and_limited(): void
    {
        Mail::fake();
        Storage::fake('public');
        $user = User::factory()->create();
        $this->order($user);
        $this->review($user, ['photos' => [UploadedFile::fake()->image('a.jpg', 1200, 900), UploadedFile::fake()->image('b.png', 600, 600)]])->assertSessionHasNoErrors();

        $review = ProductReview::with('photos')->firstOrFail();
        $this->assertCount(2, $review->photos);
        foreach ($review->photos as $photo) {
            $this->assertStringEndsWith('.webp', $photo->path);
            Storage::disk('public')->assertExists([$photo->path, $photo->thumbPath()]);
            $this->assertFalse($photo->is_approved);
        }

        // Toplam 4'ü aşamaz
        $this->review($user, ['photos' => array_map(fn ($i) => UploadedFile::fake()->image("c{$i}.jpg"), range(1, 3))])->assertSessionHasErrors('photos');
        // Bir fotoğrafı kaldırıp yenisini eklemek
        $this->review($user, ['remove_photos' => [$review->photos[0]->id], 'photos' => [UploadedFile::fake()->image('d.jpg')]])->assertSessionHasNoErrors();
        $this->assertSame(2, $review->photos()->count());
        Storage::disk('public')->assertMissing($review->photos[0]->path);
        // Başkasının fotoğrafı kaldırılamaz
        $this->review($user, ['remove_photos' => [99999]])->assertSessionHasErrors('remove_photos.0');
    }

    public function test_delivered_order_sends_one_review_invite(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $order = $this->order($user, 'shipping');
        $statuses = app(OrderStatusService::class);
        $statuses->change($order, 'delivered');
        Mail::assertQueued(SiteMail::class, fn (SiteMail $m) => $m->type === 'review.invite' && $m->hasTo($user->email) && count($m->links) === 1
            && str_contains($m->links[0][2], '#degerlendirme-yaz'));
        $this->assertNotNull($order->fresh()->review_invited_at);

        // İkinci kez teslim edildi işaretlense de tekrar davet gitmez; misafir siparişe hiç gitmez
        $statuses->change($order->fresh(), 'shipping');
        $statuses->change($order->fresh(), 'delivered');
        $this->assertSame(1, collect(Mail::queued(SiteMail::class))->filter(fn ($m) => $m->type === 'review.invite')->count());
    }

    public function test_account_page_lists_products_waiting_for_review(): void
    {
        $user = User::factory()->create();
        $this->order($user);
        $this->actingAs($user)->get(route('account.reviews'))->assertOk()->assertSee('TYT Fizik Soru Bankası')->assertSee(Settings::get('reviews_rate_cta'));
        $order = Order::firstOrFail();
        $this->get(route('account.orders.show', $order))->assertSee('#degerlendirme-yaz', false);
    }

    public function test_anyone_can_ask_and_answer_flow_sends_mails(): void
    {
        Mail::fake();
        $this->post(route('product.questions.store', $this->product), [
            'name' => 'Mehmet Demir', 'email' => 'Mehmet@Example.com', 'body' => 'Bu kitabın cevap anahtarı var mı?', 'consent' => '1',
        ])->assertSessionHasNoErrors()->assertSessionHas('question_status');

        $question = ProductQuestion::firstOrFail();
        $this->assertSame('mehmet@example.com', $question->email);
        Mail::assertQueued(SiteMail::class, fn (SiteMail $m) => $m->type === 'question.received' && $m->hasTo('mehmet@example.com'));
        Mail::assertQueued(SiteMail::class, fn (SiteMail $m) => $m->type === 'question.admin' && $m->hasTo('patron@example.com') && $m->hasReplyTo('mehmet@example.com'));
        $this->get(route('product.show', $this->product))->assertDontSee('cevap anahtarı var mı');

        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin')->put(route('admin.questions.answer', $question), ['answer' => 'Evet, kitabın sonunda.', 'publish' => '1']);
        $question->refresh();
        $this->assertTrue($question->is_public);
        Mail::assertQueued(SiteMail::class, fn (SiteMail $m) => $m->type === 'question.answered' && $m->hasTo('mehmet@example.com') && str_contains($m->buttonUrl, '#soru-'.$question->id));
        $this->get(route('product.show', $this->product))->assertSee('cevap anahtarı var mı')->assertSee('Evet, kitabın sonunda.')->assertSee('Mehmet D.');

        // Cevabı düzeltmek: kutu işaretsizse yeniden e-posta gitmez
        $this->put(route('admin.questions.answer', $question), ['answer' => 'Evet, kitabın son sayfalarında.', 'publish' => '1']);
        $this->assertSame(1, collect(Mail::queued(SiteMail::class))->filter(fn ($m) => $m->type === 'question.answered')->count());
    }

    public function test_private_answer_is_mailed_but_not_shown_and_deleting_sends_nothing(): void
    {
        Mail::fake();
        $question = ProductQuestion::create(['product_id' => $this->product->id, 'name' => 'Ali Veli', 'email' => 'ali@example.com', 'body' => 'Siparişim ne zaman gelir?']);
        $spam = ProductQuestion::create(['product_id' => $this->product->id, 'name' => 'Spam', 'email' => 'spam@example.com', 'body' => 'Ucuz takipçi satın al']);
        $this->actingAs(Admin::factory()->create(), 'admin')->put(route('admin.questions.answer', $question), ['answer' => 'Yarın teslim edilecek.', 'publish' => '0']);
        Mail::assertQueued(SiteMail::class, fn (SiteMail $m) => $m->type === 'question.answered' && $m->hasTo('ali@example.com'));
        $this->get(route('product.show', $this->product))->assertDontSee('Yarın teslim edilecek.');

        $this->delete(route('admin.questions.destroy', $spam));
        $this->assertModelMissing($spam);
        Mail::assertNotQueued(SiteMail::class, fn (SiteMail $m) => $m->hasTo('spam@example.com'));
    }

    public function test_question_form_has_honeypot_consent_and_rate_limit(): void
    {
        Mail::fake();
        $url = route('product.questions.store', $this->product);
        $base = ['name' => 'Zeynep', 'email' => 'zeynep@example.com', 'body' => 'Renkli baskısı da var mı acaba?', 'consent' => '1'];
        $this->post($url, $base + ['website' => 'http://spam'])->assertSessionHas('question_status');
        $this->post($url, array_diff_key($base, ['consent' => 1]))->assertSessionHasErrors('consent', null, 'question');
        $this->assertDatabaseCount('product_questions', 0);

        foreach (range(1, 3) as $i) {
            $this->post($url, $base)->assertSessionHasNoErrors();
        }
        $this->post($url, $base)->assertSessionHasErrors('body', null, 'question');
        $this->assertDatabaseCount('product_questions', 3);
    }

    public function test_admin_pages_and_settings_render(): void
    {
        $user = User::factory()->create();
        $this->order($user);
        Mail::fake();
        $this->review($user);
        ProductQuestion::create(['product_id' => $this->product->id, 'name' => 'Ali', 'email' => 'ali@example.com', 'body' => 'Kaç sayfa?']);
        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->get(route('admin.reviews.index'))->assertOk()->assertSee('TYT Fizik');
        $this->get(route('admin.reviews.show', ProductReview::first()))->assertOk()->assertSee('Onayla ve yayınla');
        $this->get(route('admin.questions.index'))->assertOk()->assertSee('Kaç sayfa?');
        $this->get(route('admin.questions.show', ProductQuestion::first()))->assertOk()->assertSee('Yalnız e-postayla cevapla');
        $this->get(route('admin.settings.edit', 'degerlendirme'))->assertOk()->assertSee('Değerlendirme daveti');

        $this->put(route('admin.settings.update', 'degerlendirme'), ['reviews_enabled' => '1', 'questions_enabled' => '0', 'mailtpl_question_admin_subject' => '', 'reviews_tab' => 'Yorumlar'])
            ->assertSessionHasNoErrors();
        $this->assertSame('0', Settings::get('questions_enabled'));
        $this->assertSame('0', Settings::get('mailtpl_question_admin_on')); // işaretsiz kutu kapalı sayılır
        $this->assertSame(Settings::DEFAULTS['mailtpl_question_admin_subject'], Settings::get('mailtpl_question_admin_subject'));
        $this->get(route('product.show', $this->product))->assertSee('Yorumlar')->assertDontSee(Settings::get('questions_form_title'));
    }
}
