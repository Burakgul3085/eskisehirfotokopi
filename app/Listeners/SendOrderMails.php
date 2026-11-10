<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Mail\SiteMail;
use Illuminate\Support\Facades\Mail;

/** Sipariş e-postaları (§7; olay keşfiyle bağlanır): yeni siparişte müşteriye özet ve yöneticiye bildirim; her durum değişikliğinde müşteriye. */
class SendOrderMails
{
    public function handlePlaced(OrderPlaced $event): void
    {
        $order = $event->order;
        if ($order->email) {
            Mail::to($order->email, $order->customer_name)->send(SiteMail::orderPlaced($order));
        }
        if ($admins = SiteMail::adminRecipients()) {
            Mail::to($admins)->send(SiteMail::orderAdmin($order));
        }
    }

    public function handleStatus(OrderStatusChanged $event): void
    {
        if ($event->notify && $event->order->email && $event->entry->status !== 'received') {
            Mail::to($event->order->email, $event->order->customer_name)->send(SiteMail::orderStatus($event->order, $event->entry));
        }
    }
}
