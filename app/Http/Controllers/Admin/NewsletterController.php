<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendNewsletterCampaign;
use App\Mail\SiteMail;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Yönetim › E-bülten: aboneler (arama, elle ekleme, silme, CSV) ve toplu e-posta. Önce kendinize deneme gönderip
 * görünümü kontrol edersiniz; "Abonelere gönder" her aktif aboneye kuyruktan ayrı e-posta yollar.
 */
class NewsletterController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->query('durum') === 'ayrilan' ? 'ayrilan' : 'aktif';
        $subscribers = NewsletterSubscriber::query()
            ->when($filter === 'aktif', fn ($q) => $q->whereNull('unsubscribed_at'), fn ($q) => $q->whereNotNull('unsubscribed_at'))
            ->when(filled($request->query('q')), fn ($q) => $q->where('email', 'like', '%'.trim($request->query('q')).'%'))
            ->latest('id')->paginate(30)->withQueryString();

        $campaigns = NewsletterCampaign::with('admin:id,name')
            ->withCount(['logs as sent_count' => fn ($q) => $q->where('status', 'sent'), 'logs as failed_count' => fn ($q) => $q->where('status', 'failed')])
            ->latest('id')->limit(20)->get();

        return view('admin.newsletter.index', [
            'subscribers' => $subscribers,
            'filter' => $filter,
            'campaigns' => $campaigns,
            'activeCount' => NewsletterSubscriber::active()->count(),
            'leftCount' => NewsletterSubscriber::whereNotNull('unsubscribed_at')->count(),
            'last30' => NewsletterSubscriber::active()->where('created_at', '>=', now()->subDays(30))->count(),
            'testTo' => SiteMail::adminRecipients(),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:190'],
            'heading' => ['required', 'string', 'max:190'],
            'body' => ['required', 'string', 'max:10000'],
            'button_text' => ['nullable', 'required_with:button_url', 'string', 'max:60'],
            'button_url' => ['nullable', 'required_with:button_text', 'string', 'max:500', 'regex:#^https?://#i'],
            'mode' => ['required', 'in:test,all'],
        ], [
            'button_url.regex' => 'Bağlantı http:// veya https:// ile başlamalı.',
            'button_text.required_with' => 'Bağlantı verdiyseniz buton yazısını da girin.',
            'button_url.required_with' => 'Buton yazısı verdiyseniz bağlantıyı da girin.',
        ]);
        unset($data['mode']);

        // Deneme: kaydedilmeden yalnız yönetici adreslerine
        if ($request->input('mode') === 'test') {
            $to = SiteMail::adminRecipients();
            if (! $to) {
                return back()->withInput()->withErrors(['admin' => 'Deneme için Ayarlar › E-posta bölümünde bildirim adresi girin.']);
            }
            $mail = SiteMail::newsletter(new NewsletterCampaign($data));
            $mail->about = null; // kaydedilmemiş kampanya kuyruğa bağlanamaz
            Mail::to($to)->send($mail);

            return back()->withInput()->with('status', 'Deneme e-postası '.implode(', ', $to).' adresine gönderildi.');
        }

        $count = NewsletterSubscriber::active()->count();
        if ($count === 0) {
            return back()->withInput()->withErrors(['admin' => 'Henüz aktif abone yok.']);
        }

        $campaign = NewsletterCampaign::create($data + ['status' => 'sending', 'recipients_count' => $count, 'admin_id' => $request->user('admin')->id]);
        SendNewsletterCampaign::dispatch($campaign);

        return redirect()->route('admin.newsletter.index')
            ->with('status', '“'.$campaign->subject.'” '.number_format($count, 0, ',', '.').' aboneye gönderiliyor. Sonuçlar aşağıdaki listede ve E-posta kayıtlarında.');
    }

    public function storeSubscriber(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:190']]);
        NewsletterSubscriber::subscribe($data['email'], null, 'admin');

        return back()->with('status', $data['email'].' abonelere eklendi.');
    }

    public function destroySubscriber(NewsletterSubscriber $subscriber): RedirectResponse
    {
        $subscriber->delete();

        return back()->with('status', 'Abone silindi.');
    }

    public function export(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excel Türkçe karakterleri doğru açsın
            fputcsv($out, ['E-posta', 'Kayıt tarihi', 'Onay tarihi', 'Kaynak', 'IP'], ';');
            NewsletterSubscriber::active()->orderBy('id')->chunkById(500, function ($subs) use ($out) {
                foreach ($subs as $s) {
                    fputcsv($out, [$s->email, $s->created_at?->format('d.m.Y H:i'), $s->consented_at?->format('d.m.Y H:i'), $s->source, $s->ip], ';');
                }
            });
            fclose($out);
        }, 'e-bulten-aboneleri-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
