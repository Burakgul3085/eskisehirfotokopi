<?php

namespace App\Http\Controllers\Store;

use App\Domain\Cart\Cart;
use App\Domain\Delivery\DeliveryRules;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(private Cart $cart, private DeliveryRules $rules) {}

    public function index(): View
    {
        $subtotal = $this->cart->subtotal();

        return view('store.cart', [
            'lines' => $this->cart->lines(),
            'subtotal' => $subtotal,
            'remaining' => $this->rules->remainingForFree($subtotal),
            'threshold' => $this->rules->freeThreshold(),
            'minOrder' => $this->rules->minOrder(),
        ]);
    }

    public function add(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'print_type' => ['required', Rule::in(array_keys(Product::PRINT_TYPES))],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.Cart::MAX_QTY],
        ]);
        $product = Product::visible()->find($data['product_id']);
        if (! $product || $product->price($data['print_type']) === null) {
            return $this->reply($request, 'Bu ürün şu anda satışta değil.', false);
        }
        try {
            $this->cart->addProduct($product, $data['print_type'], (int) ($data['quantity'] ?? 1));
        } catch (\DomainException $e) {
            return $this->reply($request, $e->getMessage(), false);
        }

        return $this->reply($request, '“'.$product->title.'” sepete eklendi.');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['key' => ['required', 'string', 'max:80'], 'quantity' => ['required', 'integer', 'min:0', 'max:'.Cart::MAX_QTY]]);
        $this->cart->setQuantity($data['key'], (int) $data['quantity']);

        return redirect()->route('cart.index');
    }

    public function remove(Request $request): RedirectResponse
    {
        $this->cart->remove((string) $request->input('key'));

        return redirect()->route('cart.index')->with('status', 'Ürün sepetten çıkarıldı.');
    }

    private function reply(Request $request, string $message, bool $ok = true): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => $ok, 'message' => $message, 'count' => $this->cart->count(), 'subtotal' => Money::format($this->cart->subtotal())], $ok ? 200 : 422);
        }

        return $ok
            ? back()->with('status', $message)->with('cart_added', true)
            : back()->withErrors(['cart' => $message]);
    }
}
