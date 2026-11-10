<?php

namespace App\Domain\Catalog;

use App\Models\Category;
use App\Support\Text;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Kategori ağacı işlemleri: oluşturma, taşıma (alt ağacın yolu ve derinliği de güncellenir), yol üzerinden oluşturma
 * (içe aktarmada "Tarih / Avrupa Tarihi / Almanya Tarihi") ve önbellekli tam ağaç.
 */
class CategoryTree
{
    public const CACHE_KEY = 'catalog.category_tree';

    /** Büyük harfle kalacak kısaltmalar (ünlü içerdiği için kuralla yakalanamayanlar). */
    private const ACRONYMS = ['ALES', 'AYT', 'ÖABT', 'TUS', 'DUS', 'MSÜ', 'YÖKDİL', 'İELTS', 'IELTS', 'TOEFL', 'AÖF', 'AB', 'ABD', 'TC', 'TBMM', 'İSG', 'EKPSS', 'PMYO', 'POMEM', 'GRE', 'GMAT', 'İHL', 'TEOG', 'AUZEF', 'İÜ', 'ODTÜ', 'İTÜ', 'TDK', 'UK', 'USA', 'AİHM', 'AYM', 'HMGS', 'İYÖS', 'YÖS', 'ÖSYM', 'MEB', 'YDS', 'YDT', 'LGS', 'DGS', 'KPSS', 'TYT'];

    public function create(array $data, ?Category $parent = null): Category
    {
        return DB::transaction(function () use ($data, $parent) {
            $name = trim($data['name']);
            $category = Category::create($data + [
                'parent_id' => $parent?->id,
                'name' => $name,
                'normalized_name' => Text::key($name),
                'slug' => Category::uniqueSlug(($parent ? $parent->name.' ' : '').$name),
                'path' => '',
                'depth' => $parent ? $parent->depth + 1 : 0,
                'position' => (int) Category::where('parent_id', $parent?->id)->max('position') + 1,
            ]);
            $category->update(['path' => ($parent?->path ?? '').$category->id.'/']);
            $this->flush();

            return $category;
        });
    }

    public function update(Category $category, array $data, ?Category $newParent): Category
    {
        return DB::transaction(function () use ($category, $data, $newParent) {
            if ($newParent && str_starts_with($newParent->path, $category->path)) {
                throw new DomainException('Kategori kendi altına taşınamaz.');
            }
            if (isset($data['name'])) {
                $data['normalized_name'] = Text::key($data['name']);
            }
            $category->fill($data);
            if ($category->parent_id !== $newParent?->id) {
                $oldPath = $category->path;
                $newPath = ($newParent?->path ?? '').$category->id.'/';
                $depthDiff = ($newParent ? $newParent->depth + 1 : 0) - $category->depth;
                $category->parent_id = $newParent?->id;
                $category->position = (int) Category::where('parent_id', $newParent?->id)->max('position') + 1;
                $category->save();
                // Alt ağaçtaki her kaydın yolunun başı ve derinliği güncellenir.
                Category::where('path', 'like', $oldPath.'%')->update([
                    'path' => DB::raw('CONCAT('.DB::getPdo()->quote($newPath).', SUBSTRING(path, '.(strlen($oldPath) + 1).'))'),
                    'depth' => DB::raw('depth + ('.$depthDiff.')'),
                ]);
            } else {
                $category->save();
            }
            $this->flush();

            return $category->fresh();
        });
    }

    /** @throws DomainException alt kategori veya ürün varsa */
    public function delete(Category $category): void
    {
        if ($category->children()->exists()) {
            throw new DomainException('Önce alt kategorileri silin veya taşıyın.');
        }
        if ($category->products()->exists()) {
            throw new DomainException('Bu kategoride ürün var. Önce ürünleri başka kategoriye taşıyın.');
        }
        $category->delete();
        $this->flush();
    }

