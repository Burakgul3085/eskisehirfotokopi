<?php

namespace App\Providers;

use App\Domain\Import\Ocr\OcrEngine;
use App\Domain\Import\Ocr\TesseractEngine;
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

        // Yönetimden girilen SMTP ayarları, e-posta yöneticisi ilk kullanıldığında uygulanır (testlerde uygulanmaz).
        $this->app->beforeResolving('mail.manager', function () {
            if ($this->app->environment('testing')) {
                return;
            }
            try {
                $host = Settings::get('mail_host');
            } catch (Throwable) {
                return; // kurulum sırasında ayarlar tablosu henüz yoksa
            }
            if (! $host) {
                return;
            }
            $encryption = Settings::get('mail_encryption');
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $host,
                'mail.mailers.smtp.port' => (int) Settings::get('mail_port', '587'),
                'mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
                'mail.mailers.smtp.username' => Settings::get('mail_username'),
                'mail.mailers.smtp.password' => Settings::secret('mail_password'),
                'mail.from.address' => Settings::get('mail_from_address') ?? Settings::get('mail_username'),
                'mail.from.name' => Settings::get('mail_from_name') ?? Settings::siteName(),
            ]);
        });
    }

    public function boot(): void
    {
        Paginator::defaultView('components.pagination');

        RateLimiter::for('admin-login', fn (Request $r) => [Limit::perMinute(10)->by('ip:'.$r->ip()), Limit::perHour(60)->by('ip:'.$r->ip())]);
        RateLimiter::for('customer-login', fn (Request $r) => [Limit::perMinute(10)->by('ip:'.$r->ip()), Limit::perMinute(5)->by('email:'.mb_strtolower((string) $r->input('email')))]);
        RateLimiter::for('import-upload', fn (Request $r) => Limit::perMinute(1200)->by('admin:'.($r->user('admin')?->id ?? $r->ip())));
        RateLimiter::for('customer-register', fn (Request $r) => Limit::perHour(10)->by('ip:'.$r->ip()));
        RateLimiter::for('password-email', fn (Request $r) => [Limit::perMinute(3)->by('ip:'.$r->ip()), Limit::perHour(5)->by('email:'.mb_strtolower((string) $r->input('email')))]);

        View::composer(['components.layouts.*', 'store.*', 'admin.*', 'auth.*', 'account.*'], function ($view) {
            $view->with('settings', Settings::all());
        });
    }
}
