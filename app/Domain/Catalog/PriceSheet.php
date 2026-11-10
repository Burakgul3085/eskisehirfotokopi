<?php

namespace App\Domain\Catalog;

use App\Models\Product;
use App\Support\Activity;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

/**
 * Excel şablonu (K2, K3). Dışa aktarma: seçilen ürünler satır satır. Satış fiyatı elle girilmez: yönetim "Sayfa"
 * sütununa PDF sayfa sayısını yazar, fiyatlar (S-B / renkli, 1'e 1) fotokopi kademelerinden hesaplanır; fiyat sütunları
 * yalnız bilgi içindir, geri yüklemede okunmaz. Üstü çizili eski fiyat sütunları düzenlenebilir. Eşleştirme ID sütunuyla.
 *
 * Hücre kuralları: boş = değiştirme · "-" veya "sil" = kaldır · sayı = yeni değer (fiyat "149,90", "149.9", "1.250").
 */
class PriceSheet
{
    public const HEADERS = ['ID', 'Ürün Kodu', 'ISBN', 'Kitap Adı', 'Yayınevi', 'Yazar', 'Sayfa (PDF sayfa sayısı)', 'Durum',
        "S-B Fiyat (otomatik, 1'e 1)", "Renkli Fiyat (otomatik, 1'e 1)", 'S-B Eski Fiyat (üstü çizili)', 'Renkli Eski Fiyat (üstü çizili)'];

    /** Düzenlenebilir sütunlar */
    private const PAGE_COLUMN = 6;

    /** Hesaplanan fiyat sütunları (yalnız dışa aktarılır) */
    private const COMPUTED_COLUMNS = [8 => 'price_bw', 9 => 'price_color'];

    /** Elle girilen eski fiyat sütunları: başlık indeksi => ürün alanı */
    private const PRICE_COLUMNS = [10 => 'compare_bw', 11 => 'compare_color'];

    public function export(Builder $query, string $path): int
    {
        $writer = new Writer;
        $writer->openToFile($path);
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Fiyatlar');
        $sheet->setSheetView(new SheetView(freezeRow: 2, freezeColumn: 'E'));
        foreach ([8, 14, 15, 48, 28, 24, 8, 10, 16, 16, 18, 18] as $i => $w) {
            $sheet->setColumnWidth($w, $i + 1);
        }

        $head = (new Style)->withFontBold(true)->withFontColor('FFFFFF')->withBackgroundColor('0F766E')->withShouldWrapText(true);
        $priceHead = (new Style)->withFontBold(true)->withBackgroundColor('FDE68A')->withShouldWrapText(true);
        $editable = fn (int $i) => $i === self::PAGE_COLUMN || isset(self::PRICE_COLUMNS[$i]);
        $writer->addRow(new Row(array_map(fn ($h, $i) => Cell::fromValue($h, $editable($i) ? $priceHead : $head), self::HEADERS, array_keys(self::HEADERS)), 32));

        $locked = (new Style)->withFontColor('475569');
        $price = (new Style)->withBackgroundColor('FFFBEB')->withFormat('0.00');
        $pagesStyle = (new Style)->withBackgroundColor('FFFBEB');
        $computed = (new Style)->withFontColor('475569')->withFormat('0.00');
        $count = 0;
        $query->with(['publisher:id,name', 'author:id,name'])->orderBy('publisher_id')->orderBy('title')
            ->chunkById(1000, function ($products) use ($writer, $locked, $price, $pagesStyle, $computed, &$count) {
                foreach ($products as $p) {
                    $cells = [
                        Cell::fromValue($p->id, $locked),
                        Cell::fromValue((string) $p->code, $locked),
                        Cell::fromValue((string) $p->isbn, $locked),
                        Cell::fromValue($p->title, $locked),
                        Cell::fromValue((string) $p->publisher?->name, $locked),
                        Cell::fromValue((string) $p->author?->name, $locked),
                        Cell::fromValue($p->page_count, $pagesStyle),
                        Cell::fromValue(Product::STATUSES[$p->status] ?? $p->status, $locked),
                    ];
                    foreach (self::COMPUTED_COLUMNS as $field) {
                        $cells[] = Cell::fromValue($p->{$field} !== null ? (float) $p->{$field} : null, $computed);
                    }
                    foreach (self::PRICE_COLUMNS as $field) {
                        $cells[] = Cell::fromValue($p->{$field} !== null ? (float) $p->{$field} : null, $price);
                    }
                    $writer->addRow(new Row($cells));
                    $count++;
                }
            });
        $writer->close();

        return $count;
    }

