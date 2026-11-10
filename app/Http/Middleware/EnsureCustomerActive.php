<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Yönetimden kapatılan müşteri hesabı ("beni hatırla" çereziyle bile) her istekte oturumdan çıkarılır. */
class EnsureCustomerActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();
        if ($user && $user->is_active === false) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Hesabınız kapatılmış. Bilgi için bizimle iletişime geçin.']);
        }

        return $next($request);
    }
}
