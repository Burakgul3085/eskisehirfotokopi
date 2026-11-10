<?php

namespace App\Domain\Cart;

use App\Models\Product;

/** Sepetteki bir satır (katalog ürünü veya baskı işi). Fiyatlar her zaman sunucuda güncel kayıttan hesaplanır. */
class CartLine
{
    public function __construct(
        public string $key,
        public string $kind,            // product | print
        public string $title,
        public float $unitPrice,
        public int $quantity,
        public ?string $printType = null,
        public ?string $url = null,
        public ?string $image = null,
        public ?string $subtitle = null,
        public ?Product $product = null,
        public ?int $printJobId = null,
        public int $maxQuantity = 50,
        public array $meta = [],
    ) {}

    public function total(): float
    {
        return round($this->unitPrice * $this->quantity, 2);
    }

    public function printTypeLabel(): ?string
    {
        return self::label($this->printType, $this->meta['binding'] ?? null);
    }

    /** "Siyah-beyaz · 2'ye 2 · Spiral cilt" (sipariş satırı da kullanır) */
    public static function label(?string $printType, ?string $binding): ?string
    {
        $type = $printType ? (Product::PRINT_TYPES[$printType] ?? null) : null;

        return $type && $binding ? $type.' · '.(Product::BINDINGS[$binding] ?? $binding) : $type;
    }
}
