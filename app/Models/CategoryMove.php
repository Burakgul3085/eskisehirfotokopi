<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Toplu kategori taşıması; "Geri al" ürünleri eski kategorilerine döndürür, bu taşıma için açılan boş kategoriyi siler. */
class CategoryMove extends Model
{
    protected $fillable = ['admin_id', 'category_id', 'created_category_id', 'rule_id', 'rule_previous_category_id', 'target_name', 'moves', 'count', 'undone_at'];

    protected function casts(): array
    {
        return ['moves' => 'array', 'undone_at' => 'datetime'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(PlacementRule::class, 'rule_id');
    }

    /** Geri alınabilecek son taşıma (30 gün içinde). */
    public static function lastUndoable(): ?self
    {
        return static::whereNull('undone_at')->where('created_at', '>=', now()->subDays(30))->latest('id')->first();
    }
}
