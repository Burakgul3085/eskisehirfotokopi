<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Yönetimden abonelere gönderilen toplu e-posta. Gönderim sonuçları mail_logs tablosunda (subject_model). */
class NewsletterCampaign extends Model
{
    protected $fillable = ['subject', 'heading', 'body', 'button_text', 'button_url', 'status', 'recipients_count', 'admin_id', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function logs(): MorphMany
    {
        return $this->morphMany(MailLog::class, 'subject_model');
    }

    /** @return list<string> boş satırlarla ayrılmış paragraflar */
    public function paragraphs(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R\s*\R/u', trim($this->body)))));
    }
}
