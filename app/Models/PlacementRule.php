<?php

namespace App\Models;

use App\Support\Text;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Yerleştirme kuralı: ürün listesindeki arama ve süzgeçler (arama metni, ders, kitap türü, yayınevi, kategori) kaydedilir;
 * bundan sonra yüklenen ve bu aramaya uyan kitaplar otomatik yerleştirmeden önce kuralın kategorisine gider.
 * Kategori koşulu, otomatik yerleştirmenin seçtiği kategoriye bakar ("Sınav Hazırlık'a düşen biyoloji kitapları").
 */
class PlacementRule extends Model
{
    /** Kuralda saklanan süzgeçler: ürün listesindeki adı => koşul adı */
    public const FILTERS = ['q' => 'q', 'ders' => 'subject', 'tur' => 'book_type', 'yayinevi' => 'publisher_id', 'kategori' => 'category_id'];

    protected $fillable = ['conditions', 'label', 'category_id', 'admin_id', 'applied_count'];

    /** @var array<int, list<int>> kategori alt ağacı önbelleği */
    private array $subtrees = [];

    protected function casts(): array
    {
        return ['conditions' => 'array'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * Ürün listesi süzgeçlerinden kural koşulları. Durum, fiyat, görsel gibi yüklemede anlamı olmayanlar alınmaz;
     * "Kategorisiz" (0) da alınmaz.
     *
     * @return array<string, string|int>
     */
    public static function conditionsFrom(array $filters): array
    {
        $c = [];
        if (($q = trim(preg_replace('/\s+/u', ' ', (string) ($filters['q'] ?? '')))) !== '' && Text::key($q) !== '') {
            $c['q'] = mb_substr($q, 0, 190);
        }
        foreach (['ders' => 'subject', 'tur' => 'book_type'] as $from => $to) {
            if (($v = trim((string) ($filters[$from] ?? ''))) !== '') {
                $c[$to] = mb_substr($v, 0, 190);
            }
        }
        foreach (['yayinevi' => 'publisher_id', 'kategori' => 'category_id'] as $from => $to) {
            if ((int) ($filters[$from] ?? 0) > 0) {
                $c[$to] = (int) $filters[$from];
            }
        }

        return $c;
    }

    /** "Arama: biyoloji · Ders: Biyoloji · Yayınevi: 3D Yayınları" */
    public static function describe(array $c): string
    {
        $parts = [];
        if (isset($c['q'])) {
            $parts[] = 'Arama: “'.$c['q'].'”';
        }
        if (isset($c['subject'])) {
            $parts[] = 'Ders: '.$c['subject'];
        }
        if (isset($c['book_type'])) {
            $parts[] = 'Kitap türü: '.$c['book_type'];
        }
        if (isset($c['publisher_id'])) {
            $parts[] = 'Yayınevi: '.(Publisher::whereKey($c['publisher_id'])->value('name') ?? '#'.$c['publisher_id']);
        }
        if (isset($c['category_id'])) {
            $parts[] = 'Kategori: '.(Category::find($c['category_id'])?->fullName() ?? '#'.$c['category_id']);
        }

        return mb_substr(implode(' · ', $parts), 0, 500);
    }

    public function needsCategory(): bool
    {
        return isset($this->conditions['category_id']);
    }

    /** Daha çok koşullu kural daha belirgindir; eşitlikte yenisi kazanır. */
    public function specificity(): int
    {
        return count($this->conditions ?? []);
    }

    /**
     * Gelen kitap kurala uyuyor mu? Arama ürün listesindeki gibi: her kelime kitabın metninde geçmeli
     * (10+ haneli arama ISBN'le de eşleşir). Ders ve tür harf/Türkçe karakter farkı gözetmeden aynı olmalı.
     *
     * @param  array{text: string, isbn?: ?string, subject?: ?string, book_type?: ?string, publisher_id?: ?int, category_id?: ?int}  $book
     */
    public function matches(array $book): bool
    {
        $c = $this->conditions ?? [];
        if ($c === []) {
            return false;
        }
        if (isset($c['q'])) {
            $digits = preg_replace('/\D/', '', $c['q']);
            $isbnHit = strlen($digits) >= 10 && ($book['isbn'] ?? null) === $digits;
            $text = Text::key($book['text'] ?? '');
            $terms = array_filter(explode(' ', Text::key($c['q'])));
            $textHit = $terms !== [] && collect($terms)->every(fn ($t) => str_contains($text, $t));
            if (! $isbnHit && ! $textHit) {
                return false;
            }
        }
        foreach (['subject', 'book_type'] as $k) {
            if (isset($c[$k]) && Text::key((string) ($book[$k] ?? '')) !== Text::key($c[$k])) {
                return false;
            }
        }
        if (isset($c['publisher_id']) && (int) ($book['publisher_id'] ?? 0) !== (int) $c['publisher_id']) {
            return false;
        }
        if (isset($c['category_id'])) {
            $ids = $this->subtrees[$c['category_id']] ??= (Category::find($c['category_id'])?->subtreeIds()->map(fn ($id) => (int) $id)->all() ?? []);
            if (! in_array((int) ($book['category_id'] ?? 0), $ids, true)) {
                return false;
            }
        }

        return true;
    }
}
