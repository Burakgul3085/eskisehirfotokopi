<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Müşteri mesaj konusu (iletişim formu). Müşteri gizli bağlantıdan (token) veya Hesabım'dan devam eder. */
class MessageThread extends Model
{
    public const STATUSES = ['open' => 'Yanıt bekliyor', 'answered' => 'Yanıtlandı', 'closed' => 'Kapandı'];

    protected $fillable = ['token', 'user_id', 'order_id', 'name', 'email', 'phone', 'subject', 'status', 'unread_admin', 'last_message_at'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['unread_admin' => 'boolean', 'last_message_at' => 'datetime'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'thread_id')->orderBy('id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public static function tone(string $status): string
    {
        return match ($status) {
            'open' => 'bg-accent-soft text-accent-ink',
            'answered' => 'bg-success-soft text-success',
            default => 'bg-slate-100 text-muted',
        };
    }

    /** Müşterinin konuşmayı sürdürdüğü bağlantı (e-postalarda). */
    public function url(): string
    {
        return route('messages.show', ['thread' => $this->id, 'token' => $this->token]);
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);

        return $term === '' ? $q : $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")
            ->orWhere('subject', 'like', "%{$term}%"));
    }
}
