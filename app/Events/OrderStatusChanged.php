<?php

namespace App\Events;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Foundation\Events\Dispatchable;

/** Sipariş durumu değişti (müşteriye e-posta gider). */
class OrderStatusChanged
{
    use Dispatchable;

    public function __construct(public Order $order, public OrderStatusHistory $entry, public bool $notify = true) {}
}
