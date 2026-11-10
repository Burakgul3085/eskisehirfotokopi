<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Teslimat yapılan ilçe; ücret, eşik ve en düşük tutar ilçeye özel olabilir (boşsa genel ayar). */
class DeliveryDistrict extends Model
{
    protected $fillable = ['name', 'fee', 'free_threshold', 'min_order', 'extra_days', 'is_active', 'position', 'note'];

    protected function casts(): array
    {
        return ['fee' => 'decimal:2', 'free_threshold' => 'decimal:2', 'min_order' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function neighborhoods(): HasMany
    {
        return $this->hasMany(DeliveryNeighborhood::class, 'district_id')->orderBy('name');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('position')->orderBy('name');
    }
}
