# Eskişehir Fotokopi — geliştirme notları

- Plan ve kararlar: `docs/01-analiz-ve-plan.md` (§12 K1–K10). Aşama raporları `docs/NN-asama-*.md`. Yeni oturumda önce son raporu oku.
- İletişim ve belgeler Türkçe. Kod yorumları Türkçe.
- Laravel 13, PHP 8.5 (yerelde `C:\tools\php85`), MySQL 8.4 (`C:\tools\mysql84`, hizmet değil). Başlarken `mysqladmin -uroot ping`; kapalıysa:
  `Start-Process C:\tools\mysql84\bin\mysqld.exe -ArgumentList '--defaults-file=C:/tools/mysql84/my.ini' -WindowStyle Hidden`
- Veritabanları: `eskisehirfotokopi` (yerel), `eskisehirfotokopi_test` (testler; `phpunit.xml`). Kullanıcı `efoto`.
- Testler: `php artisan test`. Biçim: `vendor/bin/pint`. Ön yüz: `npm run build`.
- **Sitede sabit metin yok:** vitrin metinleri `App\Support\Settings` (veya ilgili yönetim modülü) üzerinden gelir.
- Ön yüz Alpine'in **CSP sürümü**: şablonda yalnızca `resources/js/components.js` bileşenlerinin özellik/metot adları; nesne literali, `!ifade`, satır içi `onclick` yok.
- Tailwind v4: başka sınıfların `@apply` edeceği temel sınıflar `@utility` ile tanımlanır (ör. `btn`).
- Yöneticiler ayrı tablo ve `admin` guard'ı (`/yonetim`). Müşteriler `web` guard'ı. İlk yönetici: `php artisan yonetici:olustur e-posta "Ad"`.
- Yerel geliştirme yöneticisi bilgisi: `storage/app/private/gelistirme-yonetici.txt` (git'e girmez).
- Dev sunucu: `.claude/launch.json` (port 8010) → `php artisan yerel:sunucu`: web sunucusu + kuyruk işçisi (print, import, default). İşçi yoksa fotokopi belgeleri "Sayfalar sayılıyor…"da kalır, e-posta gitmez.
