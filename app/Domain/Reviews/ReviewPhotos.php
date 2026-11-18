<?php

namespace App\Domain\Reviews;

use App\Models\ProductReview;
use App\Models\ReviewPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Değerlendirme fotoğrafları: yüklenen görsel yeniden kodlanır (webp). Böylece EXIF, GPS konumu ve cihaz bilgileri
 * silinir, telefonun döndürme bilgisi uygulanır. Büyük sürüm (en fazla 1600 px) ve küçük sürüm (480 px) public diskte.
 */
class ReviewPhotos
{
    public const MAX = 4;

    public function store(ProductReview $review, UploadedFile $file, int $sort, bool $approved): ReviewPhoto
    {
        $disk = Storage::disk('public');
        $path = 'reviews/'.$review->product_id.'/'.Str::lower(Str::random(32)).'.webp';
        $limit = ini_get('memory_limit');
        ini_set('memory_limit', '512M');
        try {
            $image = (new ImageManager(new Driver))->read($file->getRealPath());
            $image->scaleDown(width: 1600, height: 1600);
            $disk->put($path, (string) $image->toWebp(82));
            [$width, $height] = [$image->width(), $image->height()];
            $image->scaleDown(width: 480, height: 480);
            $disk->put(preg_replace('/\.webp$/', '-sm.webp', $path), (string) $image->toWebp(78));
        } catch (Throwable $e) {
            $disk->delete([$path, preg_replace('/\.webp$/', '-sm.webp', $path)]);
            throw new PhotoProcessingFailed('Fotoğraf işlenemedi: '.$e->getMessage(), 0, $e);
        } finally {
            ini_set('memory_limit', $limit);
        }

        return $review->photos()->create(['path' => $path, 'width' => $width, 'height' => $height, 'is_approved' => $approved, 'sort' => $sort]);
    }
}
