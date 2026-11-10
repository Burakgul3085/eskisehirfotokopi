<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fotokopi için yüklenen belge. Dosyalar herkese açık değildir (local disk, print/{token}); yalnız sahibi (aynı oturum)
 * ve yönetim erişir. Süresi dolan dosya silinir, kayıt "deleted" olarak kalır (App\Domain\Print\PrintFiles).
 */
class PrintFile extends Model
{
    public const STATUSES = ['uploading' => 'Yükleniyor', 'processing' => 'Hazırlanıyor', 'ready' => 'Hazır', 'failed' => 'Hata', 'deleted' => 'Silindi'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['size' => 'integer', 'received' => 'integer', 'pages' => 'integer', 'expires_at' => 'datetime'];
    }

    public function isImage(): bool
    {
        return in_array($this->extension, ['jpg', 'jpeg', 'png'], true);
    }

    public function isReady(): bool
    {
        return $this->status === 'ready' && $this->pages > 0;
    }

    /** Baskıya gidecek dosya: çevrilmiş PDF, yoksa (PDF ve görselde) özgün dosya. */
    public function printablePath(): string
    {
        return $this->pdf_path ?? $this->path;
    }

    /** İndirme adı: özgün ad, Word vb. çevrildiyse .pdf uzantısıyla. */
    public function downloadName(): string
    {
        return $this->pdf_path ? pathinfo($this->original_name, PATHINFO_FILENAME).'.pdf' : $this->original_name;
    }
}
