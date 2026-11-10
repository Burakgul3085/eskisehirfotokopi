<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\CustomerAuthController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

/*
| Vitrin (müşteri tarafı). Yönetim rotaları routes/admin.php'dedir.
*/
Route::middleware('store.open')->group(function () {
    Route::get('/', HomeController::class)->name('home');

    Route::middleware('guest:web')->group(function () {
        Route::get('/giris', [CustomerAuthController::class, 'showLogin'])->name('login');
        Route::post('/giris', [CustomerAuthController::class, 'login'])->middleware('throttle:customer-login');
        Route::get('/uye-ol', [CustomerAuthController::class, 'showRegister'])->name('register');
        Route::post('/uye-ol', [CustomerAuthController::class, 'register'])->middleware('throttle:customer-register');
        Route::get('/sifremi-unuttum', [CustomerAuthController::class, 'showForgot'])->name('password.request');
        Route::post('/sifremi-unuttum', [CustomerAuthController::class, 'sendResetLink'])->middleware('throttle:password-email')->name('password.email');
        Route::get('/sifre-sifirla/{token}', [CustomerAuthController::class, 'showReset'])->name('password.reset');
        Route::post('/sifre-sifirla', [CustomerAuthController::class, 'reset'])->middleware('throttle:customer-login')->name('password.update');
    });

    Route::middleware('auth:web')->group(function () {
        Route::post('/cikis', [CustomerAuthController::class, 'logout'])->name('logout');
        Route::get('/hesabim', [AccountController::class, 'index'])->name('account.index');
        Route::put('/hesabim/bilgiler', [AccountController::class, 'updateProfile'])->name('account.profile');
        Route::put('/hesabim/parola', [AccountController::class, 'updatePassword'])->name('account.password');
    });
});
