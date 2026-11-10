<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SettingsController;
use Illuminate\Support\Facades\Route;

/*
| Yönetim paneli: /yonetim. Ayrı oturum koruması (admin guard).
*/
Route::prefix('yonetim')->name('admin.')->group(function () {
    Route::get('/giris', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/giris', [AuthController::class, 'login'])->middleware('throttle:admin-login');

    Route::middleware(['auth:admin', 'admin.active'])->group(function () {
        Route::post('/cikis', [AuthController::class, 'logout'])->name('logout');
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('/ayarlar/{group}', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/ayarlar/{group}', [SettingsController::class, 'update'])->name('settings.update');

        Route::middleware('admin.super')->group(function () {
            Route::resource('yoneticiler', AdminUserController::class)
                ->parameters(['yoneticiler' => 'admin'])->except('show')->names('admins');
            Route::get('/islem-gunlugu', ActivityLogController::class)->name('activity');
        });
    });
});
