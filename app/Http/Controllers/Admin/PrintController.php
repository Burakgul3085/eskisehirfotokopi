<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Print\PrintSettings;
use App\Http\Controllers\Controller;
use App\Models\PrintJob;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Yönetim › Fotokopi: fiyat kademeleri ve sınırlar; siparişteki baskı dosyasını indirme. */
class PrintController extends Controller
{
    public function edit(PrintSettings $settings): View
    {
        return view('admin.print.settings', ['s' => $settings->all(), 'warnings' => $settings->warnings()]);
    }

    public function update(Request $request, PrintSettings $settings): RedirectResponse
    {
        $settings->save($request->all());

        return redirect()->route('admin.print.edit')->with('status', 'Fotokopi ayarları kaydedildi.');
    }

    /** Siparişe bağlı baskı işinin dosyası (çevrilmiş PDF veya özgün dosya). */
    public function download(PrintJob $job): StreamedResponse
    {
        $file = $job->file;
        abort_unless($job->order_id && $file && $file->status !== 'deleted' && Storage::disk('local')->exists($file->printablePath()), 404,
            'Dosya saklama süresi dolduğu için silinmiş.');
        Activity::log('print.download', $job->order, ['job' => $job->id]);

        return Storage::disk('local')->download($file->printablePath(), $file->downloadName());
    }
}
