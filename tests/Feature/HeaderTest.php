<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_buttons_follow_admin_number(): void
    {
        Settings::put(['whatsapp' => '905551112233']);
        $this->get('/')->assertOk()
            ->assertSee('header-support', false)
            ->assertSee('aria-label="WhatsApp ile yazın"', false)
            ->assertSee('brand-mark', false)
            ->assertSee('data-header-nav', false);

        // Yönetimde numara silinince ikisi de kaybolur
        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->put(route('admin.settings.update', 'iletisim'), ['whatsapp' => ''])->assertSessionHasNoErrors();
        auth('admin')->logout();

        $this->get('/')->assertOk()
            ->assertDontSee('header-support', false)
            ->assertDontSee('aria-label="WhatsApp ile yazın"', false)
            ->assertDontSee('wa.me', false);
    }

    public function test_ticker_is_outside_sticky_header(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertLessThan(strpos($html, 'data-site-header'), strpos($html, 'header-ticker'));
    }
}
