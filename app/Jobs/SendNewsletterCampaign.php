<?php

namespace App\Jobs;

use App\Mail\SiteMail;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * E-bülteni aktif abonelere dağıtır: her aboneye kendi "abonelikten çık" bağlantısıyla ayrı bir e-posta kuyruğa
 * konur (alıcılar birbirini görmez). SMTP sağlayıcısını boğmamak için e-postalar birkaç saniye arayla gider.
 * Sonuçlar (gönderildi/hata) mail_logs tablosuna kampanyaya bağlı yazılır.
 */
class SendNewsletterCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Aynı anda gönderilen e-posta sayısı ve gruplar arası bekleme (saniye) */
    public const BATCH = 20;

    public const GAP_SECONDS = 10;

    public int $tries = 1;

    public function __construct(public NewsletterCampaign $campaign) {}

    public function handle(): void
    {
        $count = 0;
        NewsletterSubscriber::active()->orderBy('id')->chunkById(200, function ($subs) use (&$count) {
            foreach ($subs as $sub) {
                $delay = intdiv($count, self::BATCH) * self::GAP_SECONDS;
                Mail::to($sub->email)->later(now()->addSeconds($delay), SiteMail::newsletter($this->campaign, $sub));
                $count++;
            }
        });

        $this->campaign->update(['status' => 'sent', 'recipients_count' => $count, 'sent_at' => now()]);
    }
}
