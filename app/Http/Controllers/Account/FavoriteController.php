<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Hesabım › Favorilerim. */
class FavoriteController extends Controller
{
    public function index(Request $request): View
    {
        $products = $request->user()->favorites()->visible()->with('publisher:id,name,slug')->paginate(24);

        return view('account.favorites', ['products' => $products]);
    }

    public function toggle(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $result = $request->user()->favorites()->toggle($product->id);
        $added = $result['attached'] !== [];

        if ($request->expectsJson()) {
            return response()->json(['favorite' => $added]);
        }

        return back()->with('status', $added ? 'Favorilere eklendi.' : 'Favorilerden çıkarıldı.');
    }
}
