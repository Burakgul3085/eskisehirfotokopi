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
        return ['bw' => 'Siyah-beyaz', 'color' => 'Renkli'][$this->printType] ?? null;
    }
}
