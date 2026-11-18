<?php

namespace App\Http\Controllers\Store;

use App\Domain\Reviews\Reviews;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\ProductReview;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function show(Request $request, Product $product, Reviews $reviews): View
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
            'reviewData' => Settings::flag('reviews_enabled') ? $this->reviewData($request, $product, $reviews) : null,
            'questionData' => Settings::flag('questions_enabled') ? $this->questionData($request, $product) : null,
        ]);
    }

    /** Değerlendirme sekmesi: özet (ortalama, yıldız dağılımı), süzgeçli liste ve oturumdaki üyenin durumu. */
    private function reviewData(Request $request, Product $product, Reviews $reviews): array
    {
        $base = fn () => ProductReview::published()->where('product_id', $product->id);
        $distribution = $base()->selectRaw('published_rating as r, count(*) as c')->groupBy('published_rating')->pluck('c', 'r');
        $total = (int) $distribution->sum();
        $photoFilter = $request->boolean('fotografli');
        $sort = in_array($request->query('siralama'), ['yuksek', 'dusuk'], true) ? $request->query('siralama') : 'yeni';
        $withPhotos = fn ($q) => $q->whereHas('photos', fn ($p) => $p->where('is_approved', true));

        $list = $base()->with(['user:id,name', 'photos' => fn ($q) => $q->where('is_approved', true)])
            ->when($photoFilter, $withPhotos)
            ->when($sort === 'yuksek', fn ($q) => $q->orderByDesc('published_rating'))
            ->when($sort === 'dusuk', fn ($q) => $q->orderBy('published_rating'))
            ->orderByDesc('published_at')
            ->paginate(8, ['*'], 'yorumlar')->withQueryString()->fragment('degerlendirmeler');

        return [
            'total' => $total,
            'average' => $total ? round($distribution->reduce(fn ($sum, $c, $r) => $sum + $r * $c, 0) / $total, 1) : 0.0,
            'distribution' => collect(range(5, 1))->mapWithKeys(fn ($r) => [$r => (int) ($distribution[$r] ?? 0)])->all(),
            'photoCount' => $total ? $withPhotos($base())->count() : 0,
            'list' => $list,
            'photoFilter' => $photoFilter,
            'sort' => $sort,
            'eligibility' => $reviews->eligibility($request->user('web'), $product),
            'labels' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) Settings::get('reviews_rating_labels')) ?: []))),
        ];
    }

    /** Soru-cevap sekmesi: yayındaki cevaplanmış sorular. */
    private function questionData(Request $request, Product $product): array
    {
        return [
            'list' => ProductQuestion::visible()->where('product_id', $product->id)->latest('answered_at')
                ->paginate(6, ['*'], 'sorular')->withQueryString()->fragment('sorular'),
            'user' => $request->user('web'),
        ];
    }
}