    /**
     * "Tarih / Avrupa Tarihi / Almanya Tarihi" yolundaki kategorileri bulur, eksikleri oluşturur; en alttakini döndürür.
     * Ad karşılaştırması büyük/küçük harf ve Türkçe karakterden bağımsızdır.
     */
    public function ensurePath(array $names): ?Category
    {
        $parent = null;
        foreach ($names as $name) {
            $name = trim(preg_replace('/\s+/u', ' ', (string) $name));
            if ($name === '' || Text::key($name) === '') {
                continue;
            }
            $existing = Category::where('parent_id', $parent?->id)->where('normalized_name', Text::key($name))->first();
            $parent = $existing ?? $this->create(['name' => self::displayName($name)], $parent);
        }

        return $parent;
    }

    /** Tamamı büyük harfli adlar başlık biçimine çevrilir ("SORU BANKASI" → "Soru Bankası"); kısaltmalar korunur. */
    public static function displayName(string $name): string
    {
        if (Text::upper($name) !== $name) {
            return $name; // zaten düzgün yazılmış
        }

        // Kelime bazında: bilinen sınav/kurum kısaltmaları, ünlüsüz kelimeler (KPSS, YKS), Romen rakamları ve rakam
        // içeren kelimeler (2026, 12.) korunur; diğerleri başlık biçimine çevrilir ("KISA" → "Kısa").
        return collect(explode(' ', $name))->map(function ($w, $i) {
            if ($i > 0 && in_array(Text::lower($w), ['ve', 'ile', 'veya', 'için', 'de', 'da'], true)) {
                return Text::lower($w);
            }
            $letters = preg_replace('/[^\p{L}]/u', '', $w);
            $isAcronym = $letters === '' || preg_match('/\d/', $w)
                || in_array($letters, self::ACRONYMS, true)
                || (mb_strlen($letters) <= 5 && ! preg_match('/[AEIİOÖUÜ]/u', $letters))
                || preg_match('/^[IVXLC]+$/u', $letters) && mb_strlen($letters) <= 4 && $letters !== 'CIVIL';

            return $isAcronym ? $w : Text::title($w);
        })->implode(' ');
    }

    /** Tüm ağaç (önbellekli): [id => ['id','name','slug','parent_id','depth','path','is_active','show_in_menu','position']]. */
    public function all(): Collection
    {
        return collect(Cache::rememberForever(self::CACHE_KEY, fn () => Category::query()
            ->orderBy('depth')->orderBy('position')->orderBy('name')
            ->get(['id', 'name', 'slug', 'parent_id', 'depth', 'path', 'is_active', 'show_in_menu', 'position', 'icon'])
            ->keyBy('id')->map(fn ($c) => $c->only(['id', 'name', 'slug', 'parent_id', 'depth', 'path', 'is_active', 'show_in_menu', 'position', 'icon']))
            ->all()));
    }

    /** Ağacı derinlik öncelikli düz listeye çevirir (seçim kutuları ve yönetim listesi için). */
    public function flat(bool $onlyActive = false): Collection
    {
        $all = $this->all();
        $children = $all->groupBy(fn ($c) => $c['parent_id'] ?? 0);
        $out = collect();
        $walk = function (int $parentId) use (&$walk, $children, $out, $onlyActive) {
            foreach (($children[$parentId] ?? collect())->sortBy([['position', 'asc'], ['name', 'asc']]) as $c) {
                if ($onlyActive && ! $c['is_active']) {
                    continue;
                }
                $out->push($c);
                $walk($c['id']);
            }
        };
        $walk(0);

        return $out;
    }

    /** İç içe ağaç: her düğümde 'children'. */
    public function nested(bool $onlyActive = true, bool $menuOnly = false): array
    {
        $all = $this->all()->filter(fn ($c) => (! $onlyActive || $c['is_active']) && (! $menuOnly || $c['show_in_menu']));
        $children = $all->groupBy(fn ($c) => $c['parent_id'] ?? 0);
        $build = function (int $parentId) use (&$build, $children): array {
            return ($children[$parentId] ?? collect())->sortBy([['position', 'asc'], ['name', 'asc']])
                ->map(fn ($c) => $c + ['children' => $build($c['id'])])->values()->all();
        };

        return $build(0);
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
