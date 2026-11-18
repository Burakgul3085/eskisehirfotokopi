<?php

namespace App\Models;

use App\Support\Text;
use Illuminate\Database\Eloquent\Model;

/** Yönetimden tanımlanan arama yönlendirmesi: aranan kelime birebir eşleşirse doğrudan bu adrese gidilir. */
class SearchRedirect extends Model
{
    protected $fillable = ['term', 'match_key', 'url', 'hits'];

    protected static function booted(): void
    {
        static::saving(fn (self $r) => $r->match_key = Text::key($r->term));
    }
}
