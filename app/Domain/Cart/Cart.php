<?php

namespace App\Domain\Cart;

use App\Domain\Print\PrintSettings;
use App\Models\Product;
use Illuminate\Contracts\Session\Session;

/**
 * Oturumda tutulan sepet (misafir ve üye aynı). Oturumda yalnızca kimlik, baskı tipi ve adet saklanır; ad ve fiyat her
 * gösterimde güncel kayıttan okunur. Yayından kalkan veya fiyatı silinen ürün sepetten kendiliğinden düşer.
 *
 * Baskı işleri (fotokopi modülü) "print:{id}" anahtarıyla eklenir; satırları PrintCartLines üretir.
 */
class Cart
{
    public const MAX_LINES = 60;

    public const MAX_QTY = 50;

    private const KEY = 'cart.items';

    /** @var list<CartLine>|null */
    private ?array $lines = null;

    /** @var array<string, callable(array, string): ?CartLine> tür => satır üretici */
    private static array $resolvers = [];

    public function __construct(private Session $session) {}

    /** Başka modüllerin (baskı işleri) satır türü eklemesi için. */
    public static function resolver(string $kind, callable $resolver): void
    {
        self::$resolvers[$kind] = $resolver;
    }

    /** @return array<string, array> */
    public function raw(): array
    {
        return (array) $this->session->get(self::KEY, []);
    }

    public function addProduct(Product $product, string $printType, int $qty = 1, string $binding = 'none'): string
    {
        $binding = isset(Product::BINDINGS[$binding]) ? $binding : 'none';
        $key = 'product:'.$product->id.':'.$printType.($binding === 'none' ? '' : ':'.$binding);
        $items = $this->raw();
        $current = $items[$key]['qty'] ?? 0;
        if (! isset($items[$key]) && count($items) >= self::MAX_LINES) {
            throw new \DomainException('Sepette en fazla '.self::MAX_LINES.' satır olabilir.');
        }
        $items[$key] = ['kind' => 'product', 'product_id' => $product->id, 'print_type' => $printType, 'binding' => $binding,
            'qty' => min(self::MAX_QTY, $current + max(1, $qty))];
        $this->store($items);

        return $key;
    }

    public function addItem(string $kind, int $id, array $data = []): string
    {
        $key = $kind.':'.$id;
        $items = $this->raw();
        $items[$key] = ['kind' => $kind, 'id' => $id, 'qty' => 1] + $data;
        $this->store($items);

        return $key;
    }

    public function setQuantity(string $key, int $qty): void
    {
        $items = $this->raw();
        if (! isset($items[$key])) {
            return;
        }
        if ($qty <= 0) {
            unset($items[$key]);
        } else {
            $items[$key]['qty'] = min(self::MAX_QTY, $qty);
        }
        $this->store($items);
    }

    public function remove(string $key): void
    {
        $items = $this->raw();
        unset($items[$key]);
        $this->store($items);
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
        $this->lines = null;
    }

    public function count(): int
    {
        return array_sum(array_map(fn ($i) => (int) ($i['qty'] ?? 1), $this->raw()));
    }

    public function isEmpty(): bool
    {
        return $this->lines() === [];
    }

    /** @return list<CartLine> geçerli satırlar (geçersizler oturumdan da silinir) */
    public function lines(): array
    {
        if ($this->lines !== null) {
            return $this->lines;
        }
        $items = $this->raw();
        $productIds = array_column(array_filter($items, fn ($i) => ($i['kind'] ?? '') === 'product'), 'product_id');
        $products = $productIds ? Product::query()->visible()->with('publisher:id,name')->whereIn('id', $productIds)->get()->keyBy('id') : collect();

        $lines = [];
        $dirty = false;
        foreach ($items as $key => $item) {
            $line = match ($item['kind'] ?? null) {
                'product' => $this->productLine($key, $item, $products[$item['product_id']] ?? null),
                default => isset(self::$resolvers[$item['kind'] ?? '']) ? (self::$resolvers[$item['kind']])($item, $key) : null,
            };
            if ($line === null) {
                unset($items[$key]);
                $dirty = true;

                continue;
            }
            $lines[] = $line;
        }
        if ($dirty) {
            $this->session->put(self::KEY, $items);
        }

        return $this->lines = $lines;
    }

    public function subtotal(): float
    {
        return round(array_sum(array_map(fn (CartLine $l) => $l->total(), $this->lines())), 2);
    }

    private function productLine(string $key, array $item, ?Product $product): ?CartLine
    {
        $price = $product?->price($item['print_type']);
        if ($product === null || $price === null) {
            return null;
        }
        // Spiral: fotokopi ayarlarındaki güncel sabit fiyat kitap başına eklenir (belge gönderdeki gibi).
        $binding = $item['binding'] ?? 'none';
        $bindingPrice = $binding === 'spiral' ? (string) app(PrintSettings::class)->get('spiral_price') : '0.00';

        return new CartLine(
            key: $key,
            kind: 'product',
            title: $product->title,
            unitPrice: (float) bcadd($price, $bindingPrice, 2),
            quantity: max(1, min(self::MAX_QTY, (int) $item['qty'])),
            printType: $item['print_type'],
            url: route('product.show', $product),
            image: $product->imageUrl('sm'),
            subtitle: $product->publisher?->name,
            product: $product,
            maxQuantity: self::MAX_QTY,
            meta: array_filter(['binding' => isset($item['binding']) ? $binding : null, 'binding_price' => $binding === 'spiral' ? $bindingPrice : null]),
        );
    }

    private function store(array $items): void
    {
        $this->session->put(self::KEY, $items);
        $this->lines = null;
    }
}
