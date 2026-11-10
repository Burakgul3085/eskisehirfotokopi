<?php

namespace App\Domain\Orders;

use App\Domain\Cart\Cart;
use App\Domain\Delivery\DeliveryRules;
use App\Events\OrderPlaced;
use App\Models\DeliveryDistrict;
use App\Models\Order;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Sepetten sipariş oluşturur. Fiyat, teslimat ücreti ve en düşük tutar sunucuda yeniden hesaplanır; tarayıcıdan gelen
 * tutar kullanılmaz. Sipariş numarası "ES" + yılın son iki hanesi + 6 hane (ör. ES26004817).
 */
class PlaceOrder
{
    public function __construct(private Cart $cart, private DeliveryRules $rules) {}

    /**
     * @param  array{name: string, phone: string, email: ?string, district_id: int, neighborhood: string, address: string,
     *               slot: ?string, payment_method: string, note: ?string}  $data
     */
    public function handle(array $data, ?int $userId, ?string $ip): Order
    {
        $lines = $this->cart->lines();
        if ($lines === []) {
            throw ValidationException::withMessages(['cart' => 'Sepetiniz boş.']);
        }
        $district = DeliveryDistrict::active()->find($data['district_id']);
        if (! $district) {
            throw ValidationException::withMessages(['district_id' => 'Bu ilçeye teslimat yapılmıyor.']);
        }
        $hoods = $district->neighborhoods()->where('is_active', true)->pluck('name');
        if ($hoods->isNotEmpty() && ! $hoods->contains($data['neighborhood'])) {
            throw ValidationException::withMessages(['neighborhood' => 'Bu mahalleye teslimat yapılmıyor.']);
        }
        if (! isset($this->rules->paymentMethods()[$data['payment_method']])) {
            throw ValidationException::withMessages(['payment_method' => 'Ödeme yöntemi geçersiz.']);
        }
        $slots = $this->rules->slots();
        if ($slots && ! in_array($data['slot'] ?? null, $slots, true)) {
            throw ValidationException::withMessages(['slot' => 'Teslimat saati seçin.']);
        }

        $subtotal = $this->cart->subtotal();
        $min = $this->rules->minOrder($district);
        if ($min !== null && $subtotal < $min) {
            throw ValidationException::withMessages(['cart' => 'En düşük sipariş tutarı '.Money::format($min).'. Sepetinize '.Money::format($min - $subtotal).' daha ekleyin.']);
        }
        $fee = $this->rules->fee($district, $data['neighborhood'], $subtotal);

        $order = DB::transaction(function () use ($data, $userId, $ip, $lines, $district, $subtotal, $fee) {
            $order = Order::create([
                'number' => $this->number(),
                'tracking_token' => Str::random(40),
                'user_id' => $userId,
                'status' => 'received',
                'customer_name' => $data['name'],
                'phone' => $data['phone'],
                'email' => ($data['email'] ?? null) ?: null,
                'district_id' => $district->id,
                'district_name' => $district->name,
                'neighborhood' => $data['neighborhood'],
                'address' => $data['address'],
                'delivery_slot' => $data['slot'] ?? null,
                'delivery_date' => $this->rules->estimate($district)->toDateString(),
                'payment_method' => $data['payment_method'],
                'subtotal' => $subtotal,
                'delivery_fee' => $fee,
                'total' => round($subtotal + $fee, 2),
                'note' => ($data['note'] ?? null) ?: null,
                'ip' => $ip,
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'kind' => $line->kind,
                    'product_id' => $line->product?->id,
                    'print_job_id' => $line->printJobId,
                    'title' => $line->title,
                    'print_type' => $line->printType,
                    'unit_price' => $line->unitPrice,
                    'quantity' => $line->quantity,
                    'line_total' => $line->total(),
                    'meta' => array_filter([
                        'publisher' => $line->product?->publisher?->name,
                        'isbn' => $line->product?->isbn,
                        'code' => $line->product?->code,
                        'image' => $line->image,
                    ] + $line->meta),
                ]);
                if ($line->product) {
                    DB::table('products')->where('id', $line->product->id)->increment('sales_count', $line->quantity);
                }
            }
            $order->history()->create(['status' => 'received', 'customer_notified' => true]);

            return $order;
        });

        $this->cart->clear();
        OrderPlaced::dispatch($order);

        return $order;
    }

    private function number(): string
    {
        do {
            $number = 'ES'.now()->format('y').str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (Order::where('number', $number)->exists());

        return $number;
    }
}
