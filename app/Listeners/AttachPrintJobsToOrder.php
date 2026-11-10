<?php

namespace App\Listeners;

use App\Domain\Print\PrintFiles;
use App\Events\OrderPlaced;
use App\Models\PrintJob;

/** Sipariş verildi: baskı işleri siparişe bağlanır, belgeler sipariş saklama süresi boyunca tutulur. */
class AttachPrintJobsToOrder
{
    public function __construct(private PrintFiles $files) {}

    public function handle(OrderPlaced $event): void
    {
        $ids = $event->order->items()->whereNotNull('print_job_id')->pluck('print_job_id');
        if ($ids->isEmpty()) {
            return;
        }
        foreach (PrintJob::with('file')->whereIn('id', $ids)->get() as $job) {
            $job->update(['order_id' => $event->order->id]);
            $this->files->keepForOrder($job->file);
        }
    }
}
