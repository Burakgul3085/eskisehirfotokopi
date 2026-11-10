<?php

namespace App\Mail;

use App\Domain\Delivery\DeliveryRules;
use App\Models\MailLog;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Rules\TurkishPhone;
use App\Support\Money;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Sitenin tek, marka renkli e-posta şablonu (§7). Tüm e-postalar bu sınıfın fabrika metotlarıyla üretilir; görünüm
 * resources/views/emails/site.blade.php. Logo, renk ve alt bilgi yönetimden gelir. Kuyruktan gider; her gönderim
 * (başarılı ya da hatalı) mail_logs tablosuna yazılır.
 */
class SiteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    /** @var list<string> paragraflar */
    public array $lines = [];

    /** @var list<array{0: string, 1: string}> etiket => değer tablosu */
    public array $facts = [];

    public ?string $buttonText = null;

    public ?string $buttonUrl = null;

    public ?string $quote = null;

    public ?Order $order = null;

    public bool $showProgress = false;

    public ?string $replyToAddress = null;

    public ?string $replyToName = null;

    public ?Model $about = null;

    public function __construct(public string $type, public string $subjectLine, public string $heading) {}

    // ---- Fabrika metotları ---------------------------------------------------------------------------------------

    /** Müşteriye: siparişiniz alındı (özet + takip bağlantısı). */
    public static function orderPlaced(Order $order): self
    {
        $mail = new self('order.placed', 'Siparişiniz alındı — '.$order->number, 'Siparişiniz alındı');
        $mail->order = $order;
        $mail->about = $order;
        $mail->showProgress = true;
        $mail->lines = ['Merhaba '.$order->customer_name.', siparişiniz bize ulaştı. Hazırlanıp yola çıktığında size yine haber vereceğiz.'];
        $mail->facts = self::orderFacts($order);
        $mail->buttonText = 'Siparişimi takip et';
        $mail->buttonUrl = $order->trackingUrl();

        return $mail;
    }

    /** Yöneticiye: yeni sipariş. */
    public static function orderAdmin(Order $order): self
    {
        $mail = new self('order.admin', 'Yeni sipariş: '.$order->number.' — '.Money::format($order->total), 'Yeni sipariş geldi');
        $mail->order = $order;
        $mail->about = $order;
        $mail->lines = [$order->customer_name.' ('.TurkishPhone::format($order->phone).') '.$order->district_name.' / '.$order->neighborhood.' adresine sipariş verdi.'];
        $mail->facts = self::orderFacts($order);
        if ($order->note) {
            $mail->quote = 'Müşteri notu: '.$order->note;
        }
        $mail->buttonText = 'Siparişi panelde aç';
        $mail->buttonUrl = route('admin.orders.show', $order);
        if ($order->email) {
            $mail->replyToAddress = $order->email;
            $mail->replyToName = $order->customer_name;
        }

        return $mail;
    }

    /** Müşteriye: sipariş durumu değişti. */
    public static function orderStatus(Order $order, OrderStatusHistory $entry): self
    {
        $label = Order::STATUSES[$entry->status] ?? $entry->status;
        $mail = new self('order.status', 'Siparişiniz: '.$label.' — '.$order->number, $label);
        $mail->order = $order;
        $mail->about = $order;
        $mail->showProgress = $entry->status !== 'cancelled';
        $mail->lines = [match ($entry->status) {
            'confirmed' => 'Siparişiniz onaylandı, hazırlık sırasına alındı.',
            'preparing' => 'Siparişiniz hazırlanıyor.',
            'shipping' => 'Siparişiniz yola çıktı. Kurye adresinize geldiğinde ödemeyi '.mb_strtolower($order->paymentLabel()).' olarak yapabilirsiniz.',
            'delivered' => 'Siparişiniz teslim edildi. Bizi tercih ettiğiniz için teşekkür ederiz.',
            'cancelled' => 'Siparişiniz iptal edildi.',
            default => 'Siparişinizin durumu güncellendi: '.$label.'.',
        }];
        if ($entry->note) {
            $mail->quote = $entry->note;
        }
        $mail->facts = [['Sipariş', $order->number], ['Tutar', Money::format($order->total).' · '.$order->paymentLabel()]];
        $mail->buttonText = 'Siparişi görüntüle';
        $mail->buttonUrl = $order->trackingUrl();

        return $mail;
    }

    /** Yöneticiye: iletişim formundan yeni mesaj veya müşterinin yanıtı. */
    public static function messageAdmin(MessageThread $thread, Message $message): self
    {
        $mail = new self('message.admin', 'Yeni mesaj: '.$thread->subject, 'Yeni müşteri mesajı');
        $mail->about = $thread;
        $mail->facts = array_values(array_filter([
            ['Gönderen', $thread->name], ['E-posta', $thread->email],
            $thread->phone ? ['Telefon', TurkishPhone::format($thread->phone)] : null,
            ['Konu', $thread->subject],
            $thread->order ? ['Sipariş', $thread->order->number] : null,
        ]));
        $mail->quote = $message->body;
        $mail->buttonText = 'Panelde yanıtla';
        $mail->buttonUrl = route('admin.messages.show', $thread);
        $mail->replyToAddress = $thread->email;
        $mail->replyToName = $thread->name;

        return $mail;
    }

    /** Müşteriye: yöneticinin yanıtı. */
    public static function messageReply(MessageThread $thread, Message $message): self
    {
        $mail = new self('message.reply', 'Yanıt: '.$thread->subject, 'Mesajınız yanıtlandı');
        $mail->about = $thread;
        $mail->lines = ['Merhaba '.$thread->name.', “'.$thread->subject.'” konulu mesajınıza yanıtımız:'];
        $mail->quote = $message->body;
        $mail->buttonText = 'Yanıt yaz';
        $mail->buttonUrl = $thread->url();

        return $mail;
    }

    public static function welcome(User $user): self
    {
        $mail = new self('account.welcome', Settings::siteName().' hesabınız hazır', 'Hoş geldiniz');
        $mail->about = $user;
        $mail->lines = [
            'Merhaba '.$user->name.', hesabınız oluşturuldu.',
            'Siparişlerinizi, adreslerinizi ve favorilerinizi Hesabım sayfasından yönetebilirsiniz.',
        ];
        $mail->buttonText = 'Alışverişe başla';
        $mail->buttonUrl = route('home');

        return $mail;
    }

    public static function passwordReset(User $user, string $url, int $minutes): self
    {
        $mail = new self('account.reset', 'Parola sıfırlama — '.Settings::siteName(), 'Parola sıfırlama');
        $mail->about = $user;
        $mail->lines = ['Merhaba '.$user->name.', hesabınız için parola sıfırlama isteği aldık.', "Bağlantı {$minutes} dakika geçerlidir."];
        $mail->buttonText = 'Parolamı sıfırla';
        $mail->buttonUrl = $url;
        $mail->quote = 'Bu isteği siz yapmadıysanız bu e-postayı yok sayabilirsiniz; parolanız değişmez.';

        return $mail;
    }

    public static function test(): self
    {
        $mail = new self('test', 'Deneme e-postası — '.Settings::siteName(), 'E-posta ayarları çalışıyor');
        $mail->lines = ['Bu e-postayı görüyorsanız SMTP ayarlarınız doğru. Sipariş ve mesaj bildirimleri bu hesaptan gönderilecek.'];

        return $mail;
    }

    /** Yönetici bildirim adresleri: ayarlardaki liste, yoksa iletişim e-postası. @return list<string> */
    public static function adminRecipients(): array
    {
        $list = preg_split('/[\s,;]+/', (string) Settings::get('notify_emails'), -1, PREG_SPLIT_NO_EMPTY);
        if (! $list && Settings::get('email')) {
            $list = [Settings::get('email')];
        }

        return array_values(array_unique(array_filter($list, fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))));
    }

    /** @return list<array{0: string, 1: string}> */
    private static function orderFacts(Order $order): array
    {
        $rules = app(DeliveryRules::class);

        return array_values(array_filter([
            ['Sipariş no', $order->number],
            ['Teslimat', ($order->delivery_date ? $rules->label(CarbonImmutable::parse($order->delivery_date)) : '').($order->delivery_slot ? ', '.$order->delivery_slot : '')],
            ['Adres', $order->neighborhood.', '.$order->address.' — '.$order->district_name],
            ['Ödeme', $order->paymentLabel()],
        ], fn ($f) => $f[1] !== ''));
    }

    // ---- Mailable ------------------------------------------------------------------------------------------------

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
            replyTo: $this->replyToAddress ? [new Address($this->replyToAddress, $this->replyToName)] : [],
            tags: [$this->type],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.site', text: 'emails.site-text', with: [
            'site' => Settings::all(),
            'siteName' => Settings::siteName(),
            'logo' => Settings::fileUrl('logo_path'),
            'items' => $this->order?->items()->get() ?? collect(),
        ]);
    }

    /** Gönderimi günlüğe yazar (kuyruktan da, doğrudan da). */
    public function send($mailer)
    {
        try {
            $result = parent::send($mailer);
            $this->log('sent');

            return $result;
        } catch (Throwable $e) {
            $this->log('failed', $e->getMessage());
            throw $e;
        }
    }

    private function log(string $status, ?string $error = null): void
    {
        try {
            MailLog::create([
                'type' => $this->type,
                'to' => mb_substr(collect($this->to)->pluck('address')->implode(', '), 0, 190),
                'subject' => mb_substr($this->subjectLine, 0, 255),
                'status' => $status,
                'error' => $error ? mb_substr($error, 0, 500) : null,
                'subject_model_type' => $this->about?->getMorphClass(),
                'subject_model_id' => $this->about?->getKey(),
            ]);
        } catch (Throwable) {
            // günlük yazılamasa da e-posta akışı bozulmasın
        }
    }
}
