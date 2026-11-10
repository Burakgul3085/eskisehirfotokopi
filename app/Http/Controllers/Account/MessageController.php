<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\MessageThread;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Hesabım › Mesajlarım: üyenin hesabından veya e-postasından açtığı konular. */
class MessageController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user('web');

        return view('account.messages', [
            'threads' => MessageThread::where(fn ($q) => $q->where('user_id', $user->id)->orWhere('email', $user->email))
                ->withCount('messages')->latest('last_message_at')->paginate(20),
        ]);
    }
}
