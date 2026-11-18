<?php

namespace Tests\Feature;

use App\Jobs\SendNewsletterCampaign;
use App\Mail\SiteMail;
use App\Models\Admin;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NewsletterTest extends TestCase
{
    use RefreshDatabase;

    public function test_footer_shows_map_hours_and_newsletter_form(): void
    {
        Settings::put([
            'working_hours' => "Pazartesi – Cuma 08:00–20:00\nCumartesi: 09:00-20:00\nPazar | Kapalı",
            'maps_url' => 'https://www.google.com/maps/place/Eski%C5%9Fehir',
            'address' => 'Ziya Paşa Cd. No:1 Odunpazarı',
        ]);

        $this->assertSame([
            ['day' => 'Pazartesi – Cuma', 'time' => '08:00 – 20:00'],
            ['day' => 'Cumartesi', 'time' => '09:00 – 20:00'],
            ['day' => 'Pazar', 'time' => 'Kapalı'],
        ], Settings::workingHours());

        $this->get('/')->assertOk()
            ->assertSee('id="bulten"', false)->assertSee('Kampanyalardan ilk siz haberdar olun')
            ->assertSee('Çalışma saatleri')->assertSee('09:00 – 20:00')->assertSee('Yol tarifi')
            ->assertSee('<iframe', false)->assertSee('Alışveriş');

        Settings::put(['newsletter_enabled' => '0']);
        $this->get('/')->assertOk()->assertDontSee('id="bulten"', false);
        $this->post('/bulten', ['email' => 'a@example.com', 'consent' => '1'])->assertNotFound();
    }

    public function test_visitor_subscribes_with_consent_and_can_unsubscribe(): void
    {
        // Onay kutusu zorunlu; hata formun olduğu yere döner
        $this->from('/iletisim')->post('/bulten', ['email' => 'Ayse@Example.com'])
            ->assertRedirect('/iletisim#bulten')->assertSessionHasErrorsIn('newsletter', 'consent');
        $this->assertSame(0, NewsletterSubscriber::count());

        $this->from('/iletisim')->post('/bulten', ['email' => 'Ayse@Example.com', 'consent' => '1'])
            ->assertRedirect('/iletisim#bulten')->assertSessionHas('newsletter_status');
        $sub = NewsletterSubscriber::sole();
        $this->assertSame('ayse@example.com', $sub->email);
        $this->assertNotNull($sub->consented_at);

        // Aynı adres ikinci kez eklenmez; bot tuzağı dolu ise kayıt yok
        $this->post('/bulten', ['email' => 'ayse@example.com', 'consent' => '1']);
        $this->post('/bulten', ['email' => 'bot@example.com', 'consent' => '1', 'website' => 'x']);
        $this->assertSame(1, NewsletterSubscriber::count());

        // Çıkış: bağlantı onay sayfası açar, onayla çıkar
        $this->get($sub->unsubscribeUrl())->assertOk()->assertSee('ayse@example.com')->assertSee('Aboneliğimi sonlandır');
        $this->assertTrue($sub->fresh()->isActive());
        $this->post(route('newsletter.unsubscribe.confirm', $sub->token))->assertOk()->assertSee('Aboneliğiniz sonlandırıldı');
        $this->assertFalse($sub->fresh()->isActive());

        // Yeniden abone olabilir; e-posta istemcisinin tek tık isteği CSRF'siz çalışır
        $this->post('/bulten', ['email' => 'ayse@example.com', 'consent' => '1']);
        $this->assertTrue($sub->fresh()->isActive());
        $this->post(route('newsletter.unsubscribe.confirm', $sub->token), ['List-Unsubscribe' => 'One-Click'])->assertNoContent();
        $this->assertFalse($sub->fresh()->isActive());
        $this->get('/bulten/ayril/yanlis-belirtec')->assertNotFound();
    }

    public function test_admin_sends_campaign_to_active_subscribers_only(): void
    {
        $active = NewsletterSubscriber::subscribe('bir@example.com', null);
        NewsletterSubscriber::subscribe('iki@example.com', null);
        NewsletterSubscriber::subscribe('cikan@example.com', null)->update(['unsubscribed_at' => now()]);
        Settings::put(['notify_emails' => 'yonetici@example.com']);
        $this->actingAs(Admin::factory()->create(), 'admin');

        $this->get(route('admin.newsletter.index'))->assertOk()->assertSee('bir@example.com')->assertDontSee('cikan@example.com')->assertSee('2 aboneye gönder');
        $this->get(route('admin.newsletter.index', ['durum' => 'ayrilan']))->assertOk()->assertSee('cikan@example.com');

        $form = ['subject' => 'Yeni dönem', 'heading' => 'Kitaplarda indirim', 'body' => "Merhaba,\n\nYeni kitaplar geldi.", 'button_text' => 'İncele', 'button_url' => 'https://example.com/urunler'];

        // Deneme: yalnız yöneticiye, kampanya kaydı yok
        Mail::fake();
        $this->post(route('admin.newsletter.send'), $form + ['mode' => 'test'])->assertRedirect()->assertSessionHas('status');
        Mail::assertQueued(SiteMail::class, fn ($m) => $m->hasTo('yonetici@example.com') && $m->type === 'newsletter' && $m->unsubscribeUrl === null);
        $this->assertSame(0, NewsletterCampaign::count());

        // Gönder: iş kuyruğa konur
        Queue::fake([SendNewsletterCampaign::class]);
        $this->post(route('admin.newsletter.send'), $form + ['mode' => 'all'])->assertRedirect(route('admin.newsletter.index'));
        $campaign = NewsletterCampaign::sole();
        $this->assertSame(2, $campaign->recipients_count);
        Queue::assertPushed(SendNewsletterCampaign::class);

        // İş: her aktif aboneye kendi çıkış bağlantısıyla ayrı e-posta
        (new SendNewsletterCampaign($campaign))->handle();
        Mail::assertQueued(SiteMail::class, 3); // deneme + 2 abone
        Mail::assertQueued(SiteMail::class, fn ($m) => $m->hasTo('bir@example.com') && $m->unsubscribeUrl === $active->unsubscribeUrl()
            && $m->lines === ['Merhaba,', 'Yeni kitaplar geldi.'] && $m->buttonUrl === 'https://example.com/urunler');
        Mail::assertNotQueued(SiteMail::class, fn ($m) => $m->hasTo('cikan@example.com'));
        $this->assertSame('sent', $campaign->fresh()->status);

        // E-posta: List-Unsubscribe başlığı ve alt bilgide çıkış bağlantısı
        $mail = SiteMail::newsletter($campaign, $active);
        $this->assertSame('<'.$active->unsubscribeUrl().'>', $mail->headers()->text['List-Unsubscribe']);
        $this->assertStringContainsString($active->unsubscribeUrl(), $mail->render());

        // Doğrulama, elle ekleme, CSV, silme
        $this->post(route('admin.newsletter.send'), ['subject' => 'x', 'heading' => 'x', 'body' => 'x', 'button_url' => 'javascript:alert(1)', 'mode' => 'all'])
            ->assertSessionHasErrors(['button_url', 'button_text']);
        $this->post(route('admin.newsletter.subscribers.store'), ['email' => 'elle@example.com'])->assertRedirect();
        $this->assertSame('admin', NewsletterSubscriber::where('email', 'elle@example.com')->value('source'));
        $csv = $this->get(route('admin.newsletter.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('elle@example.com', $csv);
        $this->assertStringNotContainsString('cikan@example.com', $csv);
        $this->delete(route('admin.newsletter.subscribers.destroy', $active))->assertRedirect();
        $this->assertNull($active->fresh());
    }
}
