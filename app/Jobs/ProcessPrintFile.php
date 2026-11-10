<?php

namespace App\Jobs;

use App\Domain\Print\PrintFiles;
use App\Models\PrintFile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Yüklenen fotokopi belgesini hazırlar: PDF'e çevirme, sayfa sayımı, önizleme (PrintFiles::process). Kuyruk: print. */
class ProcessPrintFile implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 330;

    public function __construct(public int $fileId)
    {
        $this->onQueue('print'); // ayrı işçi: uzun çevirmeler e-postaları ve OCR'ı bekletmez
    }

    public function handle(PrintFiles $files): void
    {
        if ($file = PrintFile::find($this->fileId)) {
            $files->process($file);
        }
    }
}
