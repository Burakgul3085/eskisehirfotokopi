<?php

namespace App\Providers;

use App\Domain\Cart\Cart;
use App\Domain\Import\Ocr\OcrEngine;
use App\Domain\Import\Ocr\TesseractEngine;
use App\Domain\Print\PrintCart;
use App\Domain\Print\PrintOwner;
use App\Support\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // OCR motoru: yerel Tesseract (K10, ücretli API yok). Testlerde sahte motor bağlanır.
        $this->app->singleton(OcrEngine::class, TesseractEngine::class);

        // Sepet ve belge sahibi oturuma bağlıdır; her çözümlemede yeni nesne (durum oturumda tutulur).
        $this->app->bind(Cart::class, fn ($app) => new Cart($app['session.store']));
        $this->app->bind(PrintOwner::class, fn ($app) => new PrintOwner($app['session.store']));

        // Yönetimden girilen SMTP ayarları, e-posta yöneticisi ilk kullanıldığında uygulanır (testlerde uygulanmaz).
        $this->app->beforeResolving('mail.manager', function () {
            if ($this->app->environment('testing')) {
                return;
            }
            try {
                $address = Settings::get('mail_username');
            } catch (Throwable) {
                return; // kurulum sırasında ayarlar tablosu henüz yoksa
            }
            if (! $address || ! filter_var($address, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            // Sunucu, port ve şifreleme adresten belirlenir; gönderen adres ve ad da buradan gelir.
            $server = Settings::mailServer($address);
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $server['host'],
                'mail.mailers.smtp.port' => $server['port'],
                'mail.mailers.smtp.scheme' => $server['scheme'],
                'mail.mailers.smtp.username' => $address,
                'mail.mailers.smtp.password' => Settings::secret('mail_password'),
                'mail.from.address' => $address,
                'mail.from.name' => Settings::get('mail_from_name') ?: Settings::siteName(),
            ]);
        });
    }

    public function boot(): void
    {
        Paginator::defaultView('components.pagination');

        // Fotokopi işleri sepete "print" satırı olarak girer; fiyat her gösterimde yeniden hesaplanır.
        Cart::resolver('print', fn (array $item, string $key) => app(PrintCart::class)->line($item, $key, app(PrintOwner::class)->find()));

        RateLimiter::for('admin-login', fn (Request $r) => [Limit::perMinute(10)->by('ip:'.$r->ip()), Limit::perHour(60)->by('ip:'.$r->ip())]);
        RateLimiter::for('customer-login', fn (Request $r) => [Limit::perMinute(10)->by('ip:'.$r->ip()), Limit::perMinute(5)->by('email:'.mb_strtolower((string) $r->input('email')))]);
        RateLimiter::for('search', fn (Request $r) => Limit::perMinute(120)->by('ip:'.$r->ip()));
        RateLimiter::for('import-upload', fn (Request $r) => Limit::perMinute(1200)->by('admin:'.($r->user('admin')?->id ?? $r->ip())));
        RateLimiter::for('checkout', fn (Request $r) => [Limit::perMinute(6)->by('ip:'.$r->ip()), Limit::perHour(30)->by('ip:'.$r->ip())]);
        RateLimiter::for('contact', fn (Request $r) => [Limit::perMinute(3)->by('ip:'.$r->ip()), Limit::perHour(20)->by('ip:'.$r->ip())]);
        RateLimiter::for('print-start', fn (Request $r) => [Limit::perMinute(30)->by('ip:'.$r->ip()), Limit::perHour(200)->by('ip:'.$r->ip())]);
        RateLimiter::for('print-api', fn (Request $r) => Limit::perMinute(600)->by('ip:'.$r->ip()));
        RateLimiter::for('order-track', fn (Request $r) => Limit::perMinute(10)->by('ip:'.$r->ip()));
        RateLimiter::for('customer-register', fn (Request $r) => Limit::perHour(10)->by('ip:'.$r->ip()));
        RateLimiter::for('password-email', fn (Request $r) => [Limit::perMinute(3)->by('ip:'.$r->ip()), Limit::perHour(5)->by('email:'.mb_strtolower((string) $r->input('email')))]);

        View::composer(['components.layouts.*', 'components.account-layout', 'errors.*', 'errors::*', 'store.*', 'admin.*', 'auth.*', 'account.*'], function ($view) {
            $view->with('settings', Settings::all());
        });
    }
}
