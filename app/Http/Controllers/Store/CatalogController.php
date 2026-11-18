<?php

namespace App\Http\Controllers\Store;

use App\Domain\Catalog\CategoryTree;
use App\Domain\Catalog\ProductListing;
use App\Domain\Search\SiteSearch;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Publisher;
use App\Models\SearchMiss;
use App\Models\SearchRedirect;
use App\Support\Highlight;
use App\Support\Money;
use App\Support\Text;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
    public function search(Request $request): View|Response|RedirectResponse
    {
        $q = trim(mb_substr((string) $request->query('q'), 0, 100));
        $isSearch = ProductListing::terms($q) !== [];
        // Filtre/sıralama/sayfa yoksa (yalnız q) doğrudan gitme kuralları uygulanır
        $plain = $isSearch && array_keys($request->query()) === ['q'];

        // 1) Adı birebir eşleşen sayfa, bölüm, SSS, kategori, yayınevi, ürün veya yönetimdeki yönlendirme
        if ($plain && ($direct = $this->site->direct($q))) {
            if (isset($direct['redirect'])) {
                SearchRedirect::whereKey($direct['redirect'])->increment('hits');
            }

            return redirect()->to($direct['url']);
        }

        // 2) Yalnız gerçek eşleşmeler
        $found = $this->site->products($q);
        $others = $isSearch ? $this->site->others($q, 8) : null;
        $otherHits = collect($others)->flatten(1);

        // 3) Hiçbir şey yok: 404 sayfası, aranan kelime rapora yazılır
        if ($isSearch && $found['mode'] === 'none' && $otherHits->isEmpty()) {
            SearchMiss::record($q);

            return response()->view('errors.404', ['search' => $q], 404);
        }

        $base = $found['query'];
        $products = $this->listing->paginate($this->listing->sort($this->listing->filter(clone $base, $request), $request->query('sirala'), $found['used']));

        // Tek bir sonuç kaldıysa doğrudan ona
        if ($plain && $products->total() + $otherHits->count() === 1) {
            return redirect()->to($products->total() === 1 ? route('product.show', $products->first()) : $otherHits->first()['url']);
        }

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

    /** Anlık arama (açılır panel): doğrudan hedef, eşleşen ürünler (vurgulu) ve ürün dışı sonuçlar. */
    public function suggest(Request $request): JsonResponse
    {
        $q = trim(mb_substr((string) $request->query('q'), 0, 100));
        $terms = ProductListing::terms($q);
        if ($terms === []) {
            return response()->json(['direct' => null, 'products' => [], 'categories' => [], 'publishers' => [], 'authors' => [], 'pages' => [], 'faq' => [], 'total' => 0, 'all_url' => '']);
        }

        $data = Cache::remember('search.suggest3.'.md5(implode(' ', $terms).'|'.Text::key($q)), 300, function () use ($q, $terms) {
            $found = $this->site->products($q);
            $total = $found['mode'] === 'none' ? 0 : (clone $found['query'])->count();
            $products = $found['mode'] === 'none' ? [] : $this->listing->sort($found['query'], 'onerilen', $found['used'])
                ->with('publisher:id,name')->limit(6)->get()->map(fn ($p) => [
                    'title' => $p->title,
                    'hl' => Highlight::mark($p->title, $terms),
                    'url' => route('product.show', $p),
                    'image' => $p->imageUrl('sm'),
                    'meta' => $p->publisher?->name,
                    'price' => ($t = $p->defaultPrintType()) ? Money::format($p->price($t)) : null,
                ])->all();
            $direct = $this->site->direct($q);
            unset($direct['redirect']);

            return ['direct' => $direct ? $direct + ['hl' => Highlight::mark($direct['title'], $terms)] : null, 'products' => $products, 'total' => $total] + $this->site->others($q, 4);
        });

        return response()->json($data + ['all_url' => route('search', ['q' => $q])]);
    }

    private static function initial(string $name): string
    {
        $first = mb_substr(Text::upper(trim($name)), 0, 1);

        return in_array($first, self::LETTERS, true) ? $first : '#';
    }
}
