<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MailLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Yönetim › E-posta kayıtları: gönderilen ve gönderilemeyen e-postalar (SMTP sorunlarını görmek için). */
class MailLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $logs = MailLog::query()
            ->when(isset(MailLog::TYPES[$request->query('tur')]), fn ($q) => $q->where('type', $request->query('tur')))
            ->when($request->query('durum') === 'hata', fn ($q) => $q->where('status', 'failed'))
            ->when(filled($request->query('q')), fn ($q) => $q->where('to', 'like', '%'.trim($request->query('q')).'%'))
            ->latest('id')->paginate(50)->withQueryString();

        return view('admin.mail-logs', ['logs' => $logs, 'failed' => MailLog::where('status', 'failed')->where('created_at', '>=', now()->subDay())->count()]);
    }
}
