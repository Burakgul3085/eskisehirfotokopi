<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Yerel geliştirme: web sunucusu ile kuyruk işçisini (print, import, default) birlikte çalıştırır.
 * Kuyruk işçisi olmadan fotokopi belgeleri "Sayfalar sayılıyor…" durumunda kalır, toplu yükleme ilerlemez ve
 * e-postalar gitmez. Sunucuda bunun yerine supervisor kullanılır (Aşama 9 kurulum notları).
 */
class LocalServer extends Command
{
    protected $signature = 'yerel:sunucu {--port=8010}';

    protected $description = 'Yerel web sunucusu + kuyruk işçisi (yalnız geliştirme)';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Bu komut yalnız yerel geliştirme içindir.');

            return self::FAILURE;
        }
        $php = (new PhpExecutableFinder)->find(false) ?: 'php';
        $artisan = base_path('artisan');

        $worker = new Process([$php, $artisan, 'queue:work', '--queue=print,import,default', '--sleep=1', '--tries=3'], base_path(), null, null, null);
        $server = new Process([$php, $artisan, 'serve', '--port='.(int) $this->option('port')], base_path(), null, null, null);
        $worker->start(fn ($type, $out) => $this->output->write('[kuyruk] '.$out));
        $server->start(fn ($type, $out) => $this->output->write($out));
        $this->info('Sunucu ve kuyruk işçisi başladı (Ctrl+C ile durur).');

        // İşçi çökerse (ör. kod değişikliğinden sonra hata) yeniden başlatılır; sunucu kapanınca işçi de durur.
        while ($server->isRunning()) {
            if (! $worker->isRunning()) {
                $this->warn('[kuyruk] işçi durdu, yeniden başlatılıyor…');
                $worker = $worker->restart(fn ($type, $out) => $this->output->write('[kuyruk] '.$out));
            }
            usleep(500_000);
        }
        $worker->stop(3);

        return $server->getExitCode() ?? self::SUCCESS;
    }
}
