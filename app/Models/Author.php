<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use App\Support\Text;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Author extends Model
{
    use HasUniqueSlug;

    protected $fillable = ['name', 'slug', 'match_key'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** Hiç ürünü kalmadıysa yazar kaydını siler. */
    public static function pruneIfOrphan(int $id): bool
    {
        return (bool) static::whereKey($id)->whereDoesntHave('products')->delete();
    }

    /** Ada göre bulur, yoksa oluşturur. Boş ad için null. */
    public static function resolve(?string $name): ?self
    {
        $name = trim((string) $name);
        if ($name === '' || Text::key($name) === '') {
            return null;
        }
        $key = Text::key($name);

        return static::where('match_key', $key)->first() ?? static::findSimilar($key)
            ?? static::create(['match_key' => $key, 'name' => $name, 'slug' => static::uniqueSlug($name)]);
    }

    /**
     * Daha önce açılmış aynı yazar: kelime sırası farklı ("Fulbrook Mary") ya da OCR'nin tek harf hatası
     * ("Mary Fulbrok"; uzun adlarda iki harf). Kısa adlarda harf farkı farklı kişi olabileceğinden yalnız sıra eşleşir.
     */
    public static function findSimilar(string $key): ?self
    {
        $sorted = fn ($k) => implode(' ', collect(explode(' ', $k))->sort()->all());
        $len = strlen($key);
        foreach (static::query()->select('id', 'match_key')->cursor() as $author) {
            if ($sorted($author->match_key) === $sorted($key)) {
                return static::find($author->id);
            }
            $max = $len >= 16 ? 2 : ($len >= 10 ? 1 : 0);
            if ($max > 0 && abs(strlen($author->match_key) - $len) <= $max && levenshtein($author->match_key, $key) <= $max
                && substr_count($author->match_key, ' ') === substr_count($key, ' ')) {
                return static::find($author->id);
            }
        }

        return null;
    }
}
