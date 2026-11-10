<?php

use App\Http\Middleware\EnsureAdminActive;
use App\Http\Middleware\EnsureCustomerActive;
use App\Http\Middleware\EnsureStoreOpen;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [EnsureCustomerActive::class]);
        $middleware->alias([
            'admin.active' => EnsureAdminActive::class,
            'admin.super' => EnsureSuperAdmin::class,
            'store.open' => EnsureStoreOpen::class,
        ]);
        // Yönetim sayfalarına girişsiz gelen yönetim girişine, diğerleri müşteri girişine yönlenir.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('yonetim', 'yonetim/*') ? route('admin.login') : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->is('yonetim', 'yonetim/*') ? route('admin.dashboard') : route('account.index'));
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
