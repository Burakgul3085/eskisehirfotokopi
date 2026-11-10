<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryNeighborhood extends Model
{
    public $timestamps = false;

    protected $fillable = ['district_id', 'name', 'fee', 'is_active'];

    protected function casts(): array
    {
        return ['fee' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(DeliveryDistrict::class, 'district_id');
    }
}
