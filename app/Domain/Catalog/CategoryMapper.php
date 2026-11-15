<?php

namespace App\Domain\Catalog;

use App\Domain\Import\TitleCategory;
use App\Models\Author;
use App\Models\Category;
use App\Models\CategoryAlias;
use App\Models\PlacementRule;
use App\Models\Publisher;
use App\Support\EmptyValue;
use App\Support\Text;
use Illuminate\Support\Collection;

/**
 * Toplu yüklemede kitabı kategoriye yerleştirir. Kategoriler en fazla iki kat (Okuma Kitapları › Tarih,
 * Sınav Hazırlık › TYT); görseldeki "Türü" yolunun ayrıntıları konu etiketi olur. Karar sırası:
 *
 *  1. Kesin kurallar (güven: yüksek)
 *     - adda ya da klasör adında sınav adı ("AYT Fizik" → AYT, "ALES" → ALES, "YDS" → YDS ve YDT) veya sınıf düzeyi
 *       ("10. Sınıf" → Ara Sınıflar); listede olmayan sınav (MSÜ) Sınav Hazırlık altına açılır
 *     - yöneticinin bu "Türü" yolu için daha önce seçtiği kategori
 *  2. Sınav kitabı mı okuma kitabı mı? İpuçları puanlanır (BookSignals): tür kelimeleri (soru bankası, konu özetli,
 *     föy…), okul dersleri, roman/öykü/şiir, çevirmen, görseldeki "Türü", yayınevinin ve yazarın diğer kitapları,
 *     sitedeki benzer kitaplar (CategoryLearner).
 *  3. Grubun içinde kategori: "Türü" eşleşmesi (yüksek) → benzer kitaplar + yayınevi/yazar dağılımı (orta/düşük) →
 *     sınav kitabında türüne göre (Deneme Kitapları, Konu Özetli Defterler) → varsayılan kategori → okuma kitabında
 *     "Türü" adıyla yeni tek katlı kategori → grubun kendisi.
 * Kitap hiçbir zaman kategorisiz kalmaz; orta/düşük güvenli yerleştirmeler yükleme sayfasında gerekçesiyle listelenir.
 */
class CategoryMapper
{
    /** @var ?Collection<int, Category> */
    private ?Collection $pool = null;

    /** @var list<int> bu yerleştirmede açılan kategoriler */
    private array $created = [];

    /** @var ?list<int> */
    private ?array $restrict = null;

    /** @var ?Collection<int, PlacementRule> */
    private ?Collection $rules = null;

    public function __construct(private CategoryTree $tree, private CategoryLearner $learner) {}

    /** Kategoriler değişince (birleştirme, yeni kategori) önbelleği sıfırlar. */
    public function flush(): void
    {
        $this->pool = null;
        $this->rules = null;
    }

