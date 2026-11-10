<?php

namespace App\Domain\Catalog;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use RuntimeException;
use Throwable;

/**
 * Ürün görseli (mockup): orijinal saklanmaz; 3 boyutta WebP üretilir (Product::IMAGE_SIZES). 10.000 üründe orijinaller
 * ~100 GB tutardı; WebP sürümleri ürün başına ~200–400 KB'tır. Her yüklemede yeni bir anahtar üretilir ki tarayıcı ve
 * CDN önbelleği eski görseli göstermesin.
 */
class ProductImages
{
    private const QUALITY = 82;

    public function store(Product $product, string $sourcePath): void
    {
        $previous = $product->image_key;
        $key = $product->id.'-'.Str::lower(Str::random(10));
        $dims = $this->generate($sourcePath, "products/{$key}");

        $product->forceFill(['image_key' => $key, 'image_width' => $dims[0], 'image_height' => $dims[1]])->save();
        if ($previous) {
            Storage::disk('public')->deleteDirectory("products/{$previous}");
        }
    }

    public function delete(Product $product): void
    {
        if ($product->image_key) {
            Storage::disk('public')->deleteDirectory("products/{$product->image_key}");
            $product->forceFill(['image_key' => null, 'image_width' => null, 'image_height' => null])->save();
        }
    }

    /** @return array{0:int,1:int} en büyük sürümün boyutları */
    private function generate(string $sourcePath, string $dir): array
    {
        $limit = ini_get('memory_limit');
        ini_set('memory_limit', '1024M'); // 10 MB'lık PNG açıldığında yüzlerce MB bellek gerekebilir
        $disk = Storage::disk('public');
        try {
            $manager = new ImageManager(new Driver);
            $image = $manager->read($sourcePath);
            $disk->makeDirectory($dir);
            $dims = [0, 0];
            // Büyükten küçüğe: her adım bir öncekinin küçültülmüşünden üretilir (hızlı).
            foreach (array_reverse(Product::IMAGE_SIZES, true) as $size => $width) {
                $image->scaleDown(width: $width);
                $disk->put("{$dir}/{$size}.webp", (string) $image->toWebp(self::QUALITY));
                if ($size === 'lg') {
                    $dims = [$image->width(), $image->height()];
                }
            }

            return $dims;
        } catch (Throwable $e) {
            $disk->deleteDirectory($dir);
            throw new RuntimeException('Görsel işlenemedi: '.$e->getMessage(), 0, $e);
        } finally {
            ini_set('memory_limit', $limit);
        }
    }
}
