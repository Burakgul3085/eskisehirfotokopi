<?php

namespace App\Domain\Reviews;

/**
 * Değerlendirme ve soru e-postalarının şablon listesi. Her şablonun açık/kapalı durumu, konusu, başlığı, metni ve buton
 * yazısı ayarlarda "mailtpl_<anahtar>_<alan>" anahtarlarıyla tutulur (Ayarlar › Değerlendirme ve sorular).
 * Metinlerde :ad, :urun, :puan, :siparis ve :site yer tutucuları kullanılabilir.
 */
final class NoticeTemplates
{
    public const FIELDS = ['on', 'subject', 'heading', 'body', 'button'];

    /** anahtar => [yönetimdeki ad, kime, e-posta günlüğü türü] */
    public const LIST = [
        'question_received' => ['Soru alındı', 'Soran kişiye', 'question.received'],
        'question_admin' => ['Yeni soru', 'Yöneticilere', 'question.admin'],
        'question_answered' => ['Soru cevaplandı', 'Soran kişiye', 'question.answered'],
        'review_received' => ['Değerlendirme alındı', 'Müşteriye', 'review.received'],
        'review_admin' => ['Yeni değerlendirme', 'Yöneticilere', 'review.admin'],
        'review_edited_admin' => ['Değerlendirme düzenlendi', 'Yöneticilere', 'review.admin'],
        'review_published' => ['Değerlendirme yayınlandı', 'Müşteriye', 'review.published'],
        'review_published_reply' => ['Yayınlandı ve cevaplandı (tek e-posta)', 'Müşteriye', 'review.published'],
        'review_reply' => ['Değerlendirmeye cevap', 'Müşteriye', 'review.reply'],
        'review_rejected' => ['Değerlendirme yayınlanamadı', 'Müşteriye', 'review.rejected'],
        'review_invite' => ['Değerlendirme daveti (sipariş teslim edilince)', 'Müşteriye', 'review.invite'],
    ];

    public static function key(string $template, string $field): string
    {
        return "mailtpl_{$template}_{$field}";
    }
}
