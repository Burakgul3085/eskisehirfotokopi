<x-layouts.store title="Siparişiniz alındı" :noindex="true">
    <div class="container-page max-w-3xl py-10 sm:py-14">
        <div class="card overflow-hidden">
            <div class="flex flex-col items-center gap-3 bg-success-soft px-6 py-10 text-center">
                <span class="flex size-16 items-center justify-center rounded-full bg-success text-white"><x-icon name="check" class="size-8" /></span>
                <h1 class="text-2xl font-extrabold sm:text-3xl">Siparişiniz alındı</h1>
                <p class="text-muted">Sipariş numaranız <strong class="font-display text-lg text-ink">{{ $order->number }}</strong></p>
            </div>
            <div class="space-y-5 p-6 sm:p-8">
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-muted">Teslimat</dt><dd class="font-semibold">{{ app(\App\Domain\Delivery\DeliveryRules::class)->label(\Carbon\CarbonImmutable::parse($order->delivery_date)) }}{{ $order->delivery_slot ? ', '.$order->delivery_slot : '' }}</dd></div>
                    <div><dt class="text-muted">Ödeme</dt><dd class="font-semibold">{{ $order->paymentLabel() }} · {{ \App\Support\Money::format($order->total) }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-muted">Adres</dt><dd class="font-semibold">{{ $order->neighborhood }}, {{ $order->address }} — {{ $order->district_name }}</dd></div>
                </dl>
                <p class="rounded-xl bg-surface p-4 text-sm text-muted">
                    @if ($order->email)Sipariş özetini <strong class="text-ink">{{ $order->email }}</strong> adresine gönderdik. @endif
                    Siparişinizin durumunu sipariş numaranız ve telefonunuzla dilediğiniz zaman takip edebilirsiniz.
                </p>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ $order->trackingUrl() }}" class="btn-primary"><x-icon name="truck" class="size-4" />Siparişi takip et</a>
                    <a href="{{ route('home') }}" class="btn-ghost">Alışverişe devam et</a>
                </div>
            </div>
        </div>
    </div>
</x-layouts.store>
