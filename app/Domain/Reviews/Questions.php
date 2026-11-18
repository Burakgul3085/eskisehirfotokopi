<?php

namespace App\Domain\Reviews;

use App\Mail\SiteMail;
use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Support\Activity;
use Illuminate\Support\Facades\Mail;

/**
 * Ürün soruları: herkes sorabilir (satın alma ve üyelik gerekmez). Soran kişiye "ulaştı", yöneticilere "yeni soru"
 * e-postası gider. Cevap e-postayla soran kişiye iletilir; "yayınla" seçilirse ürün sayfasında da görünür.
 * Silinen (spam/uygunsuz) sorulara e-posta gitmez; cevap düzenlenince yalnız istenirse yeniden bildirilir.
 */
class Questions
{
    /** @param  array{name: string, email: string, body: string}  $data */
    public function ask(Product $product, array $data, ?int $userId, ?string $ip): ProductQuestion
    {
        $question = ProductQuestion::create([
            'product_id' => $product->id, 'user_id' => $userId, 'name' => $data['name'], 'email' => $data['email'],
            'body' => $data['body'], 'ip' => $ip,
        ]);
        $question->setRelation('product', $product);
        $vars = ['ad' => $question->name, 'urun' => $product->title];

        if ($mail = SiteMail::notice('question_received', $vars, $question)) {
            $mail->quote = $question->body;
            $mail->buttonUrl = route('product.show', $product);
            Mail::to($question->email, $question->name)->send($mail);
        }
        if (($admins = SiteMail::adminRecipients()) && ($mail = SiteMail::notice('question_admin', $vars, $question))) {
            $mail->quote = $question->body;
            $mail->facts = [['Ürün', $product->title], ['Soran', $question->name], ['E-posta', $question->email], ['Üyelik', $userId ? 'Üye' : 'Üye değil']];
            $mail->buttonUrl = route('admin.questions.show', $question);
            $mail->replyToAddress = $question->email;
            $mail->replyToName = $question->name;
            Mail::to($admins)->send($mail);
        }

        return $question;
    }

    /**
     * Cevap. İlk cevapta soran kişiye her zaman e-posta gider; sonraki düzenlemelerde yalnız $notify ile.
     */
    public function answer(ProductQuestion $question, string $answer, bool $public, ?Admin $admin, bool $notify = false): void
    {
        $first = ! $question->isAnswered();
        $question->update([
            'answer' => trim($answer), 'is_public' => $public, 'answered_by' => $admin?->id,
            'answered_at' => $question->answered_at ?? now(),
        ]);
        Activity::log($first ? 'question.answered' : 'question.updated', $question, ['public' => $public]);

        if ($first || $notify) {
            $question->loadMissing('product');
            if ($mail = SiteMail::notice('question_answered', ['ad' => $question->name, 'urun' => $question->product->title], $question)) {
                $mail->facts = [['Sorunuz', $question->body]];
                $mail->quote = $question->answer;
                $mail->buttonUrl = $public ? $question->url() : route('product.show', $question->product);
                Mail::to($question->email, $question->name)->send($mail);
            }
        }
    }

    /** Yayından kaldır / yayınla (yalnız cevaplanmış soru yayınlanabilir; e-posta gitmez). */
    public function setPublic(ProductQuestion $question, bool $public): void
    {
        $question->update(['is_public' => $public && $question->isAnswered()]);
        Activity::log('question.visibility', $question, ['public' => $question->is_public]);
    }
}
