<?php

namespace App\Domain\Import;

use App\Jobs\ProcessImportItem;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use Illuminate\Support\Facades\DB;

/**
 * Kitap listesinden yükleme oturumu açar. Daha önce eklenmiş klasörler (aynı parmak izi, eklendi/kontrol) atlanır;
 * böylece yarıda kalan yükleme aynı kök klasör yeniden bırakılarak sürdürülür.
 */
class ImportBatchCreator
{
    /**
     * @param  list<array{publisher_folder: ?string, folder: string, info: ?string, mockup: ?string, cover?: ?string, imprint?: ?string}>  $books
     * @return array{batch: ImportBatch, items: array<int, int|null>} items: kitap sırası => kalem id (atlandıysa null)
     */
    public function create(string $name, array $books, ?int $adminId, string $source = 'upload'): array
    {
        $fingerprints = [];
        foreach ($books as $i => $b) {
            $fingerprints[$i] = ImportItem::fingerprint($b['publisher_folder'] ?? null, $b['folder']);
        }

        $done = [];
        foreach (array_chunk(array_unique($fingerprints), 1000) as $chunk) {
            ImportItem::whereIn('fingerprint', $chunk)->whereIn('status', ['imported', 'review'])->whereNotNull('product_id')
                ->pluck('fingerprint')->each(function ($f) use (&$done) {
                    $done[$f] = true;
                });
        }

        $batch = ImportBatch::create(['admin_id' => $adminId, 'name' => mb_substr($name, 0, 255), 'source' => $source, 'status' => 'uploading']);
        $ids = [];
        $seen = [];
        $now = now();

        DB::transaction(function () use ($books, $fingerprints, $done, $batch, $now, &$ids, &$seen) {
            foreach (array_chunk($books, 500, true) as $chunk) {
                $rows = [];
                foreach ($chunk as $i => $b) {
                    $fp = $fingerprints[$i];
                    if (isset($done[$fp]) || isset($seen[$fp])) {
                        $ids[$i] = null;

                        continue;
                    }
                    $seen[$fp] = $i;
                    $expected = array_filter(array_combine(ImportItem::ROLES, array_map(fn ($r) => $b[$r] ?? null, ImportItem::ROLES)));
                    $rows[] = [
                        'batch_id' => $batch->id,
                        'publisher_folder' => isset($b['publisher_folder']) ? mb_substr($b['publisher_folder'], 0, 255) : null,
                        'folder' => mb_substr($b['folder'], 0, 255),
                        'fingerprint' => $fp,
                        'status' => $expected ? 'waiting_files' : 'queued',
                        'expected' => json_encode((object) $expected),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if ($rows) {
                    ImportItem::insert($rows);
                }
            }
            // Eklenen kalemlerin id'leri parmak izinden bulunur.
            foreach (array_chunk(array_keys($seen), 1000) as $chunk) {
                ImportItem::where('batch_id', $batch->id)->whereIn('fingerprint', $chunk)->pluck('id', 'fingerprint')
                    ->each(function ($id, $fp) use (&$ids, $seen) {
                        $ids[$seen[$fp]] = $id;
                    });
            }
        });

        // Önceki yarım kalmış oturumlardaki aynı kitaplar bu oturuma devredildi.
        if ($seen) {
            foreach (array_chunk(array_keys($seen), 1000) as $chunk) {
                ImportItem::whereIn('fingerprint', $chunk)->where('batch_id', '!=', $batch->id)
                    ->whereIn('status', ['waiting_files', 'failed'])->update(['status' => 'skipped']);
            }
        }

        $batch->update(['total_items' => count($seen)]);
        ImportItem::where('batch_id', $batch->id)->where('status', 'queued')->pluck('id')
            ->each(fn ($id) => ProcessImportItem::dispatch($id));
        $batch->refreshStatus();
        ksort($ids);

        return ['batch' => $batch, 'items' => $ids];
    }
}
