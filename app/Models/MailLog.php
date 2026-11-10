<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Gönderilen / gönderilemeyen e-postaların günlüğü (Yönetim › E-posta günlüğü). */
class MailLog extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = [
        'order.placed' => 'Sipariş alındı (müşteri)',
        'order.admin' => 'Yeni sipariş (yönetici)',
        'order.status' => 'Sipariş durumu',
        'message.admin' => 'Yeni mesaj (yönetici)',
        'message.reply' => 'Mesaj yanıtı',
        'account.welcome' => 'Hoş geldiniz',
        'account.reset' => 'Parola sıfırlama',
        'test' => 'Deneme e-postası',
    ];

    protected $fillable = ['type', 'to', 'subject', 'status', 'error', 'subject_model_type', 'subject_model_id'];

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
