<?php

namespace Tests\Feature;

use App\Domain\Catalog\CategoryTree;
use App\Models\Admin;
use App\Models\MessageThread;
use App\Models\Page;
use App\Models\Product;
use App\Models\Publisher;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Aşama 9 güvenlik ve performans düzeltmelerinin testleri. */
class HardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivated_customer_is_logged_out_on_next_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web')->get(route('account.index'))->assertOk();

        $this->actingAs(Admin::factory()->create(), 'admin')->post(route('admin.customers.toggle', $user));
        $fresh = $user->fresh();
        $this->assertFalse($fresh->is_active);
        $this->assertNotSame($user->getRememberToken(), $fresh->getRememberToken()); // "beni hatırla" çerezi geçersiz

        $this->actingAs($fresh, 'web')->get(route('account.index'))->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_customer_cannot_reply_to_closed_thread(): void
    {
        Mail::fake();
        $thread = MessageThread::create(['token' => str_repeat('a', 40), 'name' => 'Ali', 'email' => 'ali@example.com', 'subject' => 'Soru',
            'status' => 'closed', 'unread_admin' => false, 'last_message_at' => now()]);

        $this->post(route('messages.reply', ['thread' => $thread->id, 'token' => $thread->token]), ['body' => 'Bir sorum daha var'])
            ->assertSessionHasErrors('body');
        $this->assertSame(0, $thread->messages()->count());
        $this->assertSame('closed', $thread->fresh()->status);
        Mail::assertNothingQueued();
    }

    public function test_empty_placeholders_render_dash_and_admin_is_warned(): void
    {
        Settings::put(['phone' => null, 'email' => 'info@example.com', 'address' => null]);
        $page = Page::where('key', 'sales')->firstOrFail();
        $page->update(['body' => 'Telefon: {telefon} / E-posta: {eposta}']);

        $this->get($page->url())->assertSee('Telefon: — / E-posta: info@example.com');
        $this->assertSame(['{telefon}'], $page->missingPlaceholders());
        $this->actingAs(Admin::factory()->create(), 'admin')->get(route('admin.pages.edit', $page))->assertSee('ayarlarda boş');
    }

    public function test_settings_change_is_visible_in_same_request_cycle(): void
    {
        // İstek içi önbellek (memo) eski değeri tutmamalı.
        $this->assertSame('Eskişehir Fotokopi', Settings::siteName());
        Settings::put(['site_name' => 'Yeni Ad']);
        $this->assertSame('Yeni Ad', Settings::siteName());
        $this->get(route('home'))->assertSee('Yeni Ad');

        Page::where('key', 'faq')->firstOrFail()->update(['title' => 'S.S.S.']);
        $this->get(route('home'))->assertSee('S.S.S.');
    }

    public function test_publishers_page_counts_only_visible_products(): void
    {
        $category = app(CategoryTree::class)->ensurePath(['YKS']);
        $a = Publisher::create(['name' => 'Alfa', 'slug' => 'alfa', 'match_key' => 'alfa', 'is_active' => true]);
        $b = Publisher::create(['name' => 'Beta', 'slug' => 'beta', 'match_key' => 'beta', 'is_active' => true]);
        foreach ([['a1', $a, 'active'], ['a2', $a, 'active'], ['a3', $a, 'draft'], ['b1', $b, 'draft']] as [$slug, $pub, $status]) {
            Product::create(['title' => $slug, 'slug' => $slug, 'category_id' => $category->id, 'publisher_id' => $pub->id, 'price_bw' => 10, 'status' => $status]);
        }

        $response = $this->get(route('publishers.index'))->assertOk();
        $response->assertSee('Alfa')->assertDontSee('Beta'); // yayında ürünü olmayan (öne çıkmamış) yayınevi listelenmez
        $this->assertSame(2, $response->viewData('groups')->flatten()->firstWhere('id', $a->id)->products_count);
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get(route('home'))
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy');
    }
}
