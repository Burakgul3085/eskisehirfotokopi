<?php

namespace App\Domain\Catalog;

use App\Domain\Print\PrintOptions;
use App\Domain\Print\PrintPricing;
use App\Models\Product;

/**
 * Kitap fiyatı elle girilmez: ürünün PDF sayfa sayısından, belge gönderdeki yaprak fiyat kademeleriyle hesaplanır.
 *  - 1'e 1 (önlü arkalı, yüze 1 sayfa): yaprak = sayfa ÷ 2 (yukarı) · 2'ye 2 (yüze 2 sayfa): yaprak = sayfa ÷ 4 (yukarı).
 *  - Yaprak sayısının düştüğü kademenin S-B / renkli yaprak fiyatı × yaprak = kitap fiyatı (ciltsiz, A4, tek kopya).
 *    Ör. 400 sayfa: 1'e 1 = 200 yaprak → 200+ kademesi; 2'ye 2 = 100 yaprak → 100–199 kademesi.
 * Dört fiyat ürün tablosunda saklanır (listeleme, sıralama, sepet bunları okur); sayfa sayısı ya da fotokopi fiyatları
 * değişince yeniden hesaplanır.
 */
class BookPricing
{
    /** Ürün sütunu => [renk, yüze sayfa] */
    public const COLUMNS = [
        'price_bw' => ['bw', 1],
        'price_bw_2up' => ['bw', 2],
        'price_color' => ['color', 1],
        'price_color_2up' => ['color', 2],
    ];

    public function __construct(private PrintPricing $pricing) {}

    /** @return array<string, ?string> sayfa sayısı yoksa tüm fiyatlar null */
    public function prices(?int $pages): array
    {
        $out = [];
        foreach (self::COLUMNS as $column => [$color, $layout]) {
            $out[$column] = $pages > 0
                ? $this->pricing->quote($pages, new PrintOptions(color: $color, duplex: true, layout: $layout))['print_total']
                : null;
        }

        return $out;
    }

    /**
     * Sayfa sayısı olan tüm ürünlerin fiyatını güncel kademelere göre yeniler. Aynı sayfa sayılı ürünler tek sorguda
     * güncellenir (binlerce ürün için de hızlı).
     *
     * @return int güncellenen ürün sayısı
     */
    public function refreshAll(): int
    {
        $count = 0;
        Product::whereNotNull('page_count')->distinct()->orderBy('page_count')->pluck('page_count')
            ->each(function (int $pages) use (&$count) {
                $count += Product::where('page_count', $pages)->update($this->prices($pages));
            });

        return $count;
    }
}
