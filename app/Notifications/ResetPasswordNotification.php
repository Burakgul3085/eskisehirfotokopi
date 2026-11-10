<?php

namespace App\Notifications;

use App\Mail\SiteMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Müşteri parola sıfırlama bağlantısı; sitenin marka e-posta şablonuyla gider. */
class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(#[\SensitiveParameter] public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): SiteMail
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);

        return SiteMail::passwordReset($notifiable, $url, (int) config('auth.passwords.users.expire'))->to($notifiable->email, $notifiable->name);
    }
}
