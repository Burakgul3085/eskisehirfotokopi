@php
    use App\Models\Order;
    use App\Support\Money;
    /** Sipariş ayrıntısı: misafir takibi ($context = track) ve Hesabım (account) ortak. */
    $steps = array_slice(Order::SHORT, 0, 5, true);
    $current = $order->step();
    $icons = ['received' => 'package', 'confirmed' => 'check', 'preparing' => 'printer', 'shipping' => 'truck', 'delivered' => 'home'];
    // Her adımın ilk geçildiği an (geçmişten)
    $reached = $order->history->groupBy('status')->map(fn ($g) => $g->min('created_at'));
    $fill = $current === null ? 0 : $current / (count($steps) - 1) * 100;
    // Hesabım'da teslim edilmiş siparişin ürünleri değerlendirilebilir
    $canReview = $context === 'account' && $order->status === 'delivered' && \App\Support\Settings::flag('reviews_enabled');
@endphp
<x-layouts.store :title="'Sipariş '.$order->number" :noindex="true" :flush="true">
    {{-- Koyu üst bant: sipariş no, durum ve siparişin adımına göre konumlanan 3D sahne (resources/js/scenes/order-track.js) --}}
    <section class="hero-3d surface-deep relative isolate overflow-hidden text-white">
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
            <div class="hero-grid absolute inset-0"></div>
            <div class="hero-spot absolute inset-0"></div>
        </div>
        <div class="container-page relative grid max-w-5xl items-center gap-4 pt-6 pb-24 md:grid-cols-[1fr_1fr] md:pb-28">
            <div class="relative z-10">
                <x-breadcrumb class="hero-breadcrumb" :items="$context === 'account' ? [['Hesabım', route('account.index')], ['Siparişlerim', route('account.orders')], [$order->number, null]] : [['Sipariş takibi', route('orders.track')], [$order->number, null]]" />
                <p class="hero-reveal mt-6 text-sm text-white/60" style="--d: 0">{{ $order->created_at->locale('tr')->translatedFormat('j F Y, H:i') }}</p>
                <h1 class="hero-reveal mt-1 text-3xl font-extrabold tracking-tight sm:text-4xl lg:text-5xl" style="--d: 1">
                    <span class="text-white/70">Sipariş</span> <span class="bg-gradient-to-r from-white to-accent bg-clip-text text-transparent">{{ $order->number }}</span>
                </h1>
                <div class="hero-reveal mt-5 flex flex-wrap items-center gap-3" style="--d: 2">
                    <span class="badge {{ Order::tone($order->status) }} gap-2 px-3.5 py-1.5 text-sm shadow-lg">
                        @if ($order->isOpen())<span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-50"></span><span class="relative inline-flex size-2 rounded-full bg-current"></span></span>@endif
                        {{ $order->statusLabel() }}
                    </span>
                    <span class="rounded-full bg-white/10 px-3.5 py-1.5 text-sm font-semibold ring-1 ring-white/15 backdrop-blur">{{ Money::format($order->total) }}</span>
                </div>
                @if ($order->isOpen() && $order->delivery_date)
                    <p class="hero-reveal mt-5 inline-flex items-center gap-3 rounded-2xl bg-white/[0.07] px-4 py-3 text-sm ring-1 ring-white/10 backdrop-blur" style="--d: 3">
                        <span class="flex size-9 items-center justify-center rounded-xl bg-accent text-ink"><x-icon name="truck" class="size-4" /></span>
                        <span><span class="block text-xs text-white/60">Tahmini teslimat</span>
                            <strong>{{ app(\App\Domain\Delivery\DeliveryRules::class)->label(\Carbon\CarbonImmutable::parse($order->delivery_date)) }}{{ $order->delivery_slot ? ', '.$order->delivery_slot : '' }}</strong></span>
                    </p>
                @endif
            </div>
            <div data-scene="order-track" data-step="{{ $current ?? 0 }}" data-cancelled="{{ $current === null ? '1' : '0' }}" class="relative h-[240px] sm:h-[300px] md:h-[340px]">
                <div class="hero-fallback pointer-events-none absolute inset-0 flex items-center justify-center transition-opacity duration-1000" aria-hidden="true">
                    <div class="size-48 rounded-full bg-gradient-to-br from-white/25 to-accent/20 blur-2xl"></div>
                </div>
                <div class="hero-stage absolute inset-0">
                    <canvas data-scene-canvas class="block size-full" aria-hidden="true"></canvas>
                </div>
            </div>
        </div>
    </section>

    {{-- Alt zemin: üst bandın renginden açılan tonlu geçiş (düz beyaz yerine), kartlar üstünde beyaz --}}
    <div class="page-tint relative pb-16">
    <div class="container-page relative z-10 max-w-5xl">
        {{-- Adım çizelgesi: üst bandın alt kenarına biner; dolgu çizgisi açılışta adıma kadar akar --}}
        <div class="-mt-16 rounded-3xl border border-line/70 bg-white p-5 shadow-[0_30px_60px_-30px_rgba(15,23,42,0.35)] sm:p-7">
            @if ($current === null)
                <p class="flex items-start gap-3 text-danger"><span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-danger-soft"><x-icon name="alert" class="size-5" /></span>
                    <span class="pt-2.5 font-semibold">Bu sipariş iptal edildi.@if ($order->cancel_reason) <span class="block text-sm font-normal text-muted">{{ $order->cancel_reason }}</span>@endif</span></p>
            @else
                <div class="relative">
                    <div class="absolute top-5 right-[10%] left-[10%] h-1.5 rounded-full bg-line sm:top-6" aria-hidden="true">
                        <div class="track-fill h-full rounded-full bg-gradient-to-r from-primary via-primary to-accent" style="--p: {{ $fill }}%"></div>
                    </div>
                    <ol class="relative grid grid-cols-5">
                        @foreach ($steps as $key => $label)
                            @php($state = $loop->index < $current || $order->status === 'delivered' ? 'done' : ($loop->index === $current ? 'now' : 'next'))
                            <li class="track-step flex flex-col items-center text-center" data-state="{{ $state }}" style="--i: {{ $loop->index }}">
                                <span class="track-dot relative flex size-10 items-center justify-center rounded-2xl sm:size-12">
                                    <x-icon :name="$state === 'done' ? 'check' : $icons[$key]" class="size-4 sm:size-5" />
                                </span>
                                <span class="mt-2.5 px-0.5 text-[10px] leading-tight font-bold [overflow-wrap:anywhere] sm:text-sm {{ $state === 'next' ? 'text-muted' : 'text-ink' }}">{{ $label }}</span>
                                @if ($state !== 'next' && isset($reached[$key]))
                                    <span class="mt-0.5 hidden text-xs text-muted sm:block">{{ $reached[$key]->locale('tr')->translatedFormat('j M, H:i') }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
        </div>
        <x-flash class="mt-6" />

        <div class="mt-6 grid items-start gap-6 lg:grid-cols-[1fr_340px]">
            <div class="min-w-0 space-y-6">
            <section class="card overflow-hidden">
                <h2 class="flex items-center gap-3 border-b border-line px-5 py-4 font-bold">
                    <span class="flex size-8 items-center justify-center rounded-xl bg-primary-soft text-primary"><x-icon name="package" class="size-4" /></span>Ürünler
                    <span class="ml-auto rounded-full bg-surface px-2.5 py-0.5 text-xs font-semibold text-muted">{{ $order->items->sum('quantity') }}</span>
                </h2>
                <ul class="divide-y divide-line">
                    @foreach ($order->items as $item)
                        <li class="track-item flex gap-4 px-5 py-4 text-sm">
                            <span class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-surface ring-1 ring-line">
                                @if ($item->meta['image'] ?? null)<img src="{{ $item->meta['image'] }}" alt="" class="max-h-full max-w-full object-contain">
                                @else<x-icon name="{{ $item->kind === 'print' ? 'printer' : 'book' }}" class="size-6 text-slate-300" />@endif
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold">{{ $item->title }}</span>
                                <span class="text-xs text-muted">{{ collect([$item->meta['publisher'] ?? null, $item->meta['summary'] ?? $item->printTypeLabel(), $item->quantity.' × '.Money::format($item->unit_price)])->filter()->implode(' · ') }}</span>
                            </span>
                            <span class="flex shrink-0 flex-col items-end gap-2">
                                <span class="font-semibold whitespace-nowrap">{{ Money::format($item->line_total) }}</span>
                                @if ($canReview && $item->kind === 'product' && $item->product?->status === 'active')
                                    <a href="{{ route('product.show', $item->product) }}#degerlendirme-yaz" class="inline-flex items-center gap-1 rounded-lg bg-accent-soft px-2.5 py-1 text-xs font-bold text-accent-ink hover:bg-accent hover:text-ink"><x-icon name="star" class="size-3.5" />{{ $settings['reviews_rate_cta'] }}</a>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
                <dl class="space-y-1.5 border-t border-line bg-surface/60 px-5 py-4 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">Ara toplam</dt><dd>{{ Money::format($order->subtotal) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Teslimat</dt><dd>{{ (float) $order->delivery_fee > 0 ? Money::format($order->delivery_fee) : 'Ücretsiz' }}</dd></div>
                    <div class="flex justify-between border-t border-dashed border-line pt-2 font-display text-lg font-extrabold"><dt>Toplam</dt><dd class="text-primary">{{ Money::format($order->total) }}</dd></div>
                </dl>
            </section>

                <section class="card p-5 text-sm">
                    <h2 class="flex items-center gap-3 font-bold"><span class="flex size-8 items-center justify-center rounded-xl bg-info-soft text-info"><x-icon name="clock" class="size-4" /></span>Sipariş geçmişi</h2>
                    <ol class="track-history mt-4 space-y-4 border-l-2 border-line pl-5">
                        @foreach ($order->history->reverse() as $h)
                            <li class="relative" style="--i: {{ $loop->index }}">
                                <span class="absolute top-1 -left-[27px] flex size-3.5 items-center justify-center rounded-full ring-4 ring-white {{ $loop->first ? 'bg-primary' : 'bg-line' }}">
                                    @if ($loop->first)<span class="absolute inline-flex size-full animate-ping rounded-full bg-primary opacity-40"></span>@endif
                                </span>
                                <p class="font-semibold">{{ Order::STATUSES[$h->status] ?? $h->status }}</p>
                                <p class="text-xs text-muted">{{ $h->created_at->locale('tr')->translatedFormat('j F, H:i') }}</p>
                                @if ($h->note)<p class="mt-1 text-muted">{{ $h->note }}</p>@endif
                            </li>
                        @endforeach
                    </ol>
                </section>
            </div>

            <div class="space-y-6 lg:sticky lg:top-24">
                <section class="card space-y-3 p-5 text-sm">
                    <h2 class="flex items-center gap-3 font-bold"><span class="flex size-8 items-center justify-center rounded-xl bg-accent-soft text-accent-ink"><x-icon name="pin" class="size-4" /></span>Teslimat</h2>
                    <p><span class="font-semibold">{{ $order->customer_name }}</span><br>{{ \App\Rules\TurkishPhone::format($order->phone) }}</p>
                    <p class="text-muted">{{ $order->neighborhood }}, {{ $order->address }}<br>{{ $order->district_name }} / Eskişehir</p>
                    <p class="flex items-center gap-2 rounded-xl bg-surface px-3 py-2"><x-icon name="{{ $order->payment_method === 'card' ? 'card' : 'cash' }}" class="size-4 text-primary" />{{ $order->paymentLabel() }}</p>
                    @if ($order->note)<p class="rounded-xl bg-surface p-3 text-muted">{{ $order->note }}</p>@endif
                </section>


                @if ($context === 'account' && $order->items->whereNotNull('product_id')->isNotEmpty())
                    <form method="post" action="{{ route('account.orders.reorder', $order) }}">@csrf
                        <button class="btn-ghost w-full"><x-icon name="cart" class="size-4" />Tekrar sipariş ver</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
    </div>
</x-layouts.store>
