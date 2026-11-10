<?php

use App\Domain\Import\BookFiles;
use App\Domain\Import\ImportBatchCreator;
use App\Domain\Import\ImportItemProcessor;
use App\Domain\Print\PrintFiles;
use App\Jobs\ProcessImportItem;
use App\Models\Admin;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

// İlk (veya yeni) süper yöneticiyi oluşturur. Parola sorulur, komut satırı geçmişine düşmez.
Artisan::command('yonetici:olustur {email} {name}', function (string $email, string $name) {
    $email = mb_strtolower(trim($email));
    $password = $this->secret('Parola (en az 10 karakter, harf ve rakam)');
    $v = Validator::make(['email' => $email, 'password' => $password], [
        'email' => ['required', 'email', 'unique:admins,email'],
        'password' => ['required', Password::min(10)->letters()->numbers()],
    ]);
    if ($v->fails()) {
        foreach ($v->errors()->all() as $e) {
            $this->error($e);
        }

        return 1;
    }
    Admin::create(['name' => $name, 'email' => $email, 'password' => $password, 'role' => 'super', 'is_active' => true]);
    $this->info("Süper yönetici oluşturuldu: {$email}");

    return 0;
})->purpose('Süper yönetici hesabı oluşturur');

/*
| Sunucudaki bir klasörden toplu yükleme (SFTP ile kopyalanmış 10.000 kitaplık arşiv için tarayıcıdan daha hızlı).
| Düzen ve kurallar tarayıcıdaki yükleyiciyle aynıdır (App\Domain\Import\BookFiles). --hemen: kuyruk yerine burada işler.
*/
Artisan::command('katalog:klasor-yukle {yol} {--hemen : Kuyruğa atmadan bu komutta işle}', function (string $yol) {
    $root = rtrim(realpath($yol) ?: '', DIRECTORY_SEPARATOR);
    if ($root === '' || ! is_dir($root)) {
        $this->error('Klasör bulunamadı: '.$yol);

        return 1;
    }
    $books = [];
    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $dir) {
        if (! $dir->isDir() || str_starts_with($dir->getFilename(), '.')) {
            continue;
        }
        $names = array_values(array_filter(scandir($dir->getPathname()), fn ($n) => is_file($dir->getPathname().DIRECTORY_SEPARATOR.$n)));
        $roles = BookFiles::classify($names);
        if (! $roles['info'] && ! $roles['mockup']) {
            continue;
        }
        $parent = dirname($dir->getPathname());
        $books[] = [
            'publisher_folder' => basename($parent), // kitabın üst klasörü yayınevi klasörüdür
            'folder' => $dir->getFilename(),
            'info' => $roles['info'],
            'mockup' => $roles['mockup'],
        ];
        $files[] = $dir->getPathname();
    }
    if (! $books) {
        $this->warn('Kitap klasörü bulunamadı.');

        return 0;
    }
    $this->info(count($books).' kitap klasörü bulundu.');

    // Dosyası olan kalemler "dosya bekleniyor" açılır; dosyalar kopyalandıkça aşağıda sıraya alınır.
    $result = app(ImportBatchCreator::class)->create(basename($root), $books, null, 'server');

    $bar = $this->output->createProgressBar(count($books));
    $processor = app(ImportItemProcessor::class);
    foreach ($result['items'] as $i => $id) {
        $bar->advance();
        if ($id === null) {
            continue;
        }
        $item = ImportItem::find($id);
        $updates = ['status' => 'queued'];
        foreach (['info', 'mockup'] as $role) {
            if ($name = $books[$i][$role]) {
                $target = $item->storageDir().'/'.$role.'.'.strtolower(pathinfo($name, PATHINFO_EXTENSION));
                Storage::disk('local')->put($target, file_get_contents($files[$i].DIRECTORY_SEPARATOR.$name));
                $updates[$role.'_path'] = $target;
            }
        }
        $item->update($updates);
        $this->option('hemen') ? $processor->process($item) : ProcessImportItem::dispatch($id);
    }
    $bar->finish();
    $this->newLine();
    $result['batch']->refreshStatus();
    $this->info('Yükleme #'.$result['batch']->id.' oluşturuldu. '.collect($result['items'])->filter(fn ($v) => $v === null)->count().' kitap daha önce eklendiği için atlandı.');

    return 0;
})->purpose('Sunucudaki kök klasörden kitapları toplu yükler');

// Eski yükleme dosyalarını temizler: bilgi görselleri catalog.import_keep_days gün saklanır.
Artisan::command('katalog:yukleme-temizle', function () {
    $days = config('catalog.import_keep_days');
    $old = ImportBatch::where('created_at', '<', now()->subDays($days))->get();
    foreach ($old as $batch) {
        Storage::disk('local')->deleteDirectory("imports/{$batch->id}");
        $batch->items()->update(['info_path' => null, 'mockup_path' => null]);
    }
    $this->info($old->count().' yüklemenin dosyaları silindi.');
})->purpose('Eski toplu yükleme görsellerini siler');

Schedule::command('katalog:yukleme-temizle')->dailyAt('04:10');

// Süresi dolan fotokopi belgelerini siler (siparişe girmeyenler kısa, siparişe girenler daha uzun saklanır).
Artisan::command('fotokopi:temizle', function (PrintFiles $files) {
    $this->info($files->prune().' belge silindi.');
})->purpose('Süresi dolan fotokopi belgelerini siler');

Schedule::command('fotokopi:temizle')->dailyAt('04:20');
