<?php

namespace App\Models;

use App\Domain\Cart\CartLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_id', 'kind', 'product_id', 'print_job_id', 'title', 'print_type', 'unit_price', 'quantity', 'line_total', 'meta'];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2', 'line_total' => 'decimal:2', 'meta' => 'array'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function printTypeLabel(): ?string
    {
        return CartLine::label($this->print_type, $this->meta['binding'] ?? null);
    }
}
