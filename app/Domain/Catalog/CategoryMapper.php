<?php

namespace App\Domain\Catalog;

use App\Domain\Import\TitleCategory;
use App\Models\Category;
use App\Models\CategoryAlias;
use App\Support\EmptyValue;
use App\Support\Text;
use Illuminate\Support\Collection;

/**
 * Toplu yüklemede kategori seçimi: görseldeki "Türü" yolu ("Popüler Bilim / Fizik / Parçacık Fiziği") ağaca
 * eklenmez; yöneticinin kısa listesindeki (en fazla iki kat) en uygun kategori seçilir. Yoldaki diğer ayrıntılı adlar
 * konu etiketi olur. Sırayla:
 *  1. Kitap adında sınav/sınıf düzeyi varsa ("345 TYT Matematik Soru Bankası") → Sınav Hazırlık › TYT
 *  2. Yoldaki adlar baştan sona: kayıtlı eşleme (CategoryAlias) → aynı ad → OCR harf hatası (bulanık) → ad içinde geçen
 *     kategori ("Psikoloji Popüler Bilim" → Psikoloji)
 *  3. Hiçbiri tutmazsa yeni kategori açılmaz; yüklemenin varsayılan kategorisi (varsa) kullanılır ve kitap kontrole düşer.
 */
class CategoryMapper
{
    /** @var ?Collection<int, Category> */
    private ?Collection $pool = null;

    public function __construct(private CategoryTree $tree) {}

    /** Kategoriler değişince (birleştirme, yeni kategori) önbelleği sıfırlar. */
    public function flush(): void
    {
        $this->pool = null;
    }

