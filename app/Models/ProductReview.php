<?php

namespace App\Models;

use App\Support\Text;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ürün değerlendirmesi. Müşterinin son gönderdiği hâl rating/title/body'dedir; vitrinde published_* (onaylanmış hâl)
 * gösterilir. Yayındaki değerlendirme düzenlenince durum yeniden "pending" olur, onaylanana kadar eski hâl görünür.
 */
class ProductReview extends Model
{
    public const STATUSES = ['pending' => 'Onay bekliyor', 'approved' => 'Yayında', 'rejected' => 'Reddedildi'];

    protected $fillable = ['product_id', 'user_id', 'order_id', 'rating', 'title', 'body', 'status', 'published_rating', 'published_title',
        'published_body', 'published_at', 'reject_reason', 'reply', 'replied_at', 'reply_admin_id', 'submitted_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'replied_at' => 'datetime', 'submitted_at' => 'datetime', 'rating' => 'integer', 'published_rating' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function replyAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reply_admin_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ReviewPhoto::class, 'review_id')->orderBy('sort')->orderBy('id');
    }

    /** Vitrinde görünenler: en az bir kez onaylanmış olanlar (düzenlemesi onay bekleyenler eski hâliyle). */
    public function scopePublished(Builder $q): Builder
    {
        return $q->whereNotNull('published_at');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /** Yayındaki bir değerlendirmenin onay bekleyen düzenlemesi mi? */
    public function isPendingEdit(): bool
    {
        return $this->status === 'pending' && $this->isPublished();
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->isPendingEdit() => 'Düzenleme onay bekliyor',
            $this->status === 'rejected' && $this->isPublished() => 'Düzenleme reddedildi · önceki hâli yayında',
            default => self::STATUSES[$this->status] ?? $this->status,
        };
    }

    public static function tone(string $status): string
    {
        return match ($status) {
            'pending' => 'bg-accent-soft text-accent-ink',
            'approved' => 'bg-success-soft text-success',
            'rejected' => 'bg-danger-soft text-danger',
            default => 'bg-slate-100 text-muted',
        };
    }

    /** Vitrinde gösterilen ad: "Ayşe K." */
    public function authorName(): string
    {
        return Text::maskName($this->user?->name);
    }

    /** Vitrinde gösterilen onaylı fotoğraflar. */
    public function publicPhotos(): Collection
    {
        return $this->photos->where('is_approved', true)->values();
    }

    public function anchor(): string
    {
        return 'degerlendirme-'.$this->id;
    }

    public function url(): string
    {
        return route('product.show', $this->product).'#'.$this->anchor();
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);

        return $term === '' ? $q : $q->where(fn ($w) => $w->where('body', 'like', '%'.$term.'%')->orWhere('title', 'like', '%'.$term.'%')
            ->orWhereHas('product', fn ($p) => $p->where('title', 'like', '%'.$term.'%'))
            ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%')));
    }
}
