<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Sipariş. Ödeme kapıda (nakit/kart); tutarlar sipariş anındaki fiyatlarla saklanır. */
class Order extends Model
{
    /** Sıra önemlidir: müşteriye gösterilen ilerleme çubuğu bu sırayı izler. */
    public const STATUSES = [
        'received' => 'Siparişiniz alındı',
        'confirmed' => 'Onaylandı',
        'preparing' => 'Hazırlanıyor',
        'shipping' => 'Yola çıktı',
        'delivered' => 'Teslim edildi',
        'cancelled' => 'İptal edildi',
    ];

    public const SHORT = [
        'received' => 'Yeni', 'confirmed' => 'Onaylandı', 'preparing' => 'Hazırlanıyor',
        'shipping' => 'Yolda', 'delivered' => 'Teslim edildi', 'cancelled' => 'İptal',
    ];

    public const PAYMENT_METHODS = ['cash' => 'Kapıda nakit', 'card' => 'Kapıda kredi/banka kartı'];

    protected $fillable = ['number', 'tracking_token', 'user_id', 'status', 'customer_name', 'phone', 'email', 'district_id', 'district_name',
        'neighborhood', 'address', 'delivery_slot', 'delivery_date', 'payment_method', 'subtotal', 'delivery_fee', 'total', 'note',
        'admin_note', 'cancel_reason', 'ip'];

    protected $hidden = ['tracking_token'];

    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'delivery_fee' => 'decimal:2', 'total' => 'decimal:2', 'delivery_date' => 'date'];
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(DeliveryDistrict::class, 'district_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function shortStatus(): string
    {
        return self::SHORT[$this->status] ?? $this->status;
    }

    public static function tone(string $status): string
    {
        return match ($status) {
            'received' => 'bg-accent-soft text-accent-ink',
            'confirmed', 'preparing' => 'bg-info-soft text-info',
            'shipping' => 'bg-primary-soft text-primary-dark',
            'delivered' => 'bg-success-soft text-success',
            'cancelled' => 'bg-danger-soft text-danger',
            default => 'bg-slate-100 text-muted',
        };
    }

    public function paymentLabel(): string
    {
        return self::PAYMENT_METHODS[$this->payment_method] ?? $this->payment_method;
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, ['delivered', 'cancelled'], true);
    }

    /** Müşteri sipariş takibi bağlantısı (üyeliksiz siparişte de çalışır). */
    public function trackingUrl(): string
    {
        return route('orders.track.show', ['order' => $this->number, 'token' => $this->tracking_token]);
    }

    /** İlerleme adımı (0–4); iptalde null. */
    public function step(): ?int
    {
        $i = array_search($this->status, array_keys(self::STATUSES), true);

        return $this->status === 'cancelled' || $i === false ? null : $i;
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $q;
        }
        $digits = preg_replace('/\D/', '', $term);

        return $q->where(function ($w) use ($term, $digits) {
            $w->where('number', 'like', '%'.strtoupper($term).'%')->orWhere('customer_name', 'like', '%'.$term.'%')
                ->orWhere('email', 'like', '%'.$term.'%');
            if (strlen($digits) >= 4) {
                $w->orWhere('phone', 'like', '%'.$digits.'%');
            }
        });
    }
}
