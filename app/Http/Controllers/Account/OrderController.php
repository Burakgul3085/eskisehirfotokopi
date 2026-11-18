<?php

namespace App\Http\Controllers\Account;

use App\Domain\Cart\Cart;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Hesabım › Siparişlerim. */
class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $filter = (string) $request->query('durum');
        $base = fn () => $request->user()->orders();
        $groups = [
            'devam' => fn ($q) => $q->whereNotIn('status', ['delivered', 'cancelled']),
            'teslim' => fn ($q) => $q->where('status', 'delivered'),
            'iptal' => fn ($q) => $q->where('status', 'cancelled'),
        ];
        $query = $base()->with('items')->withCount('items');
        if (isset($groups[$filter])) {
            $groups[$filter]($query);
        } else {
            $filter = '';
        }

        return view('account.orders', [
            'orders' => $query->paginate(10)->withQueryString(),
            'filter' => $filter,
            'counts' => ['' => $base()->count()] + array_map(fn ($g) => $g($base())->count(), $groups),
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return view('store.order', ['order' => $order->load('items.product:id,slug,status', 'history'), 'context' => 'account']);
    }

    /** Siparişteki katalog ürünlerini güncel fiyatlarıyla yeniden sepete ekler. */
    public function reorder(Request $request, Order $order, Cart $cart): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        $added = 0;
        $products = Product::visible()->whereIn('id', $order->items->pluck('product_id')->filter())->get()->keyBy('id');
        foreach ($order->items as $item) {
            $product = $products[$item->product_id] ?? null;
            if ($product && $item->print_type && $product->price($item->print_type) !== null) {
                try {
                    $cart->addProduct($product, $item->print_type, $item->quantity, $item->meta['binding'] ?? 'none');
                    $added++;
                } catch (\DomainException) {
                    break;
                }
            }
        }

        return $added
            ? redirect()->route('cart.index')->with('status', $added.' ürün güncel fiyatlarıyla sepete eklendi.')
            : back()->withErrors(['cart' => 'Bu siparişteki ürünler artık satışta değil.']);
    }
}
