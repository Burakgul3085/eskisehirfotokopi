<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_area_redirects_guests_to_admin_login(): void
    {
        $this->get('/yonetim')->assertRedirect('/yonetim/giris');
        $this->get('/yonetim/giris')->assertOk()->assertSee('Yönetim girişi');
    }

    public function test_customer_session_does_not_grant_admin_access(): void
    {
        $this->actingAs(User::factory()->create(), 'web');
        $this->get('/yonetim')->assertRedirect('/yonetim/giris');
    }

    public function test_admin_login_and_logout(): void
    {
        $admin = Admin::factory()->create(['email' => 'y@ornek.com', 'password' => 'Yonetici12345']);

        $this->post('/yonetim/giris', ['email' => 'y@ornek.com', 'password' => 'Yonetici12345'])->assertRedirect('/yonetim');
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertNotNull($admin->fresh()->last_login_at);
        $this->get('/yonetim')->assertOk()->assertSee('Gösterge paneli');

        $this->post('/yonetim/cikis')->assertRedirect('/yonetim/giris');
        $this->assertGuest('admin');
    }

    public function test_account_locks_after_repeated_failures(): void
    {
        $admin = Admin::factory()->create(['email' => 'k@ornek.com', 'password' => 'Yonetici12345']);

        for ($i = 0; $i < Admin::MAX_FAILED_LOGINS; $i++) {
            $this->post('/yonetim/giris', ['email' => 'k@ornek.com', 'password' => 'yanlis']);
        }
        $this->assertTrue($admin->fresh()->isLocked());

        // Doğru parola da kilit süresince kabul edilmez.
        $this->post('/yonetim/giris', ['email' => 'k@ornek.com', 'password' => 'Yonetici12345'])->assertSessionHasErrors('email');
        $this->assertGuest('admin');

        $this->travel(Admin::LOCK_MINUTES + 1)->minutes();
        $this->post('/yonetim/giris', ['email' => 'k@ornek.com', 'password' => 'Yonetici12345'])->assertRedirect('/yonetim');
    }

    public function test_inactive_admin_is_rejected_and_logged_out(): void
    {
        $admin = Admin::factory()->create(['email' => 'p@ornek.com', 'password' => 'Yonetici12345', 'is_active' => false]);
        $this->post('/yonetim/giris', ['email' => 'p@ornek.com', 'password' => 'Yonetici12345'])->assertSessionHasErrors('email');

        $active = Admin::factory()->create();
        $this->actingAs($active, 'admin')->get('/yonetim')->assertOk();
        $active->update(['is_active' => false]);
        $this->get('/yonetim')->assertRedirect('/yonetim/giris');
        $this->assertGuest('admin');
    }

    public function test_security_headers_present(): void
    {
        $this->get('/')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_create_admin_command(): void
    {
        $this->artisan('yonetici:olustur', ['email' => 'Patron@Ornek.com', 'name' => 'Patron'])
            ->expectsQuestion('Parola (en az 10 karakter, harf ve rakam)', 'Guclu123456')
            ->assertSuccessful();
        $this->assertTrue(Admin::where('email', 'patron@ornek.com')->firstOrFail()->isSuper());
    }
}
