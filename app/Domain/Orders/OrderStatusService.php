<?php

namespace App\Domain\Orders;

use App\Events\OrderStatusChanged;
use App\Models\Admin;
use App\Models\Order;
use App\Support\Activity;
use Illuminate\Support\Facades\DB;

/** Sipariş durum geçişleri. Her geçiş zaman çizelgesine yazılır; istenirse müşteriye e-posta gider (§7). */
class OrderStatusService
{
    public function change(Order $order, string $status, ?string $note = null, ?Admin $admin = null, bool $notify = true): void
    {
        if (! isset(Order::STATUSES[$status])) {
            throw new \InvalidArgumentException('Geçersiz durum.');
        }
        if ($status === $order->status && $note === null) {
            return;
        }
        if ($order->status === 'cancelled' && $status !== 'cancelled') {
            throw new \DomainException('İptal edilen sipariş yeniden açılamaz.');
        }

        $entry = DB::transaction(function () use ($order, $status, $note, $admin, $notify) {
            $wasCancelled = $order->status === 'cancelled';
            $order->forceFill(['status' => $status, 'cancel_reason' => $status === 'cancelled' ? ($note ?: $order->cancel_reason) : $order->cancel_reason])->save();
            if ($status === 'cancelled' && ! $wasCancelled) {
                // İptalde satış sayaçları geri alınır.
                foreach ($order->items()->whereNotNull('product_id')->get() as $item) {
                    DB::table('products')->where('id', $item->product_id)->where('sales_count', '>=', $item->quantity)->decrement('sales_count', $item->quantity);
                }
            }

            return $order->history()->create([
                'status' => $status, 'note' => $note, 'admin_id' => $admin?->id,
                'customer_notified' => $notify && (bool) $order->email,
            ]);
        });

        Activity::log('order.status', $order, ['status' => $status, 'note' => $note]);
        OrderStatusChanged::dispatch($order, $entry, $notify);
    }
}
