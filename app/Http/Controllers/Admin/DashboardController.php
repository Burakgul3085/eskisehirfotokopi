<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\MessageThread;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Gösterge paneli: bugünün siparişleri, açık siparişler, okunmamış mesajlar, son siparişler ve işlemler. */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $open = Order::whereNotIn('status', ['delivered', 'cancelled']);

        return view('admin.dashboard', [
            'todayOrders' => Order::where('created_at', '>=', today())->count(),
            'todayTotal' => (float) Order::where('created_at', '>=', today())->where('status', '!=', 'cancelled')->sum('total'),
            'openOrders' => (clone $open)->count(),
            'todayDeliveries' => (clone $open)->whereDate('delivery_date', today())->count(),
            'unreadMessages' => MessageThread::where('unread_admin', true)->count(),
            'activeProducts' => Product::where('status', 'active')->count(),
            // 3 dakikadan eski bekleyen iş: kuyruk işçisi çalışmıyor (belgeler işlenmez, e-postalar gitmez).
            'stuckJobs' => DB::table('jobs')->whereNull('reserved_at')->where('available_at', '<', now()->subMinutes(3)->getTimestamp())->count(),
            'failedJobs' => DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count(),
            'recentOrders' => Order::latest('id')->limit(8)->get(),
            'recentActivity' => ActivityLog::with('admin')->latest('created_at')->limit(8)->get(),
        ]);
    }
}
