<?php

namespace App\Domain\Catalog;

use App\Domain\Import\TitleCategory;
use App\Models\Category;
use App\Models\Product;
use App\Support\Text;
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
                $inPlan = $p->category_id && in_array($p->category_id, $planIds, true);
                $parsed = TitleCategory::parse($p->title);
                $mapped = $this->mapper->map($inPlan ? [] : $path, $p->title);
                $target = $inPlan && ! $mapped['matched'] ? Category::find($p->category_id) : $mapped['category'];

                if (! $target) {
                    if ($path) {
                        $report['unmatched'][] = ['product' => $p->title, 'from' => implode(' › ', $path)];
                    }

                    return;
                }
                $topics = collect([...($p->topics ?? []), ...$mapped['topics']])->unique(fn ($t) => Text::key($t))->values()->all();
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
