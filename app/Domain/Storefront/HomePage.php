<?php

namespace App\Domain\Storefront;

use App\Domain\Catalog\CategoryTree;
use App\Models\Category;
use App\Models\HomeBlock;
use App\Models\Product;
use App\Models\Publisher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Ana sayfa blokları ve verileri. Hangi blokta hangi kayıtların (ürün/kategori/yayınevi kimlikleri) gösterileceği
 * 10 dakika önbellekte tutulur; kayıtlar her istekte kimlikle tek sorguda yüklenir. Önbelleğe model değil yalnızca
 * dizi yazılır (Laravel önbellekten nesne açmayı güvenlik gereği reddeder). Blok kaydedilince önbellek silinir.
 */
class HomePage
{
    public function __construct(private CategoryTree $tree) {}

    /** @return list<array{block: HomeBlock, data: mixed}> */
    public function blocks(): array
    {
        $rows = Cache::remember(HomeBlock::CACHE_KEY, 600, fn () => HomeBlock::query()->visible()->get()
            ->map(fn (HomeBlock $b) => ['attributes' => $b->getAttributes(), 'ids' => $this->ids($b)])->all());

        $out = [];
        foreach ($rows as $row) {
            $block = (new HomeBlock)->newFromBuilder($row['attributes']);
            $data = $this->load($block, $row['ids']);
            if ($this->hasContent($block, $data)) {
                $out[] = ['block' => $block, 'data' => $data];
            }
        }

        return $out;
    }

    public static function flush(): void
    {
        Cache::forget(HomeBlock::CACHE_KEY);
    }

    /** Blokta gösterilecek kayıtların kimlikleri (sıralı). */
    private function ids(HomeBlock $b): ?array
    {
        return match ($b->type) {
            'products' => $this->productIds($b),
            'categories' => $this->categoryIds($b),
            'publishers' => $this->publisherIds($b),
            default => null,
        };
    }

    private function load(HomeBlock $b, ?array $ids): mixed
    {
        if ($ids === null) {
            return null;
        }
        $model = match ($b->type) {
            'products' => Product::query()->visible()->with('publisher:id,name,slug'),
            'categories' => Category::query()->active(),
            'publishers' => Publisher::query()->active(),
        };
        $found = $ids ? $model->whereIn('id', $ids)->get()->keyBy('id') : collect();
        $items = collect($ids)->map(fn ($id) => $found[$id] ?? null)->filter()->values();

        if ($b->type === 'categories') {
            $children = $this->tree->all()->groupBy('parent_id');
            $items->each(fn (Category $c) => $c->setAttribute('child_names',
                ($children[$c->id] ?? collect())->where('is_active', true)->take(4)->pluck('name')->all()));
        }

        return $items;
    }

    private function productIds(HomeBlock $b): array
    {
        $limit = max(1, min(24, (int) $b->setting('limit', 12)));
        $q = Product::query()->visible();
        if ($b->setting('source') !== 'manual') {
            $q->orderByRaw('image_key IS NULL'); // görseli olmayan ürün vitrinde sona
        }

        match ($b->setting('source')) {
            'bestsellers' => $q->orderByDesc('sales_count')->orderByDesc('published_at'),
            'popular' => $q->orderByDesc('view_count'),
            'featured' => $q->where('is_featured', true)->orderByDesc('published_at'),
            'discounted' => $q->where(fn ($w) => $w->whereColumn('compare_bw', '>', 'price_bw')->orWhereColumn('compare_color', '>', 'price_color'))->orderByDesc('published_at'),
            'category' => ($cat = Category::find($b->setting('category_id')))
                ? $q->whereIn('category_id', Category::where('path', 'like', $cat->path.'%')->pluck('id')->all())->orderByDesc('sales_count')->orderByDesc('published_at')
                : $q->whereRaw('1 = 0'),
            'publisher' => $q->where('publisher_id', (int) $b->setting('publisher_id'))->orderByDesc('sales_count')->orderByDesc('published_at'),
            'manual' => $q->whereIn('id', $ids = array_map('intval', (array) $b->setting('product_ids', [])))
                ->when($ids, fn ($w) => $w->orderByRaw('FIELD(id, '.implode(',', $ids).')')),
            default => $q->orderByDesc('published_at'),
        };

        return $q->limit($limit)->pluck('id')->all();
    }

    private function categoryIds(HomeBlock $b): array
    {
        $ids = array_map('intval', (array) $b->setting('category_ids', []));
        $all = $this->tree->all()->where('is_active', true);
        $rows = $ids ? collect($ids)->filter(fn ($id) => isset($all[$id])) : $all->where('parent_id', null)->sortBy([['position', 'asc'], ['name', 'asc']])->pluck('id');

        return $rows->take((int) $b->setting('limit', 12))->values()->all();
    }

    private function publisherIds(HomeBlock $b): array
    {
        $limit = (int) $b->setting('limit', 18);
        $featured = Publisher::active()->where('is_featured', true)->orderBy('position')->orderBy('name')->limit($limit)->pluck('id');

        return ($featured->isNotEmpty() ? $featured : Publisher::active()
            ->withCount(['products' => fn ($q) => $q->visible()])->having('products_count', '>', 0)
            ->orderByDesc('products_count')->limit($limit)->pluck('id'))->all();
    }

    private function hasContent(HomeBlock $b, mixed $data): bool
    {
        return match ($b->type) {
            'products', 'categories', 'publishers' => $data instanceof Collection && $data->isNotEmpty(),
            'slider' => collect($b->setting('slides', []))->filter(fn ($s) => ! empty($s['image']))->isNotEmpty(),
            'faq', 'features' => ! empty($b->setting('items')),
            default => true,
        };
    }
}
