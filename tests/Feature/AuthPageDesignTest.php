<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthPageDesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_register_show_3d_panel_and_tabs(): void
    {
        foreach (['/giris', '/uye-ol'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('data-scene="auth"', false)
                ->assertSee('auth-tabs', false)
                ->assertSee(Settings::DEFAULTS['auth_panel_accent'])
                ->assertSee('Kapınıza teslim');
        }
        // Parola sayfasında panel var, sekme yok
        $this->get('/sifremi-unuttum')->assertOk()->assertSee('data-scene="auth"', false)->assertDontSee('auth-tabs', false);
    }

    public function test_admin_can_edit_panel_texts(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->put(route('admin.settings.update', 'genel'), [
            'site_name' => 'Eskişehir Fotokopi',
            'store_open' => '1',
            'auth_panel_title' => 'Hoş geldiniz',
            'auth_panel_points' => 'book|Binlerce kitap|Tek adreste',
        ])->assertRedirect()->assertSessionHasNoErrors();
        auth('admin')->logout();

        $this->get('/giris')->assertOk()->assertSee('Hoş geldiniz')->assertSee('Binlerce kitap')->assertDontSee('Her adımı anlık görün');
    }
}
