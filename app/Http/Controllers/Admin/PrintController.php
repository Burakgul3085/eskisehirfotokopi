<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\BookPricing;
use App\Domain\Print\PrintOptions;
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

    public function update(Request $request, PrintSettings $settings, BookPricing $books): RedirectResponse
    {
        $settings->save($request->all());
        // Kitap fiyatları aynı kademelerden hesaplanır: sayfa sayısı girilmiş tüm ürünler güncellenir.
        $n = $books->refreshAll();

        return redirect()->route('admin.print.edit')->with('status', 'Fotokopi ayarları kaydedildi.'.($n ? " {$n} ürünün fiyatı güncellendi." : ''));
    }

    /** Siyah-beyaz / renkli baskıyı aç-kapat (yazıcı arızasında hızlı geçiş). */
    public function toggleColor(Request $request, PrintSettings $settings, string $color): RedirectResponse
    {
        abort_unless(in_array($color, ['bw', 'color'], true), 404);
        $on = $settings->toggleColor($color);
        $label = PrintOptions::COLORS[$color];

        return back()->with('status', $label.' baskı '.($on ? 'açıldı; müşteriler yeniden seçebilir.' : 'kapatıldı; vitrinde gösterilmiyor ve sepetteki bu baskılar çıkarılır.'));
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
