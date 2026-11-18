<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Hesabım › Değerlendirmelerim: teslim alınıp henüz değerlendirilmeyen ürünler ve yazılan değerlendirmeler. */
class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $reviews = ProductReview::where('user_id', $user->id)->with('product.publisher:id,name', 'photos')->latest('submitted_at')->get();
        $deliveredIds = OrderItem::where('kind', 'product')->whereNotNull('product_id')
            ->whereHas('order', fn ($q) => $q->where('user_id', $user->id)->where('status', 'delivered'))
            ->distinct()->pluck('product_id');
        $waiting = Product::visible()->with('publisher:id,name,slug')->whereIn('id', $deliveredIds)
            ->whereNotIn('id', $reviews->pluck('product_id'))->orderBy('title')->get();

        return view('account.reviews', ['reviews' => $reviews->filter(fn ($r) => $r->product), 'waiting' => $waiting]);
    }
}
