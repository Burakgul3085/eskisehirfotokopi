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

    /** Ada göre bulur, yoksa oluşturur. Boş ad için null. */
    public static function resolve(?string $name): ?self
    {
        $name = trim((string) $name);
        if ($name === '' || Text::key($name) === '') {
            return null;
        }
        $key = Text::key($name);

        return static::firstOrCreate(['match_key' => $key], ['name' => $name, 'slug' => static::uniqueSlug($name)]);
    }
}
