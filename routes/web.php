<?php

use App\Http\Controllers\Account\AddressController;
use App\Http\Controllers\Account\FavoriteController;
use App\Http\Controllers\Account\MessageController as AccountMessageController;
use App\Http\Controllers\Account\OrderController as AccountOrderController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\CustomerAuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Store\CartController;
use App\Http\Controllers\Store\CatalogController;
use App\Http\Controllers\Store\CheckoutController;
use App\Http\Controllers\Store\ContactController;
use App\Http\Controllers\Store\OrderTrackingController;
use App\Http\Controllers\Store\PrintController;
use App\Http\Controllers\Store\ProductController;
use Illuminate\Support\Facades\Route;

/*
| Vitrin (müşteri tarafı). Yönetim rotaları routes/admin.php'dedir.
*/
Route::middleware('store.open')->group(function () {
    Route::get('/', HomeController::class)->name('home');

    // Katalog
    Route::get('/kategori/{category:slug}', [CatalogController::class, 'category'])->name('category.show');
    Route::get('/yayinevleri', [CatalogController::class, 'publishers'])->name('publishers.index');
    Route::get('/yayinevi/{publisher:slug}', [CatalogController::class, 'publisher'])->name('publisher.show');
    Route::get('/urun/{product:slug}', [ProductController::class, 'show'])->name('product.show');
    Route::get('/urunler', [CatalogController::class, 'search'])->name('products.index');
    Route::get('/ara', [CatalogController::class, 'search'])->name('search');
    Route::get('/ara/oneri', [CatalogController::class, 'suggest'])->middleware('throttle:search')->name('search.suggest');

    // Fotokopi: belge yükle, fiyatı gör, sepete ekle (K1)
    Route::get('/fotokopi', [PrintController::class, 'show'])->name('print.index');
    Route::post('/fotokopi/belge', [PrintController::class, 'start'])->middleware('throttle:print-start')->name('print.start');
    Route::prefix('/fotokopi/belge/{token}')->middleware('throttle:print-api')->controller(PrintController::class)->group(function () {
        Route::get('/', 'status')->name('print.status');
        Route::delete('/', 'destroy')->name('print.destroy');
        Route::post('/parca', 'chunk')->name('print.chunk');
        Route::get('/onizleme', 'preview')->name('print.preview');
        Route::post('/fiyat', 'quote')->name('print.quote');
        Route::post('/sepet', 'add')->name('print.add');
    });

    // Sepet ve sipariş (misafir de sipariş verebilir — K8)
    Route::get('/sepet', [CartController::class, 'index'])->name('cart.index');
    Route::post('/sepet', [CartController::class, 'add'])->name('cart.add');
    Route::patch('/sepet', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/sepet', [CartController::class, 'remove'])->name('cart.remove');
    Route::get('/siparis', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/siparis', [CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout.store');
    Route::get('/siparis/tamamlandi/{order}', [CheckoutController::class, 'done'])->name('checkout.done');
    Route::get('/siparis-takip', [OrderTrackingController::class, 'form'])->name('orders.track');
    Route::post('/siparis-takip', [OrderTrackingController::class, 'lookup'])->middleware('throttle:order-track');
    Route::get('/siparis/{order}/{token}', [OrderTrackingController::class, 'show'])->name('orders.track.show');

    // İletişim ve mesajlar (konuya e-postadaki gizli bağlantıyla dönülür)
    Route::get('/iletisim', [ContactController::class, 'show'])->name('contact');
    Route::post('/iletisim', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');
    Route::get('/mesaj/{thread}/{token}', [ContactController::class, 'thread'])->name('messages.show');
    Route::post('/mesaj/{thread}/{token}', [ContactController::class, 'reply'])->middleware('throttle:contact')->name('messages.reply');

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
        Route::get('/hesabim/siparisler', [AccountOrderController::class, 'index'])->name('account.orders');
        Route::get('/hesabim/siparisler/{order}', [AccountOrderController::class, 'show'])->name('account.orders.show');
        Route::post('/hesabim/siparisler/{order}/tekrar', [AccountOrderController::class, 'reorder'])->name('account.orders.reorder');
        Route::resource('/hesabim/adresler', AddressController::class)->parameters(['adresler' => 'address'])->except('show')->names('account.addresses');
        Route::get('/hesabim/mesajlar', AccountMessageController::class)->name('account.messages');
        Route::get('/hesabim/favoriler', [FavoriteController::class, 'index'])->name('account.favorites');
        Route::post('/favori/{product}', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    });
});
