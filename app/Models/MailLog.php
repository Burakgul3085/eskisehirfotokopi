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
        'question.received' => 'Soru alındı (müşteri)',
        'question.admin' => 'Yeni soru (yönetici)',
        'question.answered' => 'Soru cevaplandı',
        'review.received' => 'Değerlendirme alındı (müşteri)',
        'review.admin' => 'Yeni değerlendirme (yönetici)',
        'review.published' => 'Değerlendirme yayınlandı',
        'review.reply' => 'Değerlendirme cevabı',
        'review.rejected' => 'Değerlendirme yayınlanamadı',
        'review.invite' => 'Değerlendirme daveti',
        'newsletter' => 'E-bülten',
        'test' => 'Deneme e-postası',
    ];

    protected $fillable = ['type', 'to', 'subject', 'status', 'error', 'subject_model_type', 'subject_model_id'];

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
