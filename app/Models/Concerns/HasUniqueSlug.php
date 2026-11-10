<?php

namespace App\Models\Concerns;

use App\Support\Text;

/** Tabloda benzersiz slug üretir: "kitap", "kitap-2", "kitap-3"… */
trait HasUniqueSlug
{
    public static function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = mb_substr(Text::slug($source), 0, 180);
        $slug = $base;
        $i = 2;
        while (static::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
