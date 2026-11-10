<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Kategori ağacı. path = kökten kendisine kimlikler ("3/12/40/"); alt ağaç = path LIKE '{path}%'.
 * Yol ve derinlik App\Domain\Catalog\CategoryTree tarafından tutulur (taşıma alt ağacı da günceller).
 */
class Category extends Model
{
    use HasUniqueSlug;

    protected $fillable = ['parent_id', 'name', 'slug', 'path', 'depth', 'position', 'normalized_name', 'description', 'image_path',
        'icon', 'is_active', 'show_in_menu', 'meta_title', 'meta_description'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'show_in_menu' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('position')->orderBy('name');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** Kendisi ve tüm alt kategorilerinin kimlikleri. */
    public function subtreeIds(): Collection
    {
        return static::query()->where('path', 'like', $this->path.'%')->pluck('id');
    }

    /** @return Collection<int, Category> kökten kendisine (breadcrumb) */
    public function ancestorsAndSelf(): Collection
    {
        $ids = array_map('intval', array_filter(explode('/', (string) $this->path)));
        $map = static::query()->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $map[$id] ?? null)->filter()->values();
    }

    public function fullName(string $sep = ' › '): string
    {
        return $this->ancestorsAndSelf()->pluck('name')->implode($sep);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeRoots(Builder $q): Builder
    {
        return $q->whereNull('parent_id');
    }
}
