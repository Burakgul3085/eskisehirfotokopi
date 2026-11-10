<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function show(Product $product): View
    {
        // Taslak/pasif ürün yalnız yöneticiye önizleme olarak görünür.
        $preview = $product->status !== 'active';
        abort_if($preview && ! auth('admin')->check(), 404);

        $product->load('publisher', 'author', 'category');
        if (! $preview) {
            DB::table('products')->where('id', $product->id)->increment('view_count');
        }

        // Önbellekte yalnız kimlikler tutulur (model değil); ürünler kimlikle tek sorguda yüklenir.
        $related = Cache::remember("product.{$product->id}.related", 1800, function () use ($product) {
            $base = fn () => Product::visible()->whereKeyNot($product->id)->orderByRaw('image_key IS NULL')->orderByDesc('sales_count');

            return [
                'similar' => $product->category_id ? $base()->where('category_id', $product->category_id)->limit(12)->pluck('id')->all() : [],
                'publisher' => $product->publisher_id ? $base()->where('publisher_id', $product->publisher_id)
                    ->when($product->category_id, fn ($q) => $q->where('category_id', '!=', $product->category_id))->limit(12)->pluck('id')->all() : [],
            ];
        });
        $ids = array_merge($related['similar'], $related['publisher']);
        $loaded = $ids ? Product::visible()->with('publisher:id,name,slug')->whereIn('id', $ids)->get()->keyBy('id') : collect();
        $pick = fn (array $list) => collect($list)->map(fn ($id) => $loaded[$id] ?? null)->filter()->values();

        return view('store.product', [
            'product' => $product,
            'trail' => $product->category?->ancestorsAndSelf() ?? collect(),
            'preview' => $preview,
            'similar' => $pick($related['similar']),
            'samePublisher' => $pick($related['publisher']),
        ]);
    }
}
