<?php

namespace App\Models;

use App\Support\Text;
use Illuminate\Database\Eloquent\Model;

/** Hiçbir sonuç vermeyen arama (aynı kelime tek satırda sayılır). */
class SearchMiss extends Model
{
    protected $fillable = ['term', 'match_key', 'hits', 'last_searched_at'];

    protected function casts(): array
    {
        return ['last_searched_at' => 'datetime'];
    }

    public static function record(string $term): void
    {
        $key = mb_substr(Text::key($term), 0, 100);
        if ($key === '') {
            return;
        }
        $miss = self::firstOrNew(['match_key' => $key]);
        $miss->term = mb_substr(trim($term), 0, 100);
        $miss->hits = $miss->exists ? $miss->hits + 1 : 1;
        $miss->last_searched_at = now();
        $miss->save();
    }
}
