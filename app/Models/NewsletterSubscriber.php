<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** E-bülten abonesi. Aktif = abonelikten çıkmamış. */
class NewsletterSubscriber extends Model
{
    protected $fillable = ['email', 'token', 'source', 'ip', 'consented_at', 'unsubscribed_at'];

    protected function casts(): array
    {
        return ['consented_at' => 'datetime', 'unsubscribed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $s) {
            $s->email = mb_strtolower(trim($s->email));
            $s->token ??= Str::random(48);
        });
    }

    public function scopeActive(Builder $q): void
    {
        $q->whereNull('unsubscribed_at');
    }

    public function isActive(): bool
    {
        return $this->unsubscribed_at === null;
    }

    public function unsubscribeUrl(): string
    {
        return route('newsletter.unsubscribe', $this->token);
    }

    /** Formdan kayıt: yeni abone ya da daha önce çıkmış birinin yeniden katılması. Aynı adres iki kez eklenmez. */
    public static function subscribe(string $email, ?string $ip, string $source = 'footer'): self
    {
        $sub = self::firstOrNew(['email' => mb_strtolower(trim($email))]);
        if (! $sub->exists || ! $sub->isActive()) {
            $sub->fill(['source' => $source, 'ip' => $ip, 'consented_at' => now(), 'unsubscribed_at' => null])->save();
        }

        return $sub;
    }
}
