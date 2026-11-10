<?php

namespace App\Domain\Print;

use App\Domain\Cart\Cart;
use App\Domain\Cart\CartLine;
use App\Models\PrintFile;
use App\Models\PrintJob;
use DomainException;
use Illuminate\Validation\ValidationException;

/**
 * Baskı işinin fiyatı ve sepete eklenmesi. Fiyat her zaman sunucuda, belgenin sunucuda sayılan sayfa sayısıyla
 * hesaplanır; sepette her gösterimde güncel ayarlarla yenilenir (resolver). Sepet satırı "print:{iş}" anahtarlıdır.
 */
class PrintCart
{
    public function __construct(private PrintSettings $settings, private PrintPricing $pricing) {}

    /**
     * @return array{options: PrintOptions, quote: array}
     *
     * @throws ValidationException|DomainException
     */
    public function quote(PrintFile $file, array $input): array
    {
        if (! $this->settings->enabled()) {
            throw new DomainException('Fotokopi siparişi şu anda alınmıyor.');
        }
        if (! $file->isReady()) {
            throw new DomainException('Belge henüz hazır değil.');
        }
        $options = PrintOptions::fromInput($input, $file->pages, $this->settings);
        if (! $this->settings->colorEnabled($options->color)) {
            throw ValidationException::withMessages(['color' => PrintOptions::COLORS[$options->color].' baskı şu anda yapılamıyor. Lütfen diğer seçeneği seçin.']);
        }

        return ['options' => $options, 'quote' => $this->pricing->quote($file->pages, $options)];
    }

    /** @throws ValidationException|DomainException */
    public function add(Cart $cart, string $owner, PrintFile $file, array $input): PrintJob
    {
        if ($file->owner !== $owner) {
            throw new DomainException('Belge bulunamadı.');
        }
        ['options' => $options, 'quote' => $quote] = $this->quote($file, $input);
        $job = PrintJob::create([
            'file_id' => $file->id, 'owner' => $owner, 'options' => $options->toArray(),
            'pages' => $quote['pages'], 'sides' => $quote['sides'], 'sheets' => $quote['sheets'], 'copies' => $quote['copies'],
            'price' => $quote['total'], 'breakdown' => $quote,
        ]);
        try {
            $cart->addItem('print', $job->id);
        } catch (DomainException $e) {
            $job->delete();
            throw $e;
        }

        return $job;
    }

    /**
     * Sepet satırı üretici (AppServiceProvider'da Cart::resolver('print', …) ile bağlanır). İş başka oturuma aitse,
     * siparişe girmişse, belge silinmişse veya fiyat artık verilemiyorsa null döner ve satır sepetten düşer.
     */
    public function line(array $item, string $key, ?string $owner): ?CartLine
    {
        if (! $this->settings->enabled() || $owner === null) {
            return null;
        }
        $job = PrintJob::with('file')->where('owner', $owner)->whereNull('order_id')->find($item['id'] ?? 0);
        if (! $job || ! $job->file?->isReady() || ! $this->settings->colorEnabled($job->printOptions()->color)) {
            return null;
        }
        try {
            $quote = $this->pricing->quote($job->file->pages, $job->printOptions());
        } catch (DomainException) {
            return null;
        }
        if (bccomp($quote['total'], (string) $job->price, 2) !== 0) {
            $job->update(['price' => $quote['total'], 'breakdown' => $quote, 'sides' => $quote['sides'], 'sheets' => $quote['sheets']]);
        }

        return new CartLine(
            key: $key,
            kind: 'print',
            title: $job->file->original_name,
            unitPrice: (float) $quote['total'],
            quantity: 1,
            printType: $job->printOptions()->color,
            url: route('print.index'),
            image: $job->file->preview_path ? route('print.preview', $job->file->token) : null,
            subtitle: $job->summary(),
            printJobId: $job->id,
            maxQuantity: 1,
            meta: ['summary' => $job->summary(), 'file' => $job->file->original_name, 'pages' => $job->pages],
        );
    }
}
