<?php

namespace App\Http\Controllers\Store;

use App\Domain\Catalog\CategoryTree;
use App\Domain\Catalog\ProductListing;
use App\Domain\Search\SiteSearch;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Publisher;
use App\Support\Money;
use App\Support\Text;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** Kategori, yayınevi ve arama sayfaları; anlık arama önerileri. */
class CatalogController extends Controller
{
    public const LETTERS = ['A', 'B', 'C', 'Ç', 'D', 'E', 'F', 'G', 'H', 'I', 'İ', 'J', 'K', 'L', 'M', 'N', 'O', 'Ö', 'P', 'R', 'S', 'Ş', 'T', 'U', 'Ü', 'V', 'Y', 'Z', '#'];

    public function __construct(private ProductListing $listing, private CategoryTree $tree, private SiteSearch $site) {}

    public function category(Request $request, Category $category): View
    {
        abort_unless($category->is_active, 404);
        $trail = $category->ancestorsAndSelf();
        abort_if($trail->contains(fn ($c) => ! $c->is_active), 404);

        $base = $this->listing->base($category);
        $visible = $this->tree->visibleIds();
        // İçinde yayında ürün olmayan kategori menüde görünmez; adresi doğrudan açılırsa "henüz ürün yok" der.
        $empty = ! in_array($category->id, $visible, true);
        $products = $this->listing->paginate($this->listing->sort($this->listing->filter(clone $base, $request), $request->query('sirala')));

        return view('store.catalog.listing', [
            'heading' => $category->name,
            'description' => $category->description,
            'trail' => $trail,
            'category' => $category,
            'children' => $category->children()->active()->whereIn('id', $visible)->get(),
            'products' => $products,
            'facets' => $this->listing->facets($base),
            'emptyCategory' => $empty,
            'metaTitle' => $category->meta_title ?: $category->name,
            'metaDescription' => $category->meta_description ?: $category->description,
            'noindex' => $empty,
        ]);
    }

    public function publishers(): View
    {
        // Ürün sayıları tek gruplu sorguyla (yayınevi başına alt sorgu 10 bin üründe yavaştı).
        $counts = Product::visible()->whereNotNull('publisher_id')->selectRaw('publisher_id, count(*) as c')->groupBy('publisher_id')->pluck('c', 'publisher_id');
        $publishers = Publisher::active()->orderBy('name')->get()
            ->each(fn ($p) => $p->products_count = (int) ($counts[$p->id] ?? 0))
            ->filter(fn ($p) => $p->products_count > 0 || $p->is_featured);
        $byLetter = $publishers->groupBy(fn ($p) => self::initial($p->name));
        $groups = collect(self::LETTERS)->mapWithKeys(fn ($l) => [$l => $byLetter[$l] ?? collect()]);

        // Öne çıkanlar: yönetimde işaretlenenler; hiçbiri yoksa en çok ürünü olanlar
        $featured = $publishers->where('is_featured', true)->sortBy('position')->take(12);
        if ($featured->isEmpty()) {
            $featured = $publishers->sortByDesc('products_count')->take(6);
        }
        // 3D karusel: en çok ürünü olan yayınevleri (kitap kapağı olarak)
        $carousel = $publishers->sortByDesc('products_count')->take(12)->values()->map(fn ($p) => [
            'name' => $p->name, 'initials' => $p->initials(), 'logo' => $p->logoUrl(), 'url' => route('publisher.show', $p), 'count' => $p->products_count,
        ]);

        return view('store.catalog.publishers', [
            'groups' => $groups,
            'featured' => $featured->values(),
            'total' => $publishers->count(),
            'productTotal' => $publishers->sum('products_count'),
            'carousel' => $carousel,
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
    public function search(Request $request): View|Response
    {
        $q = trim(mb_substr((string) $request->query('q'), 0, 100));
        $isSearch = ProductListing::terms($q) !== [];
        // Site geneli arama: yalnız gerçek eşleşmeler; ürün de ürün dışı sonuç da yoksa 404 sayfası
        $found = $this->site->products($q);
        $others = $isSearch ? $this->site->others($q, 8) : null;
        if ($isSearch && $found['mode'] === 'none' && collect($others)->flatten(1)->isEmpty()) {
            return response()->view('errors.404', ['search' => $q], 404);
        }
        $base = $found['query'];
        $sort = $request->query('sirala');
        $products = $this->listing->paginate($this->listing->sort($this->listing->filter(clone $base, $request), $sort, $found['used']));

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
            'searchMode' => $isSearch ? $found['mode'] : null,
            'searchUsed' => $found['used'],
            'searchOthers' => $others,
        ]);
    }

    public function suggest(Request $request): JsonResponse
    {
        $q = trim(mb_substr((string) $request->query('q'), 0, 100));
        $terms = ProductListing::terms($q);
        $empty = ['products' => [], 'categories' => [], 'publishers' => [], 'authors' => [], 'pages' => [], 'faq' => [], 'total' => 0, 'mode' => 'none', 'used' => ''];
        if ($terms === []) {
            // Kutu boşken: hızlı erişim (site bölümleri ve popüler kategoriler)
            return response()->json($empty + ['quick' => $this->site->quick(), 'all_url' => route('search')]);
        }

        $data = Cache::remember('search.suggest2.'.md5(implode(' ', $terms)), 300, function () use ($q) {
            $found = $this->site->products($q);
            $total = $found['mode'] === 'none' ? 0 : (clone $found['query'])->count();
            $products = $found['mode'] === 'none' ? [] : $this->listing->sort($found['query'], 'onerilen', $found['used'])
                ->with('publisher:id,name')->limit(6)->get()->map(fn ($p) => [
                    'title' => $p->title,
                    'url' => route('product.show', $p),
                    'image' => $p->imageUrl('sm'),
                    'meta' => $p->publisher?->name,
                    'price' => ($t = $p->defaultPrintType()) ? Money::format($p->price($t)) : null,
                ])->all();

            return ['products' => $products, 'total' => $total, 'mode' => $found['mode'], 'used' => $found['used']] + $this->site->others($q, 4);
        });

        return response()->json($data + ['quick' => null, 'all_url' => route('search', ['q' => $q])]);
    }

    private static function initial(string $name): string
    {
        $first = mb_substr(Text::upper(trim($name)), 0, 1);

        return in_array($first, self::LETTERS, true) ? $first : '#';
    }
}
