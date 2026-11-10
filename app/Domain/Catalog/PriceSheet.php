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
 * Excel fiyat şablonu (K2, K3). Dışa aktarma: seçilen ürünler satır satır, fiyat sütunları boş veya mevcut değerle.
 * Yönetim yalnızca fiyat sütunlarını doldurup dosyayı geri yükler; eşleştirme ID sütunuyla yapılır.
 *
 * Hücre kuralları: boş = değiştirme · "-" veya "sil" = fiyatı kaldır · sayı = yeni fiyat ("149,90", "149.9", "1.250").
 */
class PriceSheet
{
    public const HEADERS = ['ID', 'Ürün Kodu', 'ISBN', 'Kitap Adı', 'Yayınevi', 'Yazar', 'Sayfa', 'Durum',
        'Siyah-Beyaz Fiyat', 'Renkli Fiyat', 'S-B Eski Fiyat (üstü çizili)', 'Renkli Eski Fiyat (üstü çizili)'];

    /** Fiyat sütunları: başlık indeksi => ürün alanı */
    private const PRICE_COLUMNS = [8 => 'price_bw', 9 => 'price_color', 10 => 'compare_bw', 11 => 'compare_color'];

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
        $writer->addRow(new Row(array_map(fn ($h, $i) => Cell::fromValue($h, $i >= 8 ? $priceHead : $head), self::HEADERS, array_keys(self::HEADERS)), 32));

        $locked = (new Style)->withFontColor('475569');
        $price = (new Style)->withBackgroundColor('FFFBEB')->withFormat('0.00');
        $count = 0;
        $query->with(['publisher:id,name', 'author:id,name'])->orderBy('publisher_id')->orderBy('title')
            ->chunkById(1000, function ($products) use ($writer, $locked, $price, &$count) {
                foreach ($products as $p) {
                    $cells = [
                        Cell::fromValue($p->id, $locked),
                        Cell::fromValue((string) $p->code, $locked),
                        Cell::fromValue((string) $p->isbn, $locked),
                        Cell::fromValue($p->title, $locked),
                        Cell::fromValue((string) $p->publisher?->name, $locked),
                        Cell::fromValue((string) $p->author?->name, $locked),
                        Cell::fromValue($p->page_count, $locked),
                        Cell::fromValue(Product::STATUSES[$p->status] ?? $p->status, $locked),
                    ];
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
                    if ($publishPriced && $p->status === 'draft' && ($p->price_bw !== null || $p->price_color !== null)) {
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
