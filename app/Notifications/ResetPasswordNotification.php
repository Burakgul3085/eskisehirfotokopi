<?php

namespace App\Notifications;

use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Müşteri parola sıfırlama bağlantısı. (Aşama 7'de marka e-posta şablonuna taşınacak.) */
class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(#[\SensitiveParameter] public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
        $minutes = config('auth.passwords.users.expire');

        return (new MailMessage)
            ->subject('Parola sıfırlama — '.Settings::siteName())
            ->greeting('Merhaba '.$notifiable->name.',')
            ->line('Hesabınız için parola sıfırlama isteği aldık.')
            ->action('Parolamı sıfırla', $url)
            ->line("Bağlantı {$minutes} dakika geçerlidir.")
            ->line('Bu isteği siz yapmadıysanız bu e-postayı yok sayabilirsiniz; parolanız değişmez.')
            ->salutation(Settings::siteName());
    }
}
