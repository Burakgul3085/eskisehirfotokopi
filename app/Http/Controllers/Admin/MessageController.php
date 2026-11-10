<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Messages\Messenger;
use App\Http\Controllers\Controller;
use App\Models\MessageThread;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Yönetim › Mesajlar: müşteri mesajları, yanıt (müşteriye e-postayla gider), kapatma. */
class MessageController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('durum', 'open');
        $threads = MessageThread::query()->withCount('messages')->search($request->query('q'))
            ->when(isset(MessageThread::STATUSES[$status]), fn ($q) => $q->where('status', $status))
            ->orderByDesc('unread_admin')->orderByDesc('last_message_at')->paginate(30)->withQueryString();

        return view('admin.messages.index', [
            'threads' => $threads,
            'status' => $status,
            'counts' => MessageThread::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function show(MessageThread $thread): View
    {
        if ($thread->unread_admin) {
            $thread->update(['unread_admin' => false]);
        }

        return view('admin.messages.show', [
            'thread' => $thread->load('messages.admin', 'order', 'user'),
            'others' => MessageThread::where('email', $thread->email)->whereKeyNot($thread->id)->latest('last_message_at')->limit(5)->get(),
        ]);
    }

    public function reply(Request $request, MessageThread $thread, Messenger $messenger): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:5000'], 'close' => ['nullable', 'boolean']]);
        $messenger->adminReply($thread, $data['body'], $request->user('admin'), $request->boolean('close'));

        return redirect()->route('admin.messages.show', $thread)->with('status', 'Yanıt gönderildi; müşteriye e-postayla iletildi.');
    }

    public function status(Request $request, MessageThread $thread): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:open,closed']]);
        $thread->update(['status' => $data['status']]);
        Activity::log('message.status', $thread, $data);

        return back()->with('status', $data['status'] === 'closed' ? 'Konu kapatıldı.' : 'Konu yeniden açıldı.');
    }

    public function destroy(MessageThread $thread): RedirectResponse
    {
        $thread->delete();
        Activity::log('message.deleted', null, ['subject' => $thread->subject, 'email' => $thread->email]);

        return redirect()->route('admin.messages.index')->with('status', 'Mesaj silindi.');
    }
}
