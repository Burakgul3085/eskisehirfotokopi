<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Pasifleştirilen yöneticinin açık oturumu bir sonraki istekte kapatılır. */
class EnsureAdminActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();
        if ($admin && ! $admin->is_active) {
            Auth::guard('admin')->logout();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->withErrors(['email' => 'Bu yönetici hesabı pasif.']);
        }

        return $next($request);
    }
}
