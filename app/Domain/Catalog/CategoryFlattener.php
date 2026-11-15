<?php

namespace App\Domain\Catalog;

use App\Models\Category;
use App\Models\ImportItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Eski derin kategori ağacını kısa düzene (CategoryPlan, en fazla iki kat) taşır:
 *  - her ürün, bulunduğu kategorinin yolu ("Popüler Bilim › Fizik › Parçacık Fiziği") ve kitap adıyla kısa listeye eşlenir
 *  - yoldaki ayrıntılı adlar konu etiketi olur; kitap türü ve ders kitap adından doldurulur
 *  - ürünü kalmayan eski kategoriler silinir (eşlenemeyen ürünün kategorisi yerinde kalır)
 */
class CategoryFlattener
{
    public function __construct(private CategoryTree $tree, private CategoryMapper $mapper) {}

    /**
     * @return array{moved: list<array{product: string, from: ?string, to: ?string, topics: list<string>}>, unmatched: list<array{product: string, from: string}>, deleted: list<string>}
     */
    public function run(bool $dryRun = false): array
    {
        $report = ['moved' => [], 'unmatched' => [], 'deleted' => []];

        $work = function () use (&$report) {
            $planIds = CategoryPlan::install($this->tree);
            $this->mapper->restrictTo($planIds);
            $categories = Category::all()->keyBy('id');
            $trail = function (?int $id) use ($categories): array {
                $names = [];
                while ($id && ($c = $categories[$id] ?? null)) {
                    array_unshift($names, $c->name);
                    $id = $c->parent_id;
                }

                return $names;
            };

            Product::query()->orderBy('id')->each(function (Product $p) use (&$report, $trail, $planIds) {
                $path = $trail($p->category_id);
                $item = null;
                if (! $p->category_id) {
                    // Kategorisiz kalmış ürün: toplu yüklemede okunan "Türü" yolu kullanılır
                    $item = ImportItem::where('product_id', $p->id)->whereNotNull('parsed')->latest('id')->first();
                    $path = array_values(array_filter((array) ($item?->parsed['category'] ?? [])));
                }
                $inPlan = $p->category_id && in_array($p->category_id, $planIds, true);
                $mapped = $this->mapper->map($inPlan ? [] : $path, $p->title, null, [
                    'subtitle' => $p->subtitle, 'publisher_id' => $p->publisher_id, 'author_id' => $p->author_id,
                    'extras' => array_column($p->extra_specs ?? [], 'label'),
                ]);
                // Zaten kısa listedeki ürün yalnız kesin bir kuralla (adda sınav adı) taşınır; tahminle yerinden oynatılmaz
                $target = $inPlan && $mapped['confidence'] !== 'high' ? Category::find($p->category_id) : $mapped['category'];
                $parsed = ['type' => $mapped['book_type'], 'subject' => $mapped['subject']];

                if (! $target) {
                    if ($path) {
                        $report['unmatched'][] = ['product' => $p->title, 'from' => implode(' › ', $path)];
                    }

                    return;
                }
                $topics = $this->mapper->cleanTopics([...($p->topics ?? []), ...$mapped['topics']], $target);
                $changes = array_filter([
                    'category_id' => $target->id !== $p->category_id ? $target->id : null,
                    'book_type' => $p->book_type === null ? $parsed['type'] : null,
                    'subject' => $p->subject === null ? $parsed['subject'] : null,
                    'topics' => $topics !== ($p->topics ?? []) ? ($topics ?: null) : null,
                ], fn ($v) => $v !== null);
                if (! $changes) {
                    return;
                }
                $report['moved'][] = ['product' => $p->title, 'from' => $path ? implode(' › ', $path) : null, 'to' => $target->fullName(), 'topics' => $topics];
                $p->fill($changes)->save();
                if ($item && isset($changes['category_id'])) {
                    // Yalnız kategori yüzünden kontrole düşmüş kalem artık tamamdır
                    $issues = array_values(array_filter($item->issues ?? [], fn ($i) => ! str_contains($i, 'kategorilerden birine eşlenemedi')
                        && ! str_contains($i, 'ürün kategorisiz kaldı')));
                    $item->update(['issues' => $issues ?: null] + ($issues || $item->status !== 'review' ? [] : ['status' => 'imported']));
                    $item->batch?->refreshStatus();
                }
            });

            // Ürünü ve alt kategorisi kalmayan eski (kısa listede olmayan) kategoriler: en derinden başlayarak silinir.
            $used = Product::whereNotNull('category_id')->distinct()->pluck('category_id')->all();
            foreach (Category::whereNotIn('id', $planIds)->orderByDesc('depth')->get() as $c) {
                if (! in_array($c->id, $used, true) && ! Category::where('parent_id', $c->id)->exists()) {
                    $report['deleted'][] = $c->fullName();
                    $c->delete();
                }
            }
            $this->tree->flush();
        };

        // Deneme: her şey bir işlemde yapılır ve geri alınır (rapor gerçek çalıştırmayla aynıdır)
        DB::beginTransaction();
        try {
            $work();
            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        } finally {
            $this->mapper->restrictTo(null);
            $this->tree->flush();
        }

        return $report;
    }
}
