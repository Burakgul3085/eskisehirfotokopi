<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * E-bülten: alt bilgideki formdan abonelik (açık rıza onay kutusuyla) ve e-postadaki bağlantıdan abonelikten çıkış.
 * Çıkış sayfası onay ister (bağlantı önizleyicileri yanlışlıkla çıkarmasın); e-posta istemcilerinin tek tık isteği
 * (List-Unsubscribe-Post) doğrudan çıkarır.
 */
class NewsletterController extends Controller
{
    public function subscribe(Request $request): RedirectResponse
    {
        abort_unless(Settings::flag('newsletter_enabled'), 404);

        // Bot tuzağı: görünmez alan doluysa kaydetmeden teşekkür et
        if (filled($request->input('website'))) {
            return back()->withFragment('bulten')->with('newsletter_status', Settings::get('newsletter_thanks'));
        }

        $validator = Validator::make($request->only('email', 'consent'), [
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'consent' => ['accepted'],
        ], [
            'email.required' => 'E-posta adresinizi yazın.',
            'email.email' => 'Geçerli bir e-posta adresi yazın.',
            'consent.accepted' => 'Abone olmak için onay kutusunu işaretleyin.',
        ]);
        // Hata olursa sayfa başına değil, formun olduğu yere (#bulten) dönülür
        if ($validator->fails()) {
            return back()->withFragment('bulten')->withInput($request->only('email', 'consent'))->withErrors($validator, 'newsletter');
        }

        NewsletterSubscriber::subscribe($validator->validated()['email'], $request->ip());

        // Adres zaten kayıtlı olsa da aynı yanıt (kimin abone olduğu dışarıdan anlaşılmasın)
        return back()->withFragment('bulten')->with('newsletter_status', Settings::get('newsletter_thanks'));
    }

    public function show(string $token): View
    {
        $sub = NewsletterSubscriber::where('token', $token)->firstOrFail();

        return view('store.newsletter-unsubscribe', ['subscriber' => $sub, 'done' => ! $sub->isActive()]);
    }

    public function unsubscribe(Request $request, string $token): View|Response
    {
        $sub = NewsletterSubscriber::where('token', $token)->firstOrFail();
        if ($sub->isActive()) {
            $sub->update(['unsubscribed_at' => now()]);
        }

        // E-posta istemcisinin tek tık isteği: sayfa değil, boş yanıt yeter
        if ($request->input('List-Unsubscribe') === 'One-Click') {
            return response()->noContent();
        }

        return view('store.newsletter-unsubscribe', ['subscriber' => $sub, 'done' => true]);
    }
}
