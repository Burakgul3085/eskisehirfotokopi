# Eskişehir Fotokopi

Eskişehir içi kapıya teslim çalışan fotokopi/baskı merkezi web sitesi. Katalogdan kitap siparişi ve kendi belgesini bastırma hizmeti sunar. Ödeme kapıda (nakit/kart) alınır.

- Teknoloji: PHP 8.4+ (Laravel 13), MySQL 8.4, Tailwind v4, Alpine.js
- Plan ve kararlar: [docs/01-analiz-ve-plan.md](docs/01-analiz-ve-plan.md)
- Aşama raporları: [docs/](docs/)

## Kurulum (geliştirme)

```bash
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate   # .env içinde DB_* ayarlarını doldurun
php artisan migrate
php artisan storage:link
php artisan yonetici:olustur eposta@ornek.com "Ad Soyad"
php artisan serve
```

Yönetim paneli: `/yonetim`