    /**
     * @param  list<string>  $path  "Türü" yolunun parçaları
     * @param  array{folder_title?: ?string, subtitle?: ?string, publisher_id?: ?int, author_id?: ?int, extras?: list<string>}  $ctx
     * @return array{category: ?Category, topics: list<string>, matched: bool, created: list<int>, confidence: string, reasons: list<string>, alternative: ?int, book_type: ?string, subject: ?string}
     */
    public function map(array $path, ?string $title = null, ?int $defaultId = null, array $ctx = []): array
    {
        $path = array_values(array_filter(array_map(fn ($p) => trim(preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', (string) $p)), $path),
            fn ($p) => mb_strlen(preg_replace('/[^\p{L}]/u', '', $p)) >= 3 && ! EmptyValue::is($p)));
        $this->ensurePlan();
        $this->created = [];
        $default = $defaultId ? Category::find($defaultId) : null;
        $parsed = TitleCategory::parse($title, $ctx['folder_title'] ?? null);
        $examBook = $parsed['exam'] !== null || $parsed['grade'] !== null;
        $pick = null; // [kategori, güven, gerekçeler, kullanılan yol indeksi, ikinci seçenek]

        // 0. Yöneticinin kaydettiği yerleştirme kuralı (toplu taşımada "benzer kitaplar da buraya gitsin"): her şeyden önce
        $book = $this->ruleBook($path, $title, $parsed, $ctx);
        if ($rule = $this->ruleFor($book, false)) {
            $pick = [$rule[1], 'high', ['Yerleştirme kuralı: '.$rule[0]->label], null, null];
            $examBook = $examBook || $this->inGroup($rule[1], CategoryPlan::examGroup());
        }

        // 1. Kesin: adda/klasör adında sınav adı ya da sınıf düzeyi
        if ($examBook && ! $pick) {
            $pick = [$this->examCategory($parsed['exam'] ?? $parsed['grade']), 'high',
                [$parsed['exam'] ? "Adda “{$parsed['exam']}” geçiyor" : "Adda sınıf düzeyi var ({$parsed['grade']})"], null, null];
        }
        // 1b. Kesin: yöneticinin bu "Türü" yolu için daha önce seçtiği kategori
        if (! $pick && count($path) > 1 && ($alias = CategoryAlias::where('match_key', self::pathKey($path))->first())
            && ($hit = $this->pool()->firstWhere('id', $alias->category_id))) {
            $pick = [$hit, 'high', ['Bu “Türü” için daha önce seçtiğiniz kategori'], -1, null];
            $examBook = $this->inGroup($hit, CategoryPlan::examGroup());
        }

        if (! $pick) {
            // 2. Sınav kitabı mı okuma kitabı mı; 3. grubun içinde hangi kategori
            [$pathHit, $pathIndex] = $this->pathHit($path);
            [$examBook, $why] = $this->isExamBook($title, $ctx + ['has_path' => $path !== []], $pathHit);
            // Okuma kitabı değilse: "Türü" Diğer Kitaplar'daki bir kategoriye uyuyorsa (ikinci Sözlük) o grup
            $other = CategoryPlan::otherGroup();
            $group = $examBook ? CategoryPlan::examGroup() : ($pathHit && $this->inGroup($pathHit, $other) ? $other : CategoryPlan::readingGroup());
            $inGroupHit = $pathHit && $this->inGroup($pathHit, $group) ? [$pathHit, $pathIndex] : $this->pathHitIn($path, $group?->id);

            // Sıra: görseldeki "Türü" → kitap türü (defter/deneme) → "Türü" için yeni kategori → benzer kitaplar ve
            // yayınevi/yazar geçmişi → varsayılan → grup. Açıkça yazılmış "Türü" geçmişten tahminden önce gelir
            // (bir yayınevinin Sözlük'ü, Atlas'ı, Gezi rehberi aynı kategoriye toplanmaz).
            if ($inGroupHit) {
                $pick = [$inGroupHit[0], 'high', ['Görseldeki “Türü”: '.$path[$inGroupHit[1]], ...$why], $inGroupHit[1], null];
            } elseif ($examBook && $group && ($typeCat = $this->typeCategory($group, $title, $path))) {
                $pick = [$typeCat, 'medium', ['Kitap türü: '.(TitleCategory::rawType($title) ?? implode(' ', $path)), ...$why], null, null];
            } elseif ($group && ($i = $this->newCategoryIndex($path, $examBook)) !== null) {
                // Tanınmayan "Türü": yüklemede varsayılan kategori seçildiyse oraya, yoksa bu adla yeni kategori
                // Okuma kitabı da değilse (Sözlük, Tıp, Kırtasiye…) Diğer Kitaplar altına açılır; okuma türü adı
                // ("Polisiye") ya da adda/çevirmende okuma ipucu varsa Okuma Kitapları altına
                $name = $this->tree->closestName($path[$i]) ?? CategoryTree::displayName($path[$i]);
                $target = $examBook || $this->readingLike($path[$i], $title, $ctx) ? $group->name : CategoryPlan::OTHER;
                $pick = $default
                    ? [$default, 'low', ['“Türü” ('.$path[$i].') tanınmadı; yüklemede seçilen varsayılan kategori', ...$why], null, null]
                    : [$this->ensure([$target, $name]), 'medium', ['Görseldeki “Türü” için yeni kategori: '.$target.' › '.$name, ...$why], $i, null];
            } elseif ($group && ($best = $this->bestIn($group, $title, $ctx))) {
                $pick = [$best[0], $best[2] ? 'medium' : 'low', [...$best[1], ...$why], null, $best[3]];
            } elseif ($default) {
                $pick = [$default, 'low', ['Yüklemede seçilen varsayılan kategori', ...$why], null, null];
            } elseif ($group) {
                $pick = [$group, 'low', ['Yeterli ipucu yok; '.$group->name.' ana kategorisine konuldu', ...$why], null, null];
            }
        }
        // 0b. Kategori koşullu kurallar (ve aramada kategori adı geçenler) otomatik yerleştirmenin sonucuna bakar
        if (! $rule && ($pick[0] ?? null) && ($rule = $this->ruleFor(['text' => $book['text'].' '.(Category::find($pick[0]->id)?->fullName(' ') ?? $pick[0]->name), 'category_id' => $pick[0]->id] + $book, true))) {
            $auto = $pick[0];
            $pick = [$rule[1], 'high', ['Yerleştirme kuralı: '.$rule[0]->label, 'Otomatik yerleştirme '.$auto->name.' diyordu'], null, null];
            $examBook = $examBook || $this->inGroup($rule[1], CategoryPlan::examGroup());
            $this->dropUnused($rule[1]);
        }
        [$category, $confidence, $reasons, $usedIndex, $alternative] = $pick ?? [null, 'low', [], null, null];

        // Kitap türü ve ders yalnız sınav kitaplarında (okuma kitabında "Deneme" edebî türdür)
        $bookType = $examBook ? ($parsed['type'] ?? TitleCategory::rawType($title)) : null;
        $subject = $examBook ? ($parsed['subject'] ?? TitleCategory::subject((string) $title, true)) : null;

        // Konu etiketleri: seçilen kategorinin adı, grup adları, tür ve ders dışındaki ayrıntılar
        $skip = array_filter([$category?->normalized_name, ...CategoryPlan::groupKeys(),
            $bookType ? Text::key($bookType) : null, $subject ? Text::key($subject) : null]);
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

        return [
            'category' => $category, 'topics' => $this->cleanTopics(array_values($topics), $category), 'matched' => $category !== null,
            'created' => $this->created, 'confidence' => $confidence, 'reasons' => array_values(array_unique($reasons)),
            'alternative' => $alternative, 'book_type' => $bookType, 'subject' => $subject, 'rule' => $rule ? $rule[0]->id : null,
        ];
    }

    /**
     * Kuralların bakacağı kitap bilgisi: ürün listesindeki arama metninin (search_text) yüklemedeki karşılığı.
     *
     * @return array{text: string, isbn: ?string, subject: ?string, book_type: ?string, publisher_id: ?int}
     */
    private function ruleBook(array $path, ?string $title, array $parsed, array $ctx): array
    {
        $subject = $parsed['subject'] ?? TitleCategory::subject((string) $title, true);
        $type = $parsed['type'] ?? TitleCategory::rawType($title);
        $publisherId = isset($ctx['publisher_id']) ? (int) $ctx['publisher_id'] : null;
        $text = implode(' ', array_filter([$title, $ctx['subtitle'] ?? null, $ctx['folder_title'] ?? null, ...$path, $subject, $type,
            $publisherId ? Publisher::whereKey($publisherId)->value('name') : null,
            isset($ctx['author_id']) ? Author::whereKey($ctx['author_id'])->value('name') : null,
            $ctx['code'] ?? null, $ctx['isbn'] ?? null]));

        return ['text' => $text, 'isbn' => $ctx['isbn'] ?? null, 'subject' => $subject, 'book_type' => $type, 'publisher_id' => $publisherId];
    }

    /**
     * Uyan en belirgin kural (eşitlikte en yenisi) ve hedef kategorisi. $afterAuto=false: kategori koşulsuz kurallar;
     * true: hepsi (kitap bilgisinde otomatik yerleştirmenin kategorisi var).
     *
     * @return ?array{0: PlacementRule, 1: Category}
     */
    private function ruleFor(array $book, bool $afterAuto): ?array
    {
        $this->rules ??= PlacementRule::orderByDesc('id')->get();
        foreach ($this->rules->sortByDesc(fn ($r) => [$r->specificity(), $r->id]) as $rule) {
            if (! $afterAuto && $rule->needsCategory()) {
                continue;
            }
            if ($rule->matches($book) && ($hit = $this->pool()->firstWhere('id', $rule->category_id))) {
                return [$rule, $hit];
            }
        }

        return null;
    }

    /** Kural otomatik yerleştirmeyi geçersiz kıldıysa onun bu kitap için açtığı (boş) alt kategoriler silinir. */
    private function dropUnused(Category $keep): void
    {
        foreach (array_reverse($this->created) as $id) {
            $cat = Category::find($id);
            if ($cat && $cat->id !== $keep->id && $cat->depth > 0 && ! $cat->children()->exists() && ! $cat->products()->exists()) {
                $this->tree->delete($cat);
                $this->created = array_values(array_diff($this->created, [$id]));
            }
        }
        $this->flush();
    }

    /**
     * Sınav kitabı mı? Metin ipuçları + görseldeki "Türü" + çevirmen + yayınevi/yazar geçmişi + benzer kitaplar.
     *
     * @return array{0: bool, 1: list<string>} karar ve gerekçe
     */
    private function isExamBook(?string $title, array $ctx, ?Category $pathHit): array
    {
        $text = implode(' ', array_filter([$title, $ctx['subtitle'] ?? null, $ctx['folder_title'] ?? null]));
        [$exam, $examWhy] = BookSignals::exam($text);
        [$read, $readWhy] = BookSignals::reading($text);
        $examGroup = CategoryPlan::examGroup();
        $inExam = fn (int $catId) => ($c = $this->pool()->firstWhere('id', $catId)) !== null && $this->inGroup($c, $examGroup);
        $why = [];

        if ($pathHit) {
            if ($inExam($pathHit->id)) {
                $exam += 6;
            } else {
                $read += 6;
            }
        } elseif ($ctx['has_path'] ?? false) {
            $read += 2; // görselde "Türü" satırı olan künye tablosu okuma kitaplarına özgüdür
        }
        if (collect($ctx['extras'] ?? [])->contains(fn ($l) => str_contains(Text::key((string) $l), 'cevirmen'))) {
            $read += 3;
            $readWhy[] = 'çevirmenli';
        }
        // Yayınevi (en az 3 kitap) ve yazar (en az 1 kitap) geçmişi
        foreach ([['publisher', 3, 4, 'yayınevinin kitapları'], ['author', 1, 4, 'yazarın diğer kitapları']] as [$kind, $min, $weight, $label]) {
            [$share, $total] = $this->learner->{$kind}($ctx[$kind.'_id'] ?? null);
            if ($total >= $min) {
                $e = 0.0;
                foreach ($share as $c => $s) {
                    $e += $inExam((int) $c) ? $s : 0;
                }
                $exam += $weight * $e;
                $read += $weight * (1 - $e);
                $why[] = $label.': %'.round($e * 100).' sınav kitabı';
            }
        }
        // Sitedeki benzer kitaplar
        $probs = $this->learner->predict(CategoryLearner::tokens($text), $this->pool()->pluck('id')->all());
        if ($probs) {
            $e = 0.0;
            foreach ($probs as $c => $p) {
                $e += $inExam((int) $c) ? $p : 0;
            }
            $exam += 3 * $e;
            $read += 3 * (1 - $e);
        }
        $isExam = $exam > $read;

        return [$isExam, array_values(array_filter([
            $isExam && $examWhy ? 'Sınav kitabı ipuçları: '.implode(', ', $examWhy) : null,
            ! $isExam && $readWhy ? 'Okuma kitabı ipuçları: '.implode(', ', $readWhy) : null,
            ...$why,
        ]))];
    }

    /**
     * Grubun alt kategorileri arasında en olası olan: benzer kitaplar + yayınevi ve yazar dağılımı.
     *
     * @return ?array{0: Category, 1: list<string>, 2: bool, 3: ?int} kategori, gerekçe, yeterince emin mi, ikinci seçenek
     */
    private function bestIn(Category $group, ?string $title, array $ctx): ?array
    {
        $children = $this->pool()->where('parent_id', $group->id)->keyBy('id');
        if ($children->isEmpty()) {
            return null;
        }
        $ids = $children->keys()->all();
        $scores = array_fill_keys($ids, 0.0);
        $why = array_fill_keys($ids, []);
        $text = implode(' ', array_filter([$title, $ctx['subtitle'] ?? null]));
        foreach ($this->learner->predict(CategoryLearner::tokens($text), $ids) as $c => $p) {
            $scores[$c] += 3 * $p;
            if ($p >= 0.5) {
                $why[$c][] = 'benzer kitaplar bu kategoride';
            }
        }
        foreach ($this->learner->distinctive(CategoryLearner::tokens($text), $ids) as $c => $n) {
            $scores[$c] += 1.5 * min(2, $n);
            $why[$c][] = 'adındaki kelimeler bu kategorideki kitaplarda geçiyor';
        }
        foreach ([['publisher', 3, 3, 'yayınevinin kitaplarının çoğu burada'], ['author', 1, 4, 'yazarın diğer kitapları burada']] as [$kind, $min, $weight, $label]) {
            [$share, $total] = $this->learner->{$kind}($ctx[$kind.'_id'] ?? null);
            if ($total < $min) {
                continue;
            }
            foreach ($share as $c => $s) {
                if (isset($scores[$c])) {
                    $scores[$c] += $weight * $s;
                    if ($s >= 0.5) {
                        $why[$c][] = $label;
                    }
                }
            }
        }
        arsort($scores);
        $ranked = array_keys($scores);
        [$best, $second] = [$ranked[0], $ranked[1] ?? null];
        if ($scores[$best] < 1.5) {
            return null; // yeterli ipucu yok
        }
        $sure = $scores[$best] >= 3 && ($second === null || $scores[$best] - $scores[$second] >= 1.5);

        return [$children[$best], $why[$best] ?: ['benzer kitaplar ve yayınevi geçmişi'], $sure, $second !== null && $scores[$second] > 0 ? $second : null];
    }

    /** Sınav adı okunamayan sınav kitabı için türüne göre kategori: Deneme → Deneme Kitapları, Defter → Konu Özetli Defterler. */
    private function typeCategory(Category $group, ?string $title, array $path = []): ?Category
    {
        $type = TitleCategory::rawType($title);
        foreach ($type === null ? $path : [] as $p) { // görselde "Türü: Defter"
            $type ??= TitleCategory::rawType($p);
        }
        $name = CategoryPlan::TYPE_CATEGORIES[$type ?? ''] ?? null;

        return $name ? ($this->find($name, $group->id) ?? $this->ensure([$group->name, $name])) : null;
    }

    /** Listede olmayan "Türü" bir okuma türü mü: adı okuma türü kelimesi içeriyor ya da kitap adı/çevirmen okuma kitabını gösteriyor. */
    private function readingLike(string $type, ?string $title, array $ctx): bool
    {
        if (preg_match(CategoryPlan::READING_GENRES, Text::key($type))) {
            return true;
        }
        if (collect($ctx['extras'] ?? [])->contains(fn ($l) => str_contains(Text::key((string) $l), 'cevirmen'))) {
            return true;
        }

        return BookSignals::reading((string) $title)[0] >= 4;
    }

    private function inGroup(Category $category, ?Category $group): bool
    {
        return $group !== null && ($category->id === $group->id || $category->parent_id === $group->id);
    }

    /** "Türü" yolunda bir kategoriye uyan ilk ad. @return array{0: ?Category, 1: ?int} */
    private function pathHit(array $path): array
    {
        foreach ($path as $i => $name) {
            if ($hit = $this->find($name)) {
                return [$hit, $i];
            }
        }

        return [null, null];
    }

    /** @return ?array{0: Category, 1: int} */
    private function pathHitIn(array $path, ?int $groupId): ?array
    {
        foreach ($groupId ? $path : [] as $i => $name) {
            if ($hit = $this->find($name, $groupId)) {
                return [$hit, $i];
            }
        }

        return null;
    }

    /** @param list<string> $names */
    private function ensure(array $names): ?Category
    {
        $this->tree->created = [];
        $cat = $this->tree->ensurePath($names);
        $this->created = [...$this->created, ...$this->tree->created];
        $this->flush();

        return $cat;
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
        $groups = CategoryPlan::groupKeys();
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
        CategoryPlan::ensure($this->tree);
        $this->flush();
    }

    /** Yeni kategori adı olabilecek ilk parça: harfli, en fazla 4 kelime, 40 karakter. @param list<string> $path */
    private function newCategoryIndex(array $path, bool $exam = false): ?int
    {
        foreach ($path as $i => $name) {
            $words = preg_split('/\s+/u', trim($name));
            // Öbür gruptaki bir kategorinin adı (sınav kitabında "Edebiyat") ya da sınav dersi ("Paragraf") yeni kategori olmaz
            if (! self::isGeneric($name, $exam) && $this->find($name) === null && ! ($exam && TitleCategory::subject($name) !== null)
                && count($words) <= 4 && mb_strlen($name) <= 40 && preg_match('/^[\p{L}\s\'’.-]+$/u', $name)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Kategori adı olamayacak genel "Türü" adları: "Kitap", "Genel", "Diğer"; sınav kitaplarında ayrıca kitap türleri ve
     * sınav sözcükleri ("Test Kitabı", "Soru Bankası", "Sınav Hazırlık"): bunlar süzgeçtir, kategori açılmaz.
     */
    private static function isGeneric(string $name, bool $exam): bool
    {
        $key = Text::key($name);
        if (in_array($key, ['kitap', 'kitaplar', 'kitaplari', 'urun', 'urunler', 'genel', 'diger', 'tum kitaplar', 'kategori', 'yayin', 'yayinlar'], true)) {
            return true;
        }

        return $exam && (TitleCategory::rawType($name) !== null
            || preg_match('/\b(kitap|kitabi|kitaplari|sinav|sinavlari|sinavi|hazirlik|yardimci|kaynak|ders|soru|test|egitim)\b/', $key) === 1);
    }

    /** Yolun tamamının eşleme anahtarı ("Popüler Bilim / Fizik" → "populer bilim fizik"). @param list<string> $path */
    public static function pathKey(array $path): string
    {
        return mb_substr(Text::key(implode(' ', $path)), 0, 190);
    }

    /**
     * Yöneticinin seçtiği kategoriyi hatırlar: yolun tamamı ve hiçbir kategoriye uymayan adlar ("Spor Sosyolojisi")
     * seçilen kategoriye bağlanır. Zaten bir kategoriye uyan ad ("Popüler Bilim") değiştirilmez.
     *
     * @param  list<string>  $path
     */
    public function remember(array $path, Category $category): void
    {
        CategoryLearner::forget(); // seçim benzer kitap tahminlerine hemen yansısın
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

    /** Okunan ada uyan kategori (yalnız ilk iki kat; $parentId verilirse yalnız o grubun altındakiler). */
    public function find(string $name, ?int $parentId = null): ?Category
    {
        $key = Text::key($name);
        if ($key === '') {
            return null;
        }
        $pool = $parentId ? $this->pool()->where('parent_id', $parentId) : $this->pool();
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
        // Ad içinde geçen kategori ya da takma ad ("Psikoloji Popüler Bilim" → Psikoloji, "Yabancı Dil" → Dil ve Dilbilim,
        // "Dini Kitaplar" → Din ve Mitoloji); Türkçe ek kabul edilir, gruplar hariç. İlk geçen (eşitse uzun olan) kazanır.
        $names = $pool->filter(fn ($c) => $c->depth > 0)->map(fn ($c) => [$c->normalized_name, $c])->values()->all();
        foreach (CategoryAlias::query()->get() as $alias) {
            if (($cat = $pool->firstWhere('id', $alias->category_id)) && $cat->depth > 0) {
                $names[] = [$alias->match_key, $cat];
            }
        }
        $found = null;
        foreach ($names as [$needle, $cat]) {
            if (strlen($needle) >= 3 && preg_match('/(?:^| )'.preg_quote($needle, '/').'(?:'.self::SUFFIXES.')?(?= |$)/', $key, $m, PREG_OFFSET_CAPTURE)
                && ($found === null || $m[0][1] < $found[0] || ($m[0][1] === $found[0] && strlen($needle) > $found[2]))) {
                $found = [$m[0][1], $cat, strlen($needle)];
            }
        }

        return $found[1] ?? null;
    }

    /** Ada eklenebilen Türkçe ekler (Text::key biçiminde): "din" → "dini", "tarih" → "tarihi", "roman" → "romanlar" */
    private const SUFFIXES = 'i|u|si|su|in|un|e|a|ler|lar|leri|lari';

    /** Sınav Hazırlık › TYT (sınav adı listede yoksa grubun altına açılır: MSÜ, YKS…). */
    private function examCategory(string $exam): ?Category
    {
        $group = CategoryPlan::examGroup();
        if ($found = $this->find($exam, $group?->id)) {
            return $found;
        }

        return $group ? $this->ensure([$group->name, $exam]) : null;
    }

    /** Yalnız bu kategoriler arasından seç (eski ağacı sadeleştirirken: yalnız kısa liste). @param ?list<int> $ids */
    public function restrictTo(?array $ids): void
    {
        $this->restrict = $ids;
        $this->flush();
    }

    /** @return Collection<int, Category> en fazla iki kat */
    private function pool(): Collection
    {
        return $this->pool ??= Category::where('depth', '<=', 1)
            ->when($this->restrict !== null, fn ($q) => $q->whereIn('id', $this->restrict))
            ->get(['id', 'name', 'normalized_name', 'parent_id', 'depth', 'path']);
    }
}
