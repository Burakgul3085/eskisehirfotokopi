<?php

namespace App\Http\Controllers\Store;

use App\Domain\Cart\Cart;
use App\Domain\Delivery\DeliveryRules;
use App\Domain\Orders\PlaceOrder;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\DeliveryDistrict;
use App\Models\Order;
use App\Rules\TurkishPhone;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Tek sayfalık sipariş: iletişim → adres (yalnız hizmet verilen ilçe/mahalle) → teslimat saati → kapıda ödeme yöntemi. */
class CheckoutController extends Controller
{
    public function __construct(private DeliveryRules $rules) {}

    public function show(Request $request, Cart $cart): View|RedirectResponse
    {
        if ($cart->isEmpty()) {
            return redirect()->route('cart.index');
        }
        $districts = DeliveryDistrict::active()->with(['neighborhoods' => fn ($q) => $q->where('is_active', true)])->get();
        $user = $request->user('web');

        return view('store.checkout', [
            'lines' => $cart->lines(),
            'subtotal' => $cart->subtotal(),
            'districts' => $districts,
            'districtData' => $districts->mapWithKeys(fn ($d) => [$d->id => [
                'fee' => (float) $d->fee,
                'free' => $this->rules->freeThreshold($d),
                'min' => $this->rules->minOrder($d),
                'eta' => $this->rules->label($this->rules->estimate($d)),
                'hoods' => $d->neighborhoods->map(fn ($n) => ['name' => $n->name, 'fee' => $n->fee !== null ? (float) $n->fee : null])->values(),
            ]]),
            'slots' => $this->rules->slots(),
            'payments' => $this->rules->paymentMethods(),
            'addresses' => $user ? $user->addresses()->with('district')->get() : collect(),
            'user' => $user,
            'ordersOpen' => Settings::flag('orders_open'),
        ]);
    }

    public function store(Request $request, PlaceOrder $placeOrder): RedirectResponse
    {
        abort_unless(Settings::flag('orders_open'), 503, Settings::get('orders_closed_message'));

        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'phone' => ['required', new TurkishPhone],
            'email' => [$request->user('web') ? 'nullable' : 'required', 'nullable', 'email', 'max:190'],
            'district_id' => ['required', 'integer'],
            'neighborhood' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'min:10', 'max:500'],
            'slot' => ['nullable', 'string', 'max:40'],
            'payment_method' => ['required', Rule::in(array_keys(Order::PAYMENT_METHODS))],
            'note' => ['nullable', 'string', 'max:1000'],
            'save_address' => ['nullable', 'boolean'],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => 'Siparişi tamamlamak için koşulları onaylayın.',
            'email.required' => 'Sipariş durumunu e-postayla bildirebilmemiz için e-posta adresinizi yazın.',
        ]);
        $data['phone'] = TurkishPhone::normalize($data['phone']);
        $data['email'] = $data['email'] ?? $request->user('web')?->email;
        $data['neighborhood'] = trim($data['neighborhood']);

        $user = $request->user('web');
        $order = $placeOrder->handle($data, $user?->id, $request->ip());

        if ($user && $request->boolean('save_address') && ! $user->addresses()->where('address', $data['address'])->exists()) {
            Address::create([
                'user_id' => $user->id, 'title' => 'Adresim '.($user->addresses()->count() + 1), 'name' => $data['name'], 'phone' => $data['phone'],
                'district_id' => $data['district_id'], 'neighborhood' => $data['neighborhood'], 'address' => $data['address'],
                'is_default' => ! $user->addresses()->exists(),
            ]);
        }

        $request->session()->put('last_order', $order->number);

        return redirect()->route('checkout.done', $order);
    }

    public function done(Request $request, Order $order): View
    {
        // Yalnız siparişi bu oturumda veren görür; sonraki ziyaretlerde takip bağlantısı kullanılır.
        abort_unless($request->session()->get('last_order') === $order->number, 404);

        return view('store.checkout-done', ['order' => $order->load('items')]);
    }
}
