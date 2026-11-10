<?php

namespace App\Http\Controllers\Store;

use App\Domain\Messages\Messenger;
use App\Http\Controllers\Controller;
use App\Models\MessageThread;
use App\Rules\TurkishPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** İletişim sayfası ve mesaj konusu (müşteri tarafı). Konuya e-postadaki gizli bağlantıyla veya Hesabım'dan ulaşılır. */
class ContactController extends Controller
{
    public function show(Request $request): View
    {
        return view('store.contact', ['user' => $request->user('web')]);
    }

    public function store(Request $request, Messenger $messenger): RedirectResponse
    {
        // Bot tuzağı: görünmeyen alan doluysa sessizce başarılı say.
        if (filled($request->input('website'))) {
            return redirect()->route('contact')->with('status', 'Mesajınız alındı.');
        }
        $user = $request->user('web');
        $request->merge(['email' => mb_strtolower(trim((string) ($request->input('email') ?: $user?->email)))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', new TurkishPhone],
            'subject' => ['required', 'string', 'min:3', 'max:160'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
            'order_number' => ['nullable', 'string', 'max:20'],
            'kvkk' => ['accepted'],
        ], ['kvkk.accepted' => 'Kişisel verilerinizin yanıt vermek için işlenmesini onaylayın.']);
        $data['phone'] = filled($data['phone'] ?? null) ? TurkishPhone::normalize($data['phone']) : null;

        $thread = $messenger->start($data, $user?->id, $request->ip());
        $request->session()->put('message_threads.'.$thread->id, true);

        return redirect()->to($thread->url())->with('status', 'Mesajınız alındı. Yanıtımızı '.$thread->email.' adresine de göndereceğiz.');
    }

    public function thread(Request $request, MessageThread $thread, string $token): View
    {
        abort_unless(hash_equals($thread->token, $token), 404);

        return view('store.message-thread', ['thread' => $thread->load('messages.admin')]);
    }

    public function reply(Request $request, MessageThread $thread, string $token, Messenger $messenger): RedirectResponse
    {
        abort_unless(hash_equals($thread->token, $token), 404);
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:5000']]);
        $messenger->customerReply($thread, $data['body'], $request->ip());

        return redirect()->to($thread->url())->with('status', 'Yanıtınız gönderildi.');
    }
}
