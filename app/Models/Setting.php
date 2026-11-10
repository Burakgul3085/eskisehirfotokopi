<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Ayar satırı. Okuma/yazma App\Support\Settings üzerinden yapılır (önbellek ve varsayılanlar orada). */
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];
}
