<?php

namespace App\Domain\Catalog;

use App\Models\Category;
use App\Models\Product;
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

    /** @var list<int> bu nesnenin açtığı kategoriler (toplu yükleme, yeni açılanları yöneticiye göstermek için okur) */
    public array $created = [];

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
            $this->created[] = $category->id;

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
     *
     * $fuzzy (toplu yükleme): OCR'nin bozduğu adlar ("Port ir Bilim", "Üyunculuk") mevcut kategori adlarına ve bilinen
     * tür adlarına yakınsa onlara düzeltilir; baştaki/sondaki işaretler atılır, 3 harften kısa artıklar ("* Ah") yok sayılır.
     */
    public function ensurePath(array $names, bool $fuzzy = false): ?Category
    {
        $parent = null;
        foreach ($names as $name) {
            $name = trim(preg_replace('/\s+/u', ' ', (string) $name));
            if ($fuzzy) {
                $name = trim(preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $name));
                if (mb_strlen(preg_replace('/[^\p{L}]/u', '', $name)) < 3) {
                    continue;
                }
            }
            if ($name === '' || Text::key($name) === '') {
                continue;
            }
            $existing = Category::where('parent_id', $parent?->id)->where('normalized_name', Text::key($name))->first();
            if (! $existing && $fuzzy && ($fixed = $this->closestName($name, $parent)) !== null) {
                $name = $fixed;
                $existing = Category::where('parent_id', $parent?->id)->where('normalized_name', Text::key($name))->first();
            }
            // Yolun ilk adı en üstte yoksa ama ağaçta tek bir yerde varsa ("Dünya Tarihi" → Tarih › Dünya Tarihi) aynısı
            // yeniden açılmaz, o kategoriden devam edilir.
            if (! $existing && $parent === null) {
                $same = Category::where('normalized_name', Text::key($name))->limit(2)->get();
                $existing = $same->count() === 1 ? $same->first() : null;
            }
            $parent = $existing ?? $this->create(['name' => self::displayName($name)], $parent);
        }

        return $parent;
    }

    /** Bozuk okunmuş ada en yakın doğru ad: önce aynı seviyedeki kategoriler, sonra tüm kategori adları ve bilinen türler. */
    public function closestName(string $name, ?Category $parent = null): ?string
    {
        $siblings = Category::where('parent_id', $parent?->id)->pluck('name')->all();
        $all = Category::query()->distinct()->pluck('name')->all();
        foreach ([$siblings, [...$all, ...self::KNOWN_NAMES]] as $pool) {
            $best = null;
            $bestScore = 0.0;
            foreach (array_unique($pool) as $candidate) {
                $score = self::nameScore($name, $candidate);
                if ($score > $bestScore) {
                    [$best, $bestScore] = [$candidate, $score];
                }
            }
            if ($best !== null) {
                return $best;
            }
        }

        return null;
    }

    /**
     * İki adın aynı kategori olma puanı (0 = değil). OCR hataları için: uzun adlarda %80 benzerlik yeter; kısa adlarda
     * (Tarih/Tarım gibi gerçekten farklı olabilecekler) %86. Ayrıca ilk harfi ve son 4 harfi aynı, en fazla 4 harf farklı
     * adlar da eşleşir ("Port ir Bilim" → "Popüler Bilim").
     */
    public static function nameScore(string $a, string $b): float
    {
        $ka = str_replace(' ', '', Text::key($a));
        $kb = str_replace(' ', '', Text::key($b));
        if ($ka === '' || $kb === '' || $ka === $kb) {
            return $ka !== '' && $ka === $kb ? 1.0 : 0.0;
        }
        // Ortak kelimeleri olan, kelime sayısı aynı adlarda ayırt edici kısım ayrıca benzemeli: "Rusya Tarihi" ile
        // "Dünya Tarihi" harfçe yakın ama farklı kategorilerdir. (Kelime sayısı farklıysa OCR kelimeyi bölmüştür:
        // "Port ir Bilim" → "Popüler Bilim"; o durum aşağıdaki kurallarla değerlendirilir.)
        $wa = explode(' ', Text::key($a));
        $wb = explode(' ', Text::key($b));
        $restA = implode('', array_diff($wa, $wb));
        $restB = implode('', array_diff($wb, $wa));
        if (count($wa) === count($wb) && count($wa) > 1 && $restA !== '' && $restB !== '' && count(array_intersect($wa, $wb)) > 0
            && 1 - levenshtein($restA, $restB) / max(strlen($restA), strlen($restB)) < 0.75) {
            return 0.0;
        }
        $max = max(strlen($ka), strlen($kb));
        $lev = levenshtein($ka, $kb);
        $ratio = 1 - $lev / $max;
        $min = min(strlen($ka), strlen($kb));
        if ($ratio >= ($min < 8 ? 0.86 : 0.8)) {
            return $ratio;
        }
        if ($min >= 8 && $lev <= 4 && abs(strlen($ka) - strlen($kb)) <= 2 && $ka[0] === $kb[0] && substr($ka, -4) === substr($kb, -4)) {
            return $ratio;
        }

        return 0.0;
    }

    /**
     * Kategoriyi başka bir kategoriyle birleştirir: ürünleri taşınır, aynı adlı alt kategoriler de birleşir, diğer alt
     * kategoriler hedefin altına taşınır; kaynak silinir.
     */
    public function merge(Category $from, Category $into): Category
    {
        if ($from->is($into) || str_starts_with($into->path, $from->path)) {
            throw new DomainException('Kategori kendisiyle veya kendi alt kategorisiyle birleştirilemez.');
        }

        return DB::transaction(function () use ($from, $into) {
            foreach ($from->children()->get() as $child) {
                $same = Category::where('parent_id', $into->id)->where('normalized_name', $child->normalized_name)->first();
                $same ? $this->merge($child, $same) : $this->update($child, [], $into->fresh());
            }
            Product::where('category_id', $from->id)->update(['category_id' => $into->id]);
            $from->delete();
            $this->flush();

            return $into->fresh();
        });
    }

    /** Toplu yüklemede yazım düzeltmesi için bilinen tür adları (kitapların "Türü" satırında sık geçenler). */
    public const KNOWN_NAMES = [
        'Popüler Bilim', 'Bilim', 'Fizik', 'Kimya', 'Biyoloji', 'Matematik', 'Astronomi', 'Nörobilim', 'Tıp', 'Sağlık',
        'Tarih', 'Dünya Tarihi', 'Avrupa Tarihi', 'Türkiye Tarihi', 'Osmanlı Tarihi', 'Felsefe', 'Sosyoloji', 'Psikoloji',
        'Antropoloji', 'Arkeoloji', 'Dilbilim', 'Dil Felsefesi', 'Edebiyat', 'Roman', 'Öykü', 'Şiir', 'Deneme', 'Biyografi',
        'Anı', 'İnceleme', 'Araştırma', 'Kültürel Çalışmalar', 'Siyaset', 'Siyaset Bilimi', 'Uluslararası İlişkiler',
        'Ekonomi', 'İktisat', 'İşletme', 'Hukuk', 'Eğitim', 'Din', 'Mitoloji', 'Sanat', 'Sanat Tarihi', 'Sinema', 'Tiyatro',
        'Oyunculuk', 'Sahne Sanatları', 'Müzik', 'Mimarlık', 'Coğrafya', 'Kişisel Gelişim', 'Çocuk', 'Gençlik',
        'Soru Bankası', 'Konu Anlatımı', 'Deneme Sınavı', 'Yaprak Test', 'Fasikül', 'Defter',
    ];

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
        return collect(Cache::memo()->rememberForever(self::CACHE_KEY, fn () => Category::query()
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

    /**
     * Kategori başına yayındaki ürün sayısı, alt kategoriler dahil: [kategori id => sayı]. Vitrinde yalnız sayısı 0'dan büyük
     * kategoriler görünür (toplu yüklemeyle, elle ya da boş açılmış olsun; 1 ürün bile varsa görünür). Önbellek ürünlerin
     * sayısı ve son değişme anına bağlıdır: ürün eklenince, silinince, yayına alınınca/taşınınca kendiliğinden yenilenir.
     *
     * @return array<int, int>
     */
    public function visibleCounts(): array
    {
        // Aynı saniyede yapılan değişiklikler de yakalansın diye yayındakilerin kategori toplamı da imzaya girer
        $sig = Product::query()->selectRaw("count(*) as c, sum(status = 'active') as a, sum(case when status = 'active' then category_id else 0 end) as k, max(updated_at) as m")->first();
        $direct = Cache::memo()->remember('catalog.visible_counts.'.md5($sig?->c.'|'.$sig?->a.'|'.$sig?->k.'|'.$sig?->m), 3600,
            fn () => Product::query()->visible()->whereNotNull('category_id')->groupBy('category_id')
                ->selectRaw('category_id, count(*) as c')->pluck('c', 'category_id')->map(fn ($c) => (int) $c)->all());

        $all = $this->all();
        $counts = [];
        foreach ($direct as $id => $n) {
            if (! isset($all[$id])) {
                continue;
            }
            foreach (array_filter(explode('/', (string) $all[$id]['path'])) as $ancestor) {
                $counts[(int) $ancestor] = ($counts[(int) $ancestor] ?? 0) + $n;
            }
        }

        return $counts;
    }

    /**
     * Vitrin menüsü: etkin, menüde gösterilen ve içinde (alt kategorileri dahil) yayında ürün olan kategoriler;
     * her düğümde 'count' (yayındaki ürün sayısı) ve 'key' (arama için sade ad).
     */
    public function storefront(): array
    {
        $counts = $this->visibleCounts();
        $prune = function (array $nodes) use (&$prune, $counts): array {
            $out = [];
            foreach ($nodes as $node) {
                if (($counts[$node['id']] ?? 0) < 1) {
                    continue;
                }
                $out[] = ['count' => $counts[$node['id']], 'key' => Text::key($node['name']), 'children' => $prune($node['children'])] + $node;
            }

            return $out;
        };

        return $prune($this->nested(true, true));
    }

    /** Vitrinde görünen (etkin, ataları etkin ve içinde yayında ürün olan) kategori kimlikleri. @return list<int> */
    public function visibleIds(): array
    {
        $counts = $this->visibleCounts();
        $all = $this->all();

        return $all->filter(function ($c) use ($counts, $all) {
            if (($counts[$c['id']] ?? 0) < 1) {
                return false;
            }
            foreach (array_filter(explode('/', (string) $c['path'])) as $id) {
                if (! ($all[(int) $id]['is_active'] ?? false)) {
                    return false;
                }
            }

            return true;
        })->keys()->map(fn ($id) => (int) $id)->values()->all();
    }

    public function flush(): void
    {
        Cache::memo()->forget(self::CACHE_KEY);
    }
}
