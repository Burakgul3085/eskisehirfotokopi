<?php

namespace App\Models;

use App\Support\Text;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Okunan ad → kategori ("Spor Sosyolojisi" → Sosyoloji). Toplu yüklemede kategori seçerken önce bunlara bakılır. */
class CategoryAlias extends Model
{
    protected $fillable = ['match_key', 'category_id'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Eşlemeyi kaydeder (varsa günceller). Kategorinin kendi adı eşleme olarak saklanmaz. */
    public static function remember(string $name, Category $category): void
    {
        $key = Text::key($name);
        if ($key === '' || $key === $category->normalized_name || mb_strlen($key) > 190) {
            return;
        }
        static::updateOrCreate(['match_key' => $key], ['category_id' => $category->id]);
    }
}
