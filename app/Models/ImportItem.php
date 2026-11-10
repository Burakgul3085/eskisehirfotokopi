<?php

namespace App\Models;

use App\Support\Text;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportItem extends Model
{
    public const STATUSES = [
        'waiting_files' => 'Dosya bekleniyor',
        'queued' => 'Sırada',
        'processing' => 'İşleniyor',
        'imported' => 'Eklendi',
        'review' => 'Kontrol gerekli',
        'failed' => 'Hata',
        'skipped' => 'Atlandı',
    ];

    protected $fillable = ['batch_id', 'publisher_folder', 'folder', 'fingerprint', 'status', 'expected', 'info_path', 'mockup_path',
        'ocr_text', 'parsed', 'issues', 'product_id', 'action', 'error', 'duration_ms', 'reviewed_at'];

    protected function casts(): array
    {
        return ['expected' => 'array', 'parsed' => 'array', 'issues' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'batch_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Aynı kitap klasörü her yüklemede aynı parmak izini verir (yeniden bırakılan klasör kaldığı yerden sürer). */
    public static function fingerprint(?string $publisherFolder, string $folder): string
    {
        return sha1(Text::key((string) $publisherFolder).'|'.Text::key($folder));
    }

    /** Kalemin dosyaları (local disk) için klasör. */
    public function storageDir(): string
    {
        return "imports/{$this->batch_id}/{$this->id}";
    }

    /** Beklenen dosyalar geldiyse true. */
    public function hasAllFiles(): bool
    {
        $e = $this->expected ?? [];

        return (empty($e['info']) || $this->info_path) && (empty($e['mockup']) || $this->mockup_path);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Rozet sınıfları */
    public static function tone(string $status): string
    {
        return match ($status) {
            'imported' => 'bg-success-soft text-success',
            'review' => 'bg-warning-soft text-warning',
            'failed' => 'bg-danger-soft text-danger',
            'processing', 'queued' => 'bg-info-soft text-info',
            default => 'bg-slate-100 text-muted',
        };
    }
}