    /**
     * $publishPriced: sayfa sayısı girilip fiyatı oluşan taslaklar yayına alınır.
     *
     * @return array{updated:int, unchanged:int, published:int, errors:list<string>}
     */
    public function import(string $path, bool $publishPriced = false): array
    {
        $reader = new Reader;
        $reader->open($path);
        $updates = [];
        $errors = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $rowNo = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    $rowNo++;
                    $cells = $row->toArray();
                    if ($rowNo === 1) {
                        if (trim((string) ($cells[0] ?? '')) !== 'ID') {
                            throw new RuntimeException('Dosya fiyat şablonu biçiminde değil (ilk sütun "ID" olmalı). Şablonu panelden indirip kullanın.');
                        }

                        continue;
                    }
                    $id = filter_var($cells[0] ?? null, FILTER_VALIDATE_INT);
                    if (! $id) {
                        if (array_filter($cells, fn ($c) => $c !== null && $c !== '') !== []) {
                            $errors[] = "Satır {$rowNo}: ID geçersiz.";
                        }

                        continue;
                    }
                    $change = [];
                    $rawPages = $cells[self::PAGE_COLUMN] ?? null;
                    if ($rawPages !== null && ! (is_string($rawPages) && trim($rawPages) === '')) {
                        if (is_string($rawPages) && in_array(mb_strtolower(trim($rawPages)), ['-', 'sil', 'yok'], true)) {
                            $change['page_count'] = null;
                        } else {
                            $pages = filter_var(is_string($rawPages) ? trim($rawPages) : $rawPages, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 20000]]);
                            if ($pages === false && is_float($rawPages) && floor($rawPages) === $rawPages && $rawPages >= 1 && $rawPages <= 20000) {
                                $pages = (int) $rawPages; // Excel sayıları ondalıklı okunabilir (320.0)
                            }
                            if ($pages === false) {
                                $errors[] = "Satır {$rowNo} (ID {$id}): sayfa sayısı geçersiz: ".mb_substr((string) $rawPages, 0, 20);

                                continue;
                            }
                            $change['page_count'] = $pages;
                        }
                    }
                    foreach (self::PRICE_COLUMNS as $col => $field) {
                        $raw = $cells[$col] ?? null;
                        if ($raw === null || (is_string($raw) && trim($raw) === '')) {
                            continue;
                        }
                        if (is_string($raw) && in_array(mb_strtolower(trim($raw)), ['-', 'sil', 'yok'], true)) {
                            $change[$field] = null;

                            continue;
                        }
                        $value = Money::parse($raw);
                        if ($value === null || bccomp($value, '0', 2) <= 0 || bccomp($value, '1000000', 2) >= 0) {
                            $errors[] = "Satır {$rowNo} (ID {$id}): “".self::HEADERS[$col].'” geçersiz: '.mb_substr((string) $raw, 0, 20);

                            continue 2;
                        }
                        $change[$field] = $value;
                    }
                    if ($change) {
                        $updates[$id] = $change;
                    }
                }
                break; // yalnız ilk sayfa
            }
        } finally {
            $reader->close();
        }

        $updated = 0;
        $unchanged = 0;
        $published = 0;
        foreach (array_chunk($updates, 500, true) as $chunk) {
            DB::transaction(function () use ($chunk, $publishPriced, &$updated, &$unchanged, &$published, &$errors) {
                $products = Product::whereIn('id', array_keys($chunk))->get()->keyBy('id');
                foreach ($chunk as $id => $change) {
                    $p = $products[$id] ?? null;
                    if (! $p) {
                        $errors[] = "ID {$id}: ürün bulunamadı (silinmiş olabilir).";

                        continue;
                    }
                    $p->fill($change);
                    // Fiyat kayıtta sayfa sayısından hesaplanır; sayfa sayısı olan taslak yayına alınabilir.
                    if ($publishPriced && $p->status === 'draft' && ($p->page_count || $p->price_bw !== null || $p->price_color !== null)) {
                        $p->status = 'active';
                        $published++;
                    }
                    if ($p->isDirty()) {
                        $p->save();
                        $updated++;
                    } else {
                        $unchanged++;
                    }
                }
            });
        }
        Activity::log('products.prices_imported', null, ['updated' => $updated, 'published' => $published, 'errors' => count($errors)]);

        return ['updated' => $updated, 'unchanged' => $unchanged, 'published' => $published, 'errors' => $errors];
    }
}
