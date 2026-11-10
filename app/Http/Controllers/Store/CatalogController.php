<?php

namespace App\Http\Controllers\Store;

use App\Domain\Catalog\ProductListing;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Publisher;
use App\Support\Money;
use App\Support\Text;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** Kategori, yayınevi ve arama sayfaları; anlık arama önerileri. */
class CatalogController extends Controller
{
    public const LETTERS = ['A', 'B', 'C', 'Ç', 'D', 'E', 'F', 'G', 'H', 'I', 'İ', 'J', 'K', 'L', 'M', 'N', 'O', 'Ö', 'P', 'R', 'S', 'Ş', 'T', 'U', 'Ü', 'V', 'Y', 'Z', '#'];

    public function __construct(private ProductListing $listing) {}

    public function category(Request $request, Category $category): View
    {
        abort_unless($category->is_active, 404);
        $trail = $category->ancestorsAndSelf();
        abort_if($trail->contains(fn ($c) => ! $c->is_active), 404);

        $base = $this->listing->base($category);
        $products = $this->listing->paginate($this->listing->sort($this->listing->filter(clone $base, $request), $request->query('sirala')));

        return view('store.catalog.listing', [
            'heading' => $category->name,
            'description' => $category->description,
            'trail' => $trail,
            'category' => $category,
            'children' => $category->children()->active()->get(),
            'products' => $products,
            'facets' => $this->listing->facets($base),
            'metaTitle' => $category->meta_title ?: $category->name,
            'metaDescription' => $category->meta_description ?: $category->description,
        ]);
    }

    public function publishers(): View
    {
        $publishers = Publisher::active()
            ->withCount(['products' => fn ($q) => $q->visible()])
            ->orderBy('name')->get()
            ->filter(fn ($p) => $p->products_count > 0 || $p->is_featured);
        $byLetter = $publishers->groupBy(fn ($p) => self::initial($p->name));
        $groups = collect(self::LETTERS)->mapWithKeys(fn ($l) => [$l => $byLetter[$l] ?? collect()]);

        return view('store.catalog.publishers', [
            'groups' => $groups,
            'featured' => $publishers->where('is_featured', true)->sortBy('position')->take(12),
            'total' => $publishers->count(),
        ]);
    }

    public function publisher(Request $request, Publisher $publisher): View
    {
        abort_unless($publisher->is_active, 404);
        $base = $this->listing->base(null, $publisher);
        $products = $this->listing->paginate($this->listing->sort($this->listing->filter(clone $base, $request, false), $request->query('sirala')));

        // Yayınevinin ürünlerinin bulunduğu kategoriler (kısayol çipleri)
        $categoryIds = (clone $base)->whereNotNull('category_id')->distinct()->pluck('category_id');
        $chips = Category::active()->whereIn('id', $categoryIds)
            ->withCount(['products' => fn ($q) => $q->visible()->where('publisher_id', $publisher->id)])
            ->orderByDesc('products_count')->limit(12)->get();

        return view('store.catalog.listing', [
            'heading' => $publisher->name,
            'description' => $publisher->description,
            'publisher' => $publisher,
            'trail' => collect(),
            'children' => collect(),
            'chips' => $chips,
            'products' => $products,
            'facets' => $this->listing->facets($base),
            'metaTitle' => $publisher->meta_title ?: $publisher->name.' kitapları',
            'metaDescription' => $publisher->meta_description ?: $publisher->description,
        ]);
    }

    /** Arama sonuçları; sorgu boşsa tüm ürünler (/urunler). */
    public function search(Request $request): View
    {
        $q = trim(mb_substr((string) $request->query('q'), 0, 100));
        $base = $this->listing->base(null, null, $q);
        $products = $this->listing->paginate($this->listing->sort($this->listing->filter(clone $base, $request), $request->query('sirala'), $q));
        $isSearch = ProductListing::terms($q) !== [];

        return view('store.catalog.listing', [
            'heading' => $isSearch ? '“'.$q.'” için sonuçlar' : 'Tüm ürünler',
            'description' => null,
            'search' => $q,
            'trail' => collect(),
            'children' => collect(),
            'products' => $products,
            'facets' => $this->listing->facets($base),
            'metaTitle' => $isSearch ? $q.' araması' : 'Tüm ürünler',
            'metaDescription' => null,
            'noindex' => $isSearch,
        ]);
    }

    /** Anlık arama paneli (header). Sonuçlar 5 dakika önbellekte. */
    public function suggest(Request $request): JsonResponse
    {
        $q = trim(mb_substr((string) $request->query('q'), 0, 100));
        $terms = ProductListing::terms($q);
        if ($terms === []) {
            return response()->json(['products' => [], 'publishers' => [], 'categories' => [], 'total' => 0, 'all_url' => route('search')]);
        }

        $data = Cache::remember('search.suggest.'.md5(implode(' ', $terms)), 300, function () use ($q, $terms) {
            $query = $this->listing->base(null, null, $q);
            $total = (clone $query)->count();
            $products = $this->listing->sort($query, 'onerilen', $q)->with('publisher:id,name')->limit(6)->get()->map(fn ($p) => [
                'title' => $p->title,
                'url' => route('product.show', $p),
                'image' => $p->imageUrl('sm'),
                'meta' => $p->publisher?->name,
                'price' => ($t = $p->defaultPrintType()) ? Money::format($p->price($t)) : null,
            ])->all();
            $like = fn (string $col) => function ($w) use ($col, $terms) {
                foreach ($terms as $t) {
                    $w->where($col, 'like', '%'.$t.'%');
                }
            };
            $publishers = Publisher::active()->where($like('match_key'))->orderBy('name')->limit(4)->get()
                ->map(fn ($p) => ['title' => $p->name, 'url' => route('publisher.show', $p), 'image' => $p->logoUrl()])->all();
            $categories = Category::active()->where($like('normalized_name'))->orderBy('depth')->limit(4)->get()
                ->map(fn ($c) => ['title' => $c->fullName(), 'url' => route('category.show', $c)])->all();

            return ['products' => $products, 'publishers' => $publishers, 'categories' => $categories, 'total' => $total];
        });

        return response()->json($data + ['all_url' => route('search', ['q' => $q])]);
    }

    private static function initial(string $name): string
    {
        $first = mb_substr(Text::upper(trim($name)), 0, 1);

        return in_array($first, self::LETTERS, true) ? $first : '#';
    }
}
