<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user('admin')?->isSuper(), 403, 'Bu sayfa yalnızca süper yöneticiler içindir.');

        return $next($request);
    }
}
