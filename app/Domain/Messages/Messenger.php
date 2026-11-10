<?php

namespace App\Domain\Messages;

use App\Mail\SiteMail;
use App\Models\Admin;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Order;
use App\Support\Activity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Müşteri mesajları: iletişim formundan yeni konu, müşterinin yanıtı (yöneticiye e-posta) ve yöneticinin yanıtı
 * (müşteriye e-posta, gizli bağlantıyla). Konu durumu: open (yanıt bekliyor) → answered → closed.
 */
class Messenger
{
    /** @param  array{name: string, email: string, phone: ?string, subject: string, body: string, order_number: ?string}  $data */
    public function start(array $data, ?int $userId, ?string $ip): MessageThread
    {
        $order = filled($data['order_number'] ?? null) ? Order::where('number', strtoupper(trim($data['order_number'])))->first() : null;

        [$thread, $message] = DB::transaction(function () use ($data, $userId, $ip, $order) {
            $thread = MessageThread::create([
                'token' => Str::random(40), 'user_id' => $userId,
                // Sipariş yalnız aynı telefona/e-postaya aitse bağlanır (başkasının siparişi görünmesin).
                'order_id' => $order && ($order->email === $data['email'] || ($data['phone'] && $order->phone === $data['phone'])) ? $order->id : null,
                'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?: null,
                'subject' => $data['subject'], 'status' => 'open', 'unread_admin' => true, 'last_message_at' => now(),
            ]);
            $message = $thread->messages()->create(['sender' => 'customer', 'body' => $data['body'], 'ip' => $ip]);

            return [$thread, $message];
        });
        $this->notifyAdmins($thread, $message);

        return $thread;
    }

    public function customerReply(MessageThread $thread, string $body, ?string $ip): Message
    {
        $message = $thread->messages()->create(['sender' => 'customer', 'body' => $body, 'ip' => $ip]);
        $thread->update(['status' => 'open', 'unread_admin' => true, 'last_message_at' => now()]);
        $this->notifyAdmins($thread, $message);

        return $message;
    }

    public function adminReply(MessageThread $thread, string $body, Admin $admin, bool $close = false): Message
    {
        $message = $thread->messages()->create(['sender' => 'admin', 'admin_id' => $admin->id, 'body' => $body]);
        $thread->update(['status' => $close ? 'closed' : 'answered', 'unread_admin' => false, 'last_message_at' => now()]);
        Mail::to($thread->email, $thread->name)->send(SiteMail::messageReply($thread, $message));
        Activity::log('message.replied', $thread);

        return $message;
    }

    private function notifyAdmins(MessageThread $thread, Message $message): void
    {
        if ($admins = SiteMail::adminRecipients()) {
            Mail::to($admins)->send(SiteMail::messageAdmin($thread->loadMissing('order'), $message));
        }
    }
}
