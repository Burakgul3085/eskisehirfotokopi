<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $fillable = ['admin_id', 'name', 'source', 'status', 'total_items'];

    public function items(): HasMany
    {
        return $this->hasMany(ImportItem::class, 'batch_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /** Duruma göre kalem sayıları (tüm durumlar, 0 dahil). */
    public function counts(): array
    {
        $raw = $this->items()->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status')->all();
        $out = array_fill_keys(array_keys(ImportItem::STATUSES), 0);
        foreach ($raw as $k => $v) {
            $out[$k] = (int) $v;
        }

        return $out;
    }

    /** Bekleyen iş kalmadıysa "done" yapar. */
    public function refreshStatus(): void
    {
        $open = $this->items()->whereIn('status', ['waiting_files', 'queued', 'processing'])->exists();
        $status = $open ? ($this->items()->where('status', 'waiting_files')->exists() ? 'uploading' : 'processing') : 'done';
        if ($status !== $this->status) {
            $this->update(['status' => $status]);
        }
    }
}
