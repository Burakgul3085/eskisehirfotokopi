<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\View\View;

/** Gösterge paneli. Sipariş ve ürün özetleri ilgili aşamalarda eklenir. */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'customerCount' => User::count(),
            'newCustomers' => User::where('created_at', '>=', now()->subDays(7))->count(),
            'recentActivity' => ActivityLog::with('admin')->latest('created_at')->limit(8)->get(),
        ]);
    }
}
