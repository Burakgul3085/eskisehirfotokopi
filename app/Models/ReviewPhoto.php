<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Değerlendirme fotoğrafı: public diskte EXIF/konum bilgisi silinmiş webp; "-sm" sonekli küçük sürümü de vardır. */
class ReviewPhoto extends Model
{
    protected $fillable = ['review_id', 'path', 'width', 'height', 'is_approved', 'sort'];

    protected function casts(): array
    {
        return ['is_approved' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::deleted(fn (ReviewPhoto $p) => Storage::disk('public')->delete([$p->path, $p->thumbPath()]));
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(ProductReview::class, 'review_id');
    }

    public function thumbPath(): string
    {
        return (string) preg_replace('/\.webp$/', '-sm.webp', $this->path);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function thumbUrl(): string
    {
        return Storage::disk('public')->url($this->thumbPath());
    }
}
