<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\AuthorController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PublisherController;
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

        // Katalog
        Route::get('/urunler/fiyat-sablonu', [ProductController::class, 'priceTemplate'])->name('products.price-template');
        Route::post('/urunler/fiyat-yukle', [ProductController::class, 'priceImport'])->name('products.price-import');
        Route::post('/urunler/toplu', [ProductController::class, 'bulk'])->name('products.bulk');
        Route::resource('urunler', ProductController::class)->parameters(['urunler' => 'product'])->except('show')->names('products');
        Route::post('/kategoriler/{category}/sira', [CategoryController::class, 'move'])->name('categories.move');
        Route::resource('kategoriler', CategoryController::class)->parameters(['kategoriler' => 'category'])->except('show')->names('categories');
        Route::post('/yayinevleri/{publisher}/birlestir', [PublisherController::class, 'merge'])->name('publishers.merge');
        Route::resource('yayinevleri', PublisherController::class)->parameters(['yayinevleri' => 'publisher'])->except('show')->names('publishers');
        Route::get('/yazarlar', [AuthorController::class, 'index'])->name('authors.index');
        Route::put('/yazarlar/{author}', [AuthorController::class, 'update'])->name('authors.update');

        Route::get('/ayarlar/{group}', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/ayarlar/{group}', [SettingsController::class, 'update'])->name('settings.update');

        Route::middleware('admin.super')->group(function () {
            Route::resource('yoneticiler', AdminUserController::class)
                ->parameters(['yoneticiler' => 'admin'])->except('show')->names('admins');
            Route::get('/islem-gunlugu', ActivityLogController::class)->name('activity');
        });
    });
});
