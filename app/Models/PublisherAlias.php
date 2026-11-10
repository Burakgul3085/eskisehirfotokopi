<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublisherAlias extends Model
{
    protected $fillable = ['publisher_id', 'match_key'];

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }
}
