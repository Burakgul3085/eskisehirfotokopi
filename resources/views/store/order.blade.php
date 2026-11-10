@php
    use App\Models\Order;
    use App\Support\Money;
    /** Sipariş ayrıntısı: misafir takibi ($context = track) ve Hesabım (account) ortak. */
    $steps = array_slice(Order::SHORT, 0, 5, true);
    $current = $order->step();
@endphp
<x-layouts.store :title="'Sipariş '.$order->number" :noindex="true">
    <div class="container-page max-w-4xl py-6 sm:py-10">
        <x-breadcrumb :items="$context === 'account' ? [['Hesabım', route('account.index')], ['Siparişlerim', route('account.orders')], [$order->number, null]] : [['Sipariş takibi', route('orders.track')], [$order->number, null]]" />
        <x-flash class="mt-5" />

        <div class="mt-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm text-muted">{{ $order->created_at->locale('tr')->translatedFormat('j F Y, H:i') }}</p>
                <h1 class="text-2xl font-extrabold sm:text-3xl">Sipariş {{ $order->number }}</h1>
            </div>
            <span class="badge {{ Order::tone($order->status) }} px-3 py-1 text-sm">{{ $order->statusLabel() }}</span>
        </div>

        <div class="card mt-6 p-5 sm:p-7">
            @if ($current === null)
                <p class="flex items-start gap-3 text-danger"><x-icon name="alert" class="mt-0.5 size-5 shrink-0" />
                    <span>Bu sipariş iptal edildi.@if ($order->cancel_reason) <span class="block text-sm text-muted">{{ $order->cancel_reason }}</span>@endif</span></p>
            @else
                <ol class="grid grid-cols-5 gap-1">
                    @foreach ($steps as $key => $label)
                        @php($done = $loop->index <= $current)
                        <li class="flex flex-col items-center gap-2 text-center">
                            <span class="flex h-1.5 w-full rounded-full {{ $done ? 'bg-primary' : 'bg-line' }}"></span>
                            <span class="text-[11px] font-semibold sm:text-sm {{ $done ? 'text-ink' : 'text-muted' }}">{{ $label }}</span>
                        </li>
                    @endforeach
                </ol>
                @if ($order->isOpen() && $order->delivery_date)
                    <p class="mt-5 flex items-center gap-2 text-sm"><x-icon name="truck" class="size-4 text-primary" />Tahmini teslimat:
                        <strong>{{ app(\App\Domain\Delivery\DeliveryRules::class)->label(\Carbon\CarbonImmutable::parse($order->delivery_date)) }}{{ $order->delivery_slot ? ', '.$order->delivery_slot : '' }}</strong></p>
                @endif
            @endif
        </div>

        <div class="mt-6 grid items-start gap-6 lg:grid-cols-[1fr_320px]">
            <section class="card overflow-hidden">
                <h2 class="border-b border-line px-5 py-4 font-bold">Ürünler</h2>
                <ul class="divide-y divide-line">
                    @foreach ($order->items as $item)
                        <li class="flex gap-4 px-5 py-4 text-sm">
                            <span class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-surface">
                                @if ($item->meta['image'] ?? null)<img src="{{ $item->meta['image'] }}" alt="" class="max-h-full max-w-full object-contain">
                                @else<x-icon name="{{ $item->kind === 'print' ? 'printer' : 'book' }}" class="size-6 text-slate-300" />@endif
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold">{{ $item->title }}</span>
                                <span class="text-xs text-muted">{{ collect([$item->meta['publisher'] ?? null, $item->meta['summary'] ?? $item->printTypeLabel(), $item->quantity.' × '.Money::format($item->unit_price)])->filter()->implode(' · ') }}</span>
                            </span>
                            <span class="font-semibold whitespace-nowrap">{{ Money::format($item->line_total) }}</span>
                        </li>
                    @endforeach
                </ul>
                <dl class="space-y-1.5 border-t border-line bg-surface/60 px-5 py-4 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">Ara toplam</dt><dd>{{ Money::format($order->subtotal) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Teslimat</dt><dd>{{ (float) $order->delivery_fee > 0 ? Money::format($order->delivery_fee) : 'Ücretsiz' }}</dd></div>
                    <div class="flex justify-between text-base font-bold"><dt>Toplam</dt><dd>{{ Money::format($order->total) }}</dd></div>
                </dl>
            </section>

            <div class="space-y-6">
                <section class="card space-y-3 p-5 text-sm">
                    <h2 class="font-bold">Teslimat</h2>
                    <p><span class="font-semibold">{{ $order->customer_name }}</span><br>{{ \App\Rules\TurkishPhone::format($order->phone) }}</p>
                    <p class="text-muted">{{ $order->neighborhood }}, {{ $order->address }}<br>{{ $order->district_name }} / Eskişehir</p>
                    <p class="flex items-center gap-2"><x-icon name="{{ $order->payment_method === 'card' ? 'card' : 'cash' }}" class="size-4 text-primary" />{{ $order->paymentLabel() }}</p>
                    @if ($order->note)<p class="rounded-lg bg-surface p-2.5 text-muted">{{ $order->note }}</p>@endif
                </section>

                <section class="card p-5 text-sm">
                    <h2 class="font-bold">Sipariş geçmişi</h2>
                    <ol class="mt-3 space-y-3 border-l-2 border-line pl-4">
                        @foreach ($order->history->reverse() as $h)
                            <li class="relative">
                                <span class="absolute top-1.5 -left-[21px] size-2.5 rounded-full {{ $loop->first ? 'bg-primary' : 'bg-line' }}"></span>
                                <p class="font-semibold">{{ Order::STATUSES[$h->status] ?? $h->status }}</p>
                                <p class="text-xs text-muted">{{ $h->created_at->locale('tr')->translatedFormat('j F, H:i') }}</p>
                                @if ($h->note)<p class="mt-1 text-muted">{{ $h->note }}</p>@endif
                            </li>
                        @endforeach
                    </ol>
                </section>

                @if ($context === 'account' && $order->items->whereNotNull('product_id')->isNotEmpty())
                    <form method="post" action="{{ route('account.orders.reorder', $order) }}">@csrf
                        <button class="btn-ghost w-full"><x-icon name="cart" class="size-4" />Tekrar sipariş ver</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-layouts.store>
