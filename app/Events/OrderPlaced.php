<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/** Yeni sipariş verildi (yöneticiye ve müşteriye e-posta gider). */
class OrderPlaced
{
    use Dispatchable;

    public function __construct(public Order $order) {}
}
