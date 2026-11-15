<?php

namespace App\Domain\Catalog;

use App\Models\Product;
use App\Support\Text;
use Illuminate\Support\Facades\Cache;

/**
 * Sitedeki kategorisi belli kitaplardan öğrenir (yerel; dışarıya veri gitmez):
 *  - kelime istatistiği (naive Bayes): yeni kitabın adı/alt başlığı/konuları en çok hangi kategorideki kitaplara benziyor
 *  - yayınevi profili: yayınevinin kitapları hangi kategorilerde (345 Yayınları → çoğu sınav kitabı)
 *  - yazar profili: yazarın diğer kitapları hangi kategoride (Russell → Felsefe)
 * Model 5 dakika önbellekte tutulur; yönetici bir kitabın kategorisini değiştirince forget() ile hemen tazelenir.
 */
class CategoryLearner
{
    private const CACHE = 'category-learner:v1';

    /** Anlam taşımayan kelimeler */
    private const STOP = ['ve', 'ile', 'bir', 'icin', 'gibi', 'olan', 'the', 'and', 'of', 'to', 'in', 'a', 'an', 'der', 'die', 'das', 'und',
        'kitabi', 'kitap', 'yayinlari', 'yayinevi', 'cilt', 'baski', 'yeni', 'bu', 'da', 'de', 'mi', 'ne', 'en', 'cok'];

    private ?array $model = null;

    public static function forget(): void
    {
        Cache::forget(self::CACHE);
    }

    /** @return list<string> */
    public static function tokens(string $text): array
    {
        $out = [];
        foreach (explode(' ', Text::key($text)) as $w) {
            if (strlen($w) >= 3 && ! ctype_digit($w) && ! in_array($w, self::STOP, true)) {
                $out[] = $w;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Kelimelere göre kategori olasılıkları (yalnız $candidates arasında; hiçbir kelime tanınmıyorsa boş).
     *
     * @param  list<string>  $tokens
     * @param  list<int>  $candidates
     * @return array<int, float>
     */
    public function predict(array $tokens, array $candidates): array
    {
        $m = $this->model();
        $candidates = array_values(array_filter($candidates, fn ($c) => ($m['docs'][$c] ?? 0) >= 2));
        $known = array_values(array_filter($tokens, fn ($t) => isset($m['vocab'][$t])));
        if (! $candidates || ! $known) {
            return [];
        }
        $v = max(1, count($m['vocab']));
        $all = array_sum($m['docs']);
        $logs = [];
        foreach ($candidates as $c) {
            $lp = log($m['docs'][$c] / $all);
            foreach ($known as $t) {
                $lp += log((($m['words'][$c][$t] ?? 0) + 1) / ($m['totals'][$c] + $v));
            }
            $logs[$c] = $lp;
        }
        $max = max($logs);
        $exp = array_map(fn ($l) => exp($l - $max), $logs);
        $sum = array_sum($exp);

        return array_map(fn ($e) => $e / $sum, $exp);
    }

    /** Yayınevinin kitaplarının kategorilere dağılımı (oran) ve toplam kitap sayısı. @return array{0: array<int, float>, 1: int} */
    public function publisher(?int $id): array
    {
        return $this->share($this->model()['publishers'][$id] ?? []);
    }

    /** @return array{0: array<int, float>, 1: int} */
    public function author(?int $id): array
    {
        return $this->share($this->model()['authors'][$id] ?? []);
    }

    private function share(array $counts): array
    {
        $total = array_sum($counts);

        return [$total ? array_map(fn ($n) => $n / $total, $counts) : [], $total];
    }

    /** @return array{docs: array<int,int>, words: array<int, array<string,int>>, totals: array<int,int>, vocab: array<string,bool>, publishers: array<int, array<int,int>>, authors: array<int, array<int,int>>} */
    private function model(): array
    {
        return $this->model ??= Cache::remember(self::CACHE, 300, function () {
            $m = ['docs' => [], 'words' => [], 'totals' => [], 'vocab' => [], 'publishers' => [], 'authors' => []];
            $rows = Product::query()->whereNotNull('category_id')
                ->select(['id', 'title', 'subtitle', 'topics', 'subject', 'book_type', 'category_id', 'publisher_id', 'author_id'])->cursor();
            foreach ($rows as $p) {
                $c = $p->category_id;
                $m['docs'][$c] = ($m['docs'][$c] ?? 0) + 1;
                $text = implode(' ', array_filter([$p->title, $p->subtitle, $p->subject, $p->book_type, ...($p->topics ?? [])]));
                foreach (self::tokens($text) as $t) {
                    $m['words'][$c][$t] = ($m['words'][$c][$t] ?? 0) + 1;
                    $m['totals'][$c] = ($m['totals'][$c] ?? 0) + 1;
                    $m['vocab'][$t] = true;
                }
                $m['totals'][$c] ??= 0;
                if ($p->publisher_id) {
                    $m['publishers'][$p->publisher_id][$c] = ($m['publishers'][$p->publisher_id][$c] ?? 0) + 1;
                }
                if ($p->author_id) {
                    $m['authors'][$p->author_id][$c] = ($m['authors'][$p->author_id][$c] ?? 0) + 1;
                }
            }

            return $m;
        });
    }
}
