<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Support\MapEmbed;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContactPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_map_embed_url_is_built_from_link_code_or_address(): void
    {
        // Koordinatlı tam bağlantı
        $this->assertSame('https://www.google.com/maps?q=39.7767%2C30.5206&z=16&hl=tr&output=embed',
            MapEmbed::fromLink('https://www.google.com/maps/place/Fotokopi/@39.7767,30.5206,17z/data=!3m1'));
        // Yer adıyla bağlantı ve q parametresi
        $this->assertStringContainsString('q=Eski%C5%9Fehir%20Fotokopi', MapEmbed::fromLink('https://www.google.com/maps/place/Eski%C5%9Fehir+Fotokopi/data=x'));
        $this->assertStringContainsString('q=Odunpazar%C4%B1', MapEmbed::fromLink('https://maps.google.com/maps?q=Odunpazar%C4%B1'));
        // Google dışı bağlantı reddedilir
        $this->assertNull(MapEmbed::fromLink('https://example.com/maps/place/x/@1.1,2.2'));

        // Yerleştirme kodu: yalnız google.com/maps/embed
        $code = '<iframe src="https://www.google.com/maps/embed?pb=!1m18!2d30.52" width="600"></iframe>';
        $this->assertSame('https://www.google.com/maps/embed?pb=!1m18!2d30.52', MapEmbed::fromCode($code));
        $this->assertNull(MapEmbed::fromCode('<iframe src="https://evil.test/maps/embed?x"></iframe>'));
        $this->assertNull(MapEmbed::fromCode('<script>alert(1)</script>'));

        // Öncelik: kod > bağlantı > adres
        Settings::put(['address' => 'Hoşnudiye Mah. Eskişehir', 'maps_url' => null, 'maps_embed' => null]);
        $this->assertStringContainsString('q=Ho%C5%9Fnudiye', MapEmbed::url());
        Settings::put(['maps_url' => 'https://www.google.com/maps/@39.1,30.2,15z']);
        $this->assertStringContainsString('q=39.1%2C30.2', MapEmbed::url());
        Settings::put(['maps_embed' => $code]);
        $this->assertStringStartsWith('https://www.google.com/maps/embed?pb=', MapEmbed::url());
    }

    public function test_admin_short_share_link_is_resolved_and_page_shows_map(): void
    {
        Http::fake([
            'maps.app.goo.gl/*' => Http::response('', 302, ['Location' => 'https://www.google.com/maps/place/Fotokopi/@39.7767,30.5206,17z']),
        ]);
        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->put(route('admin.settings.update', 'iletisim'), [
            'maps_url' => 'https://maps.app.goo.gl/AbCd123',
            'address' => 'Test sokak Eskişehir',
            'contact_title' => 'Bize yazın',
            'contact_map_title' => 'Dükkânımız burada',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('https://www.google.com/maps/place/Fotokopi/@39.7767,30.5206,17z', Settings::get('maps_resolved'));
        $this->get(route('admin.settings.edit', 'iletisim'))->assertOk()->assertSee('Harita önizleme');

        auth('admin')->logout();
        $this->get('/iletisim')->assertOk()
            ->assertSee('data-scene="contact"', false)
            ->assertSee('Bize yazın')->assertSee('Dükkânımız burada')
            ->assertSee('https://www.google.com/maps?q=39.7767%2C30.5206', false)
            ->assertSee('https://maps.app.goo.gl/AbCd123', false); // "Haritada aç" paylaş bağlantısına gider

        // Geçersiz yerleştirme kodu reddedilir
        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->put(route('admin.settings.update', 'iletisim'), ['maps_embed' => '<iframe src="https://evil.test/x"></iframe>'])
            ->assertSessionHasErrors('maps_embed');
    }

    public function test_page_without_location_has_no_map(): void
    {
        Settings::put(['address' => null, 'maps_url' => null, 'maps_embed' => null]);
        $this->get('/iletisim')->assertOk()->assertDontSee('contact-map', false);
    }
}
