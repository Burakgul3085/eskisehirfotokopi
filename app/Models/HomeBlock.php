<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class HomeBlock extends Model
{
    /** tür => yönetimde görünen ad */
    public const TYPES = [
        'hero' => 'Karşılama alanı (büyük başlık ve arama)',
        'slider' => 'Görsel slayt',
        'categories' => 'Kategori kutuları',
        'products' => 'Ürün satırı',
        'publishers' => 'Yayınevi logoları',
        'print_banner' => 'Fotokopi / belge baskısı bandı',
        'features' => 'Güven şeridi (simge + kısa metin)',
        'faq' => 'Sıkça sorulan sorular',
        'text' => 'Serbest metin',
    ];

    public const PRODUCT_SOURCES = [
        'newest' => 'Yeni eklenenler',
        'bestsellers' => 'Çok satanlar',
        'popular' => 'En çok incelenenler',
        'featured' => 'Öne çıkan ürünler',
        'discounted' => 'İndirimli ürünler',
        'category' => 'Seçilen kategori',
        'publisher' => 'Seçilen yayınevi',
        'manual' => 'El ile seçilen ürünler',
    ];

    public const CACHE_KEY = 'home.blocks';

    protected $fillable = ['type', 'title', 'subtitle', 'settings', 'position', 'is_active'];

    protected function casts(): array
    {
        return ['settings' => 'array', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function scopeVisible(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('position')->orderBy('id');
    }
}
