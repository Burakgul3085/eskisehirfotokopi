<?php

namespace App\Http\Middleware;

use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Site yönetimden kapatıldıysa ziyaretçilere bakım sayfası gösterilir; giriş yapmış yönetici siteyi görmeye devam eder. */
class EnsureStoreOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Settings::flag('store_open') && ! Auth::guard('admin')->check()) {
            return response()->view('store.closed', [], 503)->header('Retry-After', '3600');
        }

        return $next($request);
    }
}
