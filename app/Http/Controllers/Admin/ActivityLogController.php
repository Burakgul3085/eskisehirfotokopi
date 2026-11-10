<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.activity', ['logs' => ActivityLog::with('admin')->latest('created_at')->paginate(50)]);
    }
}
