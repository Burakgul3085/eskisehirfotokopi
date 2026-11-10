<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_render(): void
    {
        $this->get('/')->assertOk()->assertSee('Eskişehir Fotokopi');
        $this->get('/giris')->assertOk()->assertSee('Giriş yap');
        $this->get('/uye-ol')->assertOk();
        $this->get('/sifremi-unuttum')->assertOk();
    }

    public function test_register_normalizes_email_and_phone(): void
    {
        $this->post('/uye-ol', [
            'name' => 'Ayşe Yılmaz', 'email' => 'AYSE@Example.com', 'phone' => '+90 532 123 45 67',
            'password' => 'gizli1234', 'password_confirmation' => 'gizli1234', 'terms' => '1',
        ])->assertRedirect(route('account.index'));

        $user = User::firstOrFail();
        $this->assertSame('ayse@example.com', $user->email);
        $this->assertSame('05321234567', $user->phone);
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_register_requires_terms_and_valid_phone(): void
    {
        $this->post('/uye-ol', ['name' => 'A', 'email' => 'a@b.com', 'phone' => '123', 'password' => 'gizli1234', 'password_confirmation' => 'gizli1234'])
            ->assertSessionHasErrors(['phone', 'terms']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_and_logout(): void
    {
        $user = User::factory()->create(['email' => 'm@ornek.com', 'password' => 'gizli1234']);

        $this->post('/giris', ['email' => 'M@ornek.com', 'password' => 'yanlis'])->assertSessionHasErrors('email');
        $this->assertGuest('web');

        $this->post('/giris', ['email' => 'm@ornek.com', 'password' => 'gizli1234'])->assertRedirect(route('account.index'));
        $this->assertAuthenticatedAs($user, 'web');
        $this->get('/hesabim')->assertOk()->assertSee($user->name);

        $this->post('/cikis')->assertRedirect('/');
        $this->assertGuest('web');
    }

    public function test_inactive_customer_cannot_login(): void
    {
        User::factory()->create(['email' => 'p@ornek.com', 'password' => 'gizli1234', 'is_active' => false]);
        $this->post('/giris', ['email' => 'p@ornek.com', 'password' => 'gizli1234'])->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_account_requires_login(): void
    {
        $this->get('/hesabim')->assertRedirect('/giris');
    }

    public function test_password_reset_flow(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'r@ornek.com']);

        // Kayıtsız adreste de aynı yanıt verilir.
        $this->post('/sifremi-unuttum', ['email' => 'yok@ornek.com'])->assertSessionHas('status');
        $this->post('/sifremi-unuttum', ['email' => 'r@ornek.com'])->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->post('/sifre-sifirla', ['token' => $token, 'email' => 'r@ornek.com', 'password' => 'yeni12345', 'password_confirmation' => 'yeni12345'])
            ->assertRedirect('/giris');
        $this->assertTrue(Hash::check('yeni12345', $user->fresh()->password));
    }

    public function test_profile_and_password_update(): void
    {
        $user = User::factory()->create(['password' => 'gizli1234']);
        $this->actingAs($user, 'web');

        $this->put('/hesabim/bilgiler', ['name' => 'Yeni Ad', 'email' => $user->email, 'phone' => '0555 111 22 33'])->assertSessionHas('status');
        $this->assertSame('05551112233', $user->fresh()->phone);

        $this->put('/hesabim/parola', ['current_password' => 'yanlis', 'password' => 'yeni12345', 'password_confirmation' => 'yeni12345'])
            ->assertSessionHasErrors('current_password');
        $this->put('/hesabim/parola', ['current_password' => 'gizli1234', 'password' => 'yeni12345', 'password_confirmation' => 'yeni12345'])
            ->assertSessionHas('status');
        $this->assertTrue(Hash::check('yeni12345', $user->fresh()->password));
    }
}
