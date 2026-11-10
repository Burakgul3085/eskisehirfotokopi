<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $fillable = ['user_id', 'title', 'name', 'phone', 'district_id', 'neighborhood', 'address', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(DeliveryDistrict::class, 'district_id');
    }

    public function oneLine(): string
    {
        return $this->neighborhood.' Mah., '.$this->address.' — '.($this->district?->name ?? '');
    }
}