    /**
     * @param  list<string>  $path  "Türü" yolunun parçaları
     * @return array{category: ?Category, topics: list<string>, matched: bool, created: list<int>}
     */
    public function map(array $path, ?string $title = null, ?int $defaultId = null): array
    {
        $path = array_values(array_filter(array_map(fn ($p) => trim(preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', (string) $p)), $path),
            fn ($p) => mb_strlen(preg_replace('/[^\p{L}]/u', '', $p)) >= 3 && ! EmptyValue::is($p)));
        $created = [];
        $this->ensurePlan();

        $category = null;
        $usedIndex = null;
        $parsed = TitleCategory::parse($title);
        if ($exam = $parsed['exam'] ?? $parsed['grade']) {
            [$category, $created] = $this->examCategory($exam);
        }
        // Yöneticinin bu yolun tamamı için daha önce seçtiği kategori
        if (! $category && count($path) > 1 && ($alias = CategoryAlias::where('match_key', self::pathKey($path))->first())) {
            $category = $this->pool()->firstWhere('id', $alias->category_id);
            $usedIndex = $category ? -1 : null;
        }
        if (! $category) {
            foreach ($path as $i => $name) {
                if ($category = $this->find($name)) {
                    $usedIndex = $i;
                    break;
                }
            }
        }
        $matched = $category !== null;
        if (! $category && $defaultId) {
            $category = Category::find($defaultId);
            $matched = $category !== null;
        }
        // Hiçbiri tutmadı: kitap kategorisiz bırakılmaz, yolun ilk adıyla Okuma Kitapları altına tek katlı kategori açılır
        // ("Gastronomi"). Yükleme sayfasındaki "yeni kategoriler" bölümünde görünür; istenirse birleştirilir.
        if (! $category && ($i = $this->newCategoryIndex($path)) !== null) {
            $name = $this->tree->closestName($path[$i]) ?? CategoryTree::displayName($path[$i]);
            $this->tree->created = [];
            $category = $this->tree->ensurePath([CategoryPlan::READING, $name]);
            $created = $this->tree->created;
            $usedIndex = $i;
            $matched = $category !== null;
            $this->flush();
        }

        // Konu etiketleri: seçilen kategorinin adı ve grup adları dışındaki ayrıntılar
        $skip = array_filter([$category?->normalized_name, Text::key(CategoryPlan::READING), Text::key(CategoryPlan::EXAM)]);
        $topics = [];
        foreach ($path as $i => $name) {
            $key = Text::key($name);
            if ($i === $usedIndex || in_array($key, $skip, true) || isset($topics[$key])) {
                continue;
            }
            // OCR harf hatası bilinen bir ada yakınsa o yazılır ("Üyunculuk" → "Oyunculuk")
            $topic = $this->tree->closestName($name) ?? CategoryTree::displayName($name);
            if (! in_array(Text::key($topic), $skip, true)) {
                $topics[Text::key($topic)] ??= $topic;
            }
        }

        return ['category' => $category, 'topics' => $this->cleanTopics(array_values($topics), $category), 'matched' => $matched, 'created' => $created];
    }

    /**
     * Konu etiketlerinden kategorinin kendisini anlatanları atar: kategori/grup adı ve ona eşlenen adlar
     * (Popüler Bilim'deki kitapta "Bilim", Sanat ve Tiyatro'daki kitapta "Tiyatro"). Yinelenenler de atılır.
     *
     * @param  list<string>  $topics
     * @return list<string>
     */
    public function cleanTopics(array $topics, ?Category $category): array
    {
        $groups = [Text::key(CategoryPlan::READING), Text::key(CategoryPlan::EXAM)];
        $out = [];
        foreach ($topics as $t) {
            $key = Text::key((string) $t);
            if ($key === '' || isset($out[$key]) || in_array($key, $groups, true)) {
                continue;
            }
            // Kelimeleri kategori adında geçen ("Bilim" ⊂ "Popüler Bilim") ya da kategori adını kapsayan ("Psikoloji
            // Popüler Bilim" ⊃ "Psikoloji") etiket kategoriyi tekrarlar; "Fizik", "Avrupa Tarihi" gibi ayrıntılar kalır.
            if ($category) {
                $tw = explode(' ', $key);
                $cw = array_diff(explode(' ', $category->normalized_name), ['ve']);
                if (! array_diff($tw, [...$cw, 've']) || ! array_diff($cw, $tw)) {
                    continue;
                }
            }
            $out[$key] = $t;
        }

        return array_values($out);
    }

    /** Başlangıç düzeni silinmişse (ör. tüm kategoriler silindi) yeniden kurulur; yükleme hiçbir zaman eşleyecek liste olmadan kalmaz. */
    private function ensurePlan(): void
    {
        if ($this->restrict !== null) {
            return;
        }
        $groups = Category::whereNull('parent_id')
            ->whereIn('normalized_name', [Text::key(CategoryPlan::READING), Text::key(CategoryPlan::EXAM)])->count();
        if ($groups < 2) {
            CategoryPlan::install($this->tree);
            $this->flush();
        }
    }

    /** Yeni kategori adı olabilecek ilk parça: harfli, en fazla 4 kelime, 40 karakter. @param list<string> $path */
    private function newCategoryIndex(array $path): ?int
    {
        foreach ($path as $i => $name) {
            $words = preg_split('/\s+/u', trim($name));
            if (count($words) <= 4 && mb_strlen($name) <= 40 && preg_match('/^[\p{L}\s\'’.-]+$/u', $name)) {
                return $i;
            }
        }

        return null;
    }

    /** Yolun tamamının eşleme anahtarı ("Popüler Bilim / Fizik" → "populer bilim fizik"). @param list<string> $path */
    public static function pathKey(array $path): string
    {
        return mb_substr(Text::key(implode(' ', $path)), 0, 190);
    }

    /**
     * Kontrol ekranında yöneticinin seçtiği kategoriyi hatırlar: yolun tamamı ve hiçbir kategoriye uymayan adlar
     * ("Spor Sosyolojisi") seçilen kategoriye bağlanır. Zaten bir kategoriye uyan ad ("Popüler Bilim") değiştirilmez.
     *
     * @param  list<string>  $path
     */
    public function remember(array $path, Category $category): void
    {
        $path = array_values(array_filter(array_map('trim', $path), fn ($p) => mb_strlen($p) >= 3 && ! EmptyValue::is($p)));
        if (! $path) {
            return;
        }
        if (count($path) > 1) {
            CategoryAlias::updateOrCreate(['match_key' => self::pathKey($path)], ['category_id' => $category->id]);
        }
        foreach ($path as $name) {
            $found = $this->find($name);
            // Yüklemenin kendiliğinden açtığı ve artık boş kalan kategori ("Kuantum Gastronomisi") yöneticinin seçimine bırakılır
            $autoEmpty = $found && $found->id !== $category->id && ! CategoryPlan::isPlanned($found)
                && ! $found->products()->exists() && ! Category::where('parent_id', $found->id)->exists();
            if (! $found || $autoEmpty) {
                CategoryAlias::remember($name, $category);
            }
            if ($autoEmpty) {
                $found->delete();
                $this->tree->flush();
            }
            $this->flush();
        }
    }

    /** Okunan ada uyan kategori (yalnız ilk iki kat). */
    public function find(string $name): ?Category
    {
        $key = Text::key($name);
        if ($key === '') {
            return null;
        }
        $pool = $this->pool();
        if (($alias = CategoryAlias::where('match_key', $key)->first()) && ($hit = $pool->firstWhere('id', $alias->category_id))) {
            return $hit;
        }
        // Aynı ad: alt kategori grubundan önce gelir ("Tarih" adlı hem grup hem alt varsa alt seçilir)
        $same = $pool->where('normalized_name', $key)->sortByDesc('depth')->first();
        if ($same) {
            return $same;
        }
        // OCR harf hatası ("Port ir Bilim" → Popüler Bilim, "Psikolji" → Psikoloji)
        $best = null;
        $bestScore = 0.0;
        foreach ($pool as $cat) {
            $score = CategoryTree::nameScore($name, $cat->name);
            if ($score > $bestScore) {
                [$best, $bestScore] = [$cat, $score];
            }
        }
        foreach (CategoryAlias::query()->get() as $alias) {
            $score = CategoryTree::nameScore($name, $alias->match_key);
            if ($score > $bestScore && ($cat = $pool->firstWhere('id', $alias->category_id))) {
                [$best, $bestScore] = [$cat, $score];
            }
        }
        if ($best) {
            return $best;
        }
        // Ad içinde geçen kategori ("Psikoloji Popüler Bilim" → ilk geçen: Psikoloji); gruplar hariç
        $padded = ' '.$key.' ';
        $hits = $pool->filter(fn ($c) => $c->depth > 0 && str_contains($padded, ' '.$c->normalized_name.' '))
            ->sortBy(fn ($c) => strpos($padded, ' '.$c->normalized_name.' '));

        return $hits->first();
    }

    /** @return array{0: ?Category, 1: list<int>} Sınav Hazırlık › TYT (sınav adı listede yoksa grubun altına açılır). */
    private function examCategory(string $exam): array
    {
        if ($found = $this->find($exam)) {
            return [$found, []];
        }
        $group = CategoryPlan::examGroup();
        if (! $group) {
            return [null, []];
        }
        $this->tree->created = [];
        $cat = $this->tree->ensurePath([$group->name, $exam]);
        $this->flush();

        return [$cat, $this->tree->created];
    }

    /** Yalnız bu kategoriler arasından seç (eski ağacı sadeleştirirken: yalnız kısa liste). @param ?list<int> $ids */
    public function restrictTo(?array $ids): void
    {
        $this->restrict = $ids;
        $this->flush();
    }

    /** @var ?list<int> */
    private ?array $restrict = null;

    /** @return Collection<int, Category> en fazla iki kat */
    private function pool(): Collection
    {
        return $this->pool ??= Category::where('depth', '<=', 1)
            ->when($this->restrict !== null, fn ($q) => $q->whereIn('id', $this->restrict))
            ->get(['id', 'name', 'normalized_name', 'parent_id', 'depth', 'path']);
    }
}
