<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Setting;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(Admin::factory()->create(), 'admin');
    }

    public function test_every_group_renders(): void
    {
        foreach (['genel', 'iletisim', 'gorunum', 'seo', 'eposta'] as $group) {
            $this->get("/yonetim/ayarlar/{$group}")->assertOk();
        }
        $this->get('/yonetim/ayarlar/yok')->assertNotFound();
    }

    public function test_general_settings_drive_storefront(): void
    {
        $this->put('/yonetim/ayarlar/genel', [
            'site_name' => 'Deneme Baskı', 'site_tagline' => 'Slogan', 'announcement' => '500 TL üzeri teslimat ücretsiz',
            'store_open' => '1', 'footer_about' => 'Hakkımızda metni',
        ])->assertRedirect('/yonetim/ayarlar/genel');

        $this->get('/')->assertSee('Deneme Baskı')->assertSee('500 TL üzeri teslimat ücretsiz')->assertSee('Hakkımızda metni');
        $this->assertDatabaseHas('activity_logs', ['action' => 'settings.updated']);
    }

    public function test_closed_store_shows_maintenance_to_visitors_only(): void
    {
        Settings::put(['store_open' => '0', 'closed_message' => 'Bakımdayız']);

        $this->get('/')->assertOk(); // giriş yapmış yönetici siteyi görür
        auth('admin')->logout();
        $this->get('/')->assertStatus(503)->assertSee('Bakımdayız');
        $this->get('/yonetim/giris')->assertOk();
    }

    public function test_mail_password_is_encrypted_and_kept_when_blank(): void
    {
        $base = ['mail_host' => 'smtp.gmail.com', 'mail_port' => '587', 'mail_encryption' => 'tls', 'mail_username' => 'a@b.com', 'notify_emails' => 'x@y.com, z@y.com'];

        $this->put('/yonetim/ayarlar/eposta', $base + ['mail_password' => 'uygulama-sifresi'])->assertSessionHasNoErrors();
        $this->assertNotSame('uygulama-sifresi', Setting::find('mail_password')->value);
        $this->assertSame('uygulama-sifresi', Settings::secret('mail_password'));

        $this->put('/yonetim/ayarlar/eposta', $base + ['mail_password' => ''])->assertSessionHasNoErrors();
        $this->assertSame('uygulama-sifresi', Settings::secret('mail_password'));

        $this->put('/yonetim/ayarlar/eposta', ['notify_emails' => 'gecersiz'] + $base)->assertSessionHasErrors('notify_emails');
    }

    public function test_appearance_colors_and_logo(): void
    {
        Storage::fake('public');

        $this->put('/yonetim/ayarlar/gorunum', ['color_primary' => '#1D4ED8', 'color_accent' => '#f59e0b', 'logo' => UploadedFile::fake()->image('logo.png', 400, 100)])
            ->assertSessionHasNoErrors();
        $path = Settings::get('logo_path');
        Storage::disk('public')->assertExists($path);
        $this->get('/')->assertSee('--color-primary:#1d4ed8', false);

        $this->put('/yonetim/ayarlar/gorunum', ['color_primary' => 'red;}body{', 'color_accent' => '#f59e0b'])->assertSessionHasErrors('color_primary');

        $this->put('/yonetim/ayarlar/gorunum', ['color_primary' => '#1d4ed8', 'color_accent' => '#f59e0b', 'remove_logo' => '1']);
        $this->assertNull(Settings::get('logo_path'));
        Storage::disk('public')->assertMissing($path);
    }
}
