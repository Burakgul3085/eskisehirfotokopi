<?php

namespace App\Jobs;

use App\Domain\Import\ImportItemProcessor;
use App\Models\ImportItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Tek kitap klasörünü işler ("import" kuyruğu; sunucuda 2–4 işçi paralel çalışır). */
class ProcessImportItem implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(public int $itemId)
    {
        $this->onQueue(config('catalog.import_queue'));
    }

    public function handle(ImportItemProcessor $processor): void
    {
        $item = ImportItem::find($this->itemId);
        if ($item && in_array($item->status, ['queued', 'processing', 'failed'], true)) {
            $processor->process($item);
        }
    }
}
