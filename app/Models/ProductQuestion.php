<?php

namespace App\Models;

use App\Support\Text;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ürün sorusu. Herkes sorabilir; cevaplanan ve "yayınla" seçilen sorular ürün sayfasında (ad kısaltılarak) görünür. */
class ProductQuestion extends Model
{
    protected $fillable = ['product_id', 'user_id', 'name', 'email', 'body', 'answer', 'answered_at', 'answered_by', 'is_public', 'ip'];

    protected function casts(): array
    {
        return ['answered_at' => 'datetime', 'is_public' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answerer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'answered_by');
    }

    /** Vitrinde görünenler: cevaplanmış ve yayınlanması seçilmiş sorular. */
    public function scopeVisible(Builder $q): Builder
    {
        return $q->where('is_public', true)->whereNotNull('answered_at');
    }

    public function isAnswered(): bool
    {
        return $this->answered_at !== null;
    }

    public function statusLabel(): string
    {
        return ! $this->isAnswered() ? 'Cevap bekliyor' : ($this->is_public ? 'Cevaplandı · yayında' : 'Cevaplandı · yalnız e-posta');
    }

    public function tone(): string
    {
        return ! $this->isAnswered() ? 'bg-accent-soft text-accent-ink' : ($this->is_public ? 'bg-success-soft text-success' : 'bg-info-soft text-info');
    }

    public function authorName(): string
    {
        return Text::maskName($this->name);
    }

    public function anchor(): string
    {
        return 'soru-'.$this->id;
    }

    public function url(): string
    {
        return route('product.show', $this->product).'#'.$this->anchor();
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);

        return $term === '' ? $q : $q->where(fn ($w) => $w->where('body', 'like', '%'.$term.'%')->orWhere('name', 'like', '%'.$term.'%')
            ->orWhere('email', 'like', '%'.$term.'%')->orWhereHas('product', fn ($p) => $p->where('title', 'like', '%'.$term.'%')));
    }
}
