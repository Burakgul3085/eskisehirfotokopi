<?php

namespace Tests\Feature;

use App\Domain\Catalog\CategoryTree;
use App\Mail\SiteMail;
use App\Models\Admin;
use App\Models\DeliveryDistrict;
use App\Models\MailLog;
use App\Models\MessageThread;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailAndMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::put(['notify_emails' => 'patron@example.com, kasa@example.com', 'email' => 'info@example.com']);
    }

    private function placeOrder(): Order
    {
        $category = app(CategoryTree::class)->ensurePath(['YKS']);
        $book = Product::create(['title' => 'TYT Fizik', 'slug' => 'tyt-fizik', 'category_id' => $category->id, 'price_bw' => 200, 'status' => 'active']);
        $this->post(route('cart.add'), ['product_id' => $book->id, 'print_type' => 'bw', 'quantity' => 1]);
        $this->post(route('checkout.store'), [
            'name' => 'Ayşe Yılmaz', 'phone' => '0532 123 45 67', 'email' => 'ayse@example.com',
            'district_id' => DeliveryDistrict::where('name', 'Odunpazarı')->value('id'), 'neighborhood' => 'Vişnelik', 'address' => 'Atatürk Cad. No: 12',
            'slot' => '10:00 - 13:00', 'payment_method' => 'cash', 'terms' => '1',
        ])->assertSessionHasNoErrors();

        return Order::firstOrFail();
    }

    public function test_order_mails_go_to_customer_and_admins_and_on_each_status(): void
    {
        Mail::fake();
        $order = $this->placeOrder();

        Mail::assertQueued(SiteMail::class, fn (SiteMail $m) => $m->type === 'order.placed' && $m->hasTo('ayse@example.com'));
        Mail::assertQueued(SiteMail::class, fn (SiteMail $m) => $m->type === 'order.admin' && $m->hasTo('patron@example.com') && $m->hasTo('kasa@example.com')
            && $m->hasReplyTo('ayse@example.com'));

        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->put(route('admin.orders.status', $order), ['status' => 'preparing', 'notify' => '1']);
        $this->put(route('admin.orders.status', $order), ['status' => 'on_the_way']);
        Mail::assertQueued(SiteMail::class, fn (SiteMail $m) => $m->type === 'order.status' && $m->hasTo('ayse@example.com'));
        Mail::assertQueuedCount(3); // bildirim işaretsiz durum değişikliği e-posta göndermez
    }

    public function test_mail_templates_render_and_sending_is_logged(): void
    {
        // Sahte değil: log sürücüsüyle gerçek gönderim ve günlük.
        $order = $this->placeOrder()->load('history');
        Settings::put(['site_name' => 'Deneme Kırtasiye']);

        $html = SiteMail::orderPlaced($order)->render();
        $this->assertStringContainsString('Deneme Kırtasiye', $html);
        $this->assertStringContainsString($order->number, $html);
        $this->assertStringContainsString('TYT Fizik', $html);
        $this->assertStringContainsString($order->trackingUrl(), $html);
        $this->assertStringContainsString('Siparişi panelde aç', SiteMail::orderAdmin($order)->render());
        $this->assertNotEmpty(SiteMail::orderStatus($order, $order->history->first())->render());
        $this->assertStringContainsString('parola', mb_strtolower(SiteMail::passwordReset(User::factory()->create(), 'https://ornek/sifirla', 60)->render()));

        Mail::to('deneme@example.com')->sendNow(SiteMail::test());
        $this->assertDatabaseHas('mail_logs', ['type' => 'test', 'to' => 'deneme@example.com', 'status' => 'sent']);
        $this->assertDatabaseHas('mail_logs', ['type' => 'order.placed', 'to' => 'ayse@example.com', 'status' => 'sent', 'subject_model_id' => $order->id]);
    }

    public function test_contact_form_creates_thread_and_notifies_admin(): void
    {
        Mail::fake();
        $this->get(route('contact'))->assertOk()->assertSee(Settings::get('contact_intro'));

        $this->post(route('contact.store'), ['name' => 'Ali Veli', 'email' => 'ali@example.com', 'subject' => 'Soru', 'body' => 'Kısa'])
            ->assertSessionHasErrors(['body', 'kvkk']);

        $response = $this->post(route('contact.store'), [
            'name' => 'Ali Veli', 'email' => 'ALI@example.com', 'phone' => '0533 111 22 33', 'subject' => 'Kitap stoğu',
            'body' => 'TYT Fizik kitabı ne zaman gelir?', 'kvkk' => '1',
        ]);
        $thread = MessageThread::firstOrFail();
        $response->assertRedirect($thread->url());
        $this->assertSame('ali@example.com', $thread->email);
        $this->assertSame('05331112233', $thread->phone);
        $this->assertTrue($thread->unread_admin);
        Mail::assertQueued(SiteMail::class, fn (SiteMail $m) => $m->type === 'message.admin' && $m->hasTo('patron@example.com') && $m->hasReplyTo('ali@example.com'));

        $this->get($thread->url())->assertOk()->assertSee('TYT Fizik kitabı ne zaman gelir?');
        $this->get(route('messages.show', ['thread' => $thread->id, 'token' => 'yanlis']))->assertNotFound();
    }

    public function test_honeypot_silently_drops_bots(): void
    {
        Mail::fake();
        $this->post(route('contact.store'), ['name' => 'Bot', 'email' => 'bot@example.com', 'subject' => 'Spam', 'body' => 'spam spam spam', 'kvkk' => '1', 'website' => 'http://spam'])
            ->assertRedirect(route('contact'));
        $this->assertSame(0, MessageThread::count());
        Mail::assertNothingQueued();
    }

    public function test_admin_replies_and_customer_continues_thread(): void
    {
        Mail::fake();
        $this->post(route('contact.store'), ['name' => 'Ali Veli', 'email' => 'ali@example.com', 'subject' => 'Fotokopi', 'body' => 'Spiralli cilt yapıyor musunuz?', 'kvkk' => '1']);
        $thread = MessageThread::firstOrFail();

        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        $this->get(route('admin.messages.index'))->assertOk()->assertSee('Fotokopi');
        $this->get(route('admin.messages.show', $thread))->assertOk()->assertSee('Spiralli cilt');
        $this->assertFalse($thread->fresh()->unread_admin);

        $this->post(route('admin.messages.reply', $thread), ['body' => 'Evet, yapıyoruz.'])->assertSessionHasNoErrors();
        $this->assertSame('answered', $thread->fresh()->status);
        Mail::assertQueued(SiteMail::class, fn (SiteMail $m) => $m->type === 'message.reply' && $m->hasTo('ali@example.com') && $m->buttonUrl === $thread->url());

        // Müşteri e-postadaki bağlantıdan yanıtlar: konu yeniden açılır, yöneticiye bildirim gider.
        $this->post(route('messages.reply', ['thread' => $thread->id, 'token' => $thread->token]), ['body' => 'Teşekkürler, fiyatı nedir?'])->assertRedirect();
        $this->assertSame('open', $thread->fresh()->status);
        $this->assertTrue($thread->fresh()->unread_admin);
        Mail::assertQueued(SiteMail::class, fn (SiteMail $m) => $m->type === 'message.admin', 2);

        $this->put(route('admin.messages.status', $thread), ['status' => 'closed']);
        $this->get($thread->url())->assertSee('Bu konu kapatıldı');
        $this->delete(route('admin.messages.destroy', $thread))->assertRedirect(route('admin.messages.index'));
        $this->assertSame(0, MessageThread::count());
    }

    public function test_member_sees_messages_and_order_is_linked_only_for_owner(): void
    {
        Mail::fake();
        $order = $this->placeOrder();
        $user = User::factory()->create(['email' => 'ayse@example.com']);
        $this->actingAs($user, 'web');

        $this->post(route('contact.store'), ['name' => 'Ayşe', 'subject' => 'Siparişim', 'body' => 'Teslimat saatini değiştirebilir miyiz?', 'order_number' => strtolower($order->number), 'kvkk' => '1']);
        $mine = MessageThread::latest('id')->firstOrFail();
        $this->assertSame($order->id, $mine->order_id);
        $this->assertSame($user->id, $mine->user_id);
        $this->get(route('account.messages'))->assertOk()->assertSee('Siparişim');

        // Başkası sipariş numarasını yazarsa konuya bağlanmaz.
        auth('web')->logout();
        $this->post(route('contact.store'), ['name' => 'Yabancı', 'email' => 'x@example.com', 'subject' => 'Deneme', 'body' => 'Bu sipariş kimin acaba?', 'order_number' => $order->number, 'kvkk' => '1']);
        $this->assertNull(MessageThread::latest('id')->first()->order_id);
    }

    public function test_welcome_mail_on_register(): void
    {
        Mail::fake();
        $this->post(route('register'), ['name' => 'Mehmet Kaya', 'email' => 'mehmet@example.com', 'phone' => '0532 999 88 77',
            'password' => 'Guclu-Parola-123', 'password_confirmation' => 'Guclu-Parola-123', 'terms' => '1'])->assertSessionHasNoErrors();
        Mail::assertQueued(SiteMail::class, fn (SiteMail $m) => $m->type === 'account.welcome' && $m->hasTo('mehmet@example.com'));
    }

    public function test_admin_customers_mail_logs_and_test_mail(): void
    {
        $order = $this->placeOrder();
        $user = User::factory()->create(['name' => 'Zeynep Ak', 'email' => 'zeynep@example.com']);
        $order->update(['user_id' => $user->id]);
        MailLog::create(['type' => 'order.status', 'to' => 'zeynep@example.com', 'subject' => 'Durum', 'status' => 'failed', 'error' => 'SMTP bağlantı hatası']);

        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Bugünkü sipariş')->assertSee($order->number);
        $this->get(route('admin.customers.index', ['q' => 'zeynep']))->assertOk()->assertSee('Zeynep Ak')->assertSee('240,00 ₺');
        $this->get(route('admin.customers.show', $user))->assertOk()->assertSee($order->number);

        $this->post(route('admin.customers.toggle', $user));
        $this->assertFalse($user->fresh()->is_active);
        $this->post(route('login'), ['email' => 'zeynep@example.com', 'password' => 'password'])->assertSessionHasErrors();

        $this->get(route('admin.mail-logs', ['durum' => 'hata']))->assertOk()->assertSee('SMTP bağlantı hatası');

        Mail::fake();
        $this->post(route('admin.settings.test-mail'), ['to' => 'deneme@example.com'])->assertSessionHas('status');
        Mail::assertSent(SiteMail::class, fn (SiteMail $m) => $m->type === 'test');
    }
}
