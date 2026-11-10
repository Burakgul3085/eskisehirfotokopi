<?php

namespace App\Domain\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\Publisher;
use App\Support\Text;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Vitrin ürün listeleri (kategori, yayınevi, arama) için ortak filtre ve sıralama. Filtreler URL'de tutulur
 * (paylaşılabilir, SEO uyumlu): ?yayinevi[]=3&tip=renkli&min=50&max=200&yil=2025&sirala=fiyat-artan
 */
class ProductListing
{
    public const SORTS = [
        'onerilen' => 'Önerilen',
        'yeni' => 'En yeniler',
        'cok-satan' => 'Çok satanlar',
        'fiyat-artan' => 'Fiyat (artan)',
        'fiyat-azalan' => 'Fiyat (azalan)',
        'a-z' => 'Ada göre (A-Z)',
        'z-a' => 'Ada göre (Z-A)',
    ];

    public const PER_PAGE = 24;

    private const PRICE = 'COALESCE(products.price_bw, products.price_color)';

    /** Aramadaki terimler (en az 2 karakter, en fazla 8 terim). */
    public static function terms(?string $q): array
    {
        $key = Text::key((string) $q);
        $terms = array_values(array_filter(explode(' ', $key), fn ($t) => mb_strlen($t) >= 2 || ctype_digit($t)));

        return array_slice(array_unique($terms), 0, 8);
    }

    /** Yayındaki ürünler + bağlam (kategori alt ağacı, yayınevi, arama). */
    public function base(?Category $category = null, ?Publisher $publisher = null, ?string $q = null): Builder
    {
        $query = Product::query()->visible();
        if ($category) {
            $query->whereIn('category_id', Category::query()->active()->where('path', 'like', $category->path.'%')->select('id'));
        }
        if ($publisher) {
            $query->where('publisher_id', $publisher->id);
        }
        foreach (self::terms($q) as $term) {
            $query->where('search_text', 'like', '%'.$term.'%');
        }

        return $query;
    }

    /** Kullanıcı filtreleri. */
    public function filter(Builder $query, Request $request, bool $withPublisher = true): Builder
    {
        $publishers = array_filter(array_map('intval', (array) $request->query('yayinevi', [])));
        if ($withPublisher && $publishers) {
            $query->whereIn('publisher_id', $publishers);
        }
        if ($author = (int) $request->query('yazar')) {
            $query->where('author_id', $author);
        }
        if ($request->query('tip') === 'renkli') {
            $query->whereNotNull('price_color');
        } elseif ($request->query('tip') === 'siyah-beyaz') {
            $query->whereNotNull('price_bw');
        }
        $min = is_numeric($request->query('min')) ? (float) $request->query('min') : null;
        $max = is_numeric($request->query('max')) ? (float) $request->query('max') : null;
        if ($min !== null) {
            $query->whereRaw(self::PRICE.' >= ?', [$min]);
        }
        if ($max !== null) {
            $query->whereRaw(self::PRICE.' <= ?', [$max]);
        }
        if ($year = (int) $request->query('yil')) {
            $query->where('print_year', $year);
        }

        return $query;
    }

    public function sort(Builder $query, ?string $sort, ?string $q = null): Builder
    {
        $sort = isset(self::SORTS[$sort]) ? $sort : 'onerilen';
        match ($sort) {
            'yeni' => $query->orderByDesc('published_at'),
            'cok-satan' => $query->orderByDesc('sales_count'),
            'fiyat-artan' => $query->orderByRaw(self::PRICE.' asc'),
            'fiyat-azalan' => $query->orderByRaw(self::PRICE.' desc'),
            'a-z' => $query->orderBy('title'),
            'z-a' => $query->orderByDesc('title'),
            default => $this->recommended($query, $q),
        };

        return $query->orderByDesc('id');
    }

    /** Önerilen: aramada başlığı sorguyla başlayanlar önce; sonra öne çıkan, çok satan, görselli ürünler. */
    private function recommended(Builder $query, ?string $q): void
    {
        $key = Text::key((string) $q);
        if ($key !== '') {
            $query->orderByRaw('CASE WHEN search_text LIKE ? THEN 0 WHEN search_text LIKE ? THEN 1 ELSE 2 END', [' '.$key.'%', '% '.$key.'%']);
        }
        $query->orderByDesc('is_featured')->orderByRaw('image_key IS NULL')->orderByDesc('sales_count')->orderByDesc('published_at');
    }

    public function paginate(Builder $query): LengthAwarePaginator
    {
        return $query->with(['publisher:id,name,slug', 'author:id,name'])->paginate(self::PER_PAGE)->withQueryString();
    }

    /**
     * Filtre paneli için yayınevleri (sonuçtaki ürün sayısıyla), yıllar ve fiyat aralığı.
     *
     * @return array{publishers: Collection, years: Collection, price: array{min: ?float, max: ?float}}
     */
    public function facets(Builder $base): array
    {
        $ids = (clone $base)->select('products.id');
        $publishers = Publisher::query()
            ->joinSub((clone $base)->select('publisher_id', DB::raw('count(*) as c'))->groupBy('publisher_id'), 'pc', 'pc.publisher_id', '=', 'publishers.id')
            ->orderBy('publishers.name')->get(['publishers.id', 'publishers.name', 'pc.c as products_count']);
        $years = (clone $base)->whereNotNull('print_year')->distinct()->orderByDesc('print_year')->limit(15)->pluck('print_year');
        $price = Product::query()->whereIn('id', $ids)->selectRaw('MIN('.self::PRICE.') as mn, MAX('.self::PRICE.') as mx')->first();

        return [
            'publishers' => $publishers,
            'years' => $years,
            'price' => ['min' => $price?->mn !== null ? floor((float) $price->mn) : null, 'max' => $price?->mx !== null ? ceil((float) $price->mx) : null],
        ];
    }

    /** Etkin filtre sayısı (mobil "Filtrele (2)" düğmesi). */
    public static function activeFilterCount(Request $request): int
    {
        return count(array_filter([
            (array) $request->query('yayinevi', []),
            $request->query('yazar'), $request->query('tip'), $request->query('min'), $request->query('max'), $request->query('yil'),
        ], fn ($v) => $v !== null && $v !== '' && $v !== []));
    }
}
