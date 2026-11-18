@php
    use App\Models\Order;
    use App\Support\Money;
    use App\Support\Settings;
    // Sipariş onayı: koyu üst bant (animasyonlu onay işareti, konfeti, kopyalanabilir sipariş no), adım çizelgesi,
    // teslimat/ödeme kartları, ürünler ve özet. Yazılar Yönetim › Ayarlar › Genel › Sipariş onayı.
    $steps = ['received' => 'Alındı'] + array_slice(Order::SHORT, 0, 5, true); // müşteriye "Yeni" yerine "Alındı"
    $icons = ['received' => 'package', 'confirmed' => 'check', 'preparing' => 'printer', 'shipping' => 'truck', 'delivered' => 'home'];
    $deliveryLabel = $order->delivery_date
        ? app(\App\Domain\Delivery\DeliveryRules::class)->label(\Carbon\CarbonImmutable::parse($order->delivery_date)).($order->delivery_slot ? ', '.$order->delivery_slot : '')
        : null;
    $whatsapp = Settings::whatsappUrl('Merhaba, '.$order->number.' numaralı siparişim hakkında yazıyorum.');
    $confettiColors = ['var(--color-accent)', '#ffffff', 'color-mix(in oklab, var(--color-primary) 40%, white)', '#fde68a'];
@endphp
<x-layouts.store title="Siparişiniz alındı" :noindex="true" :flush="true">
    <section class="hero-3d done-hero surface-deep relative isolate overflow-hidden text-white">
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
            <div class="hero-grid absolute inset-0"></div>
            <div class="done-glow absolute inset-0"></div>
        </div>
        {{-- Konfeti: bir kez yağar --}}
        <div class="done-confetti pointer-events-none absolute inset-0 -z-10 overflow-hidden" aria-hidden="true">
            @for ($i = 0; $i < 28; $i++)
                <span style="--x: {{ ($i * 37 + 7) % 100 }}%; --r: {{ ($i * 53) % 360 }}deg; --d: {{ ($i % 9) * 0.12 }}s; --t: {{ 2.4 + ($i % 5) * 0.35 }}s; --c: {{ $confettiColors[$i % 4] }}; --w: {{ 6 + ($i % 3) * 3 }}px"></span>
            @endfor
        </div>

        <div class="container-page relative flex max-w-4xl flex-col items-center pt-12 pb-28 text-center sm:pt-14 sm:pb-32">
            <div class="done-badge relative flex size-24 items-center justify-center sm:size-28">
                <span class="done-ring absolute inset-0 rounded-full"></span>
                <span class="done-ring absolute inset-0 rounded-full" style="--d: 1"></span>
                <span class="absolute inset-0 rounded-full bg-gradient-to-br from-success to-[color-mix(in_oklab,var(--color-success)_60%,var(--color-primary))] shadow-[0_20px_40px_-12px_rgb(21_128_61/0.7)]"></span>
                <svg viewBox="0 0 52 52" class="done-check relative size-12 sm:size-14" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 27.5 22.5 36 38.5 17"/></svg>
            </div>
            <h1 class="hero-reveal mt-7 text-3xl font-extrabold tracking-tight sm:text-5xl" style="--d: 1">{{ Settings::get('done_title', 'Siparişiniz alındı') }}</h1>
            @if (Settings::get('done_text'))<p class="hero-reveal mt-3 max-w-xl text-base leading-relaxed text-white/70 sm:text-lg" style="--d: 2">{{ Settings::get('done_text') }}</p>@endif

            <div class="hero-reveal mt-7 flex flex-wrap items-center justify-center gap-3" style="--d: 3">
                <div class="flex items-center gap-1 rounded-2xl bg-white/[0.08] p-1.5 pl-4 ring-1 ring-white/15 backdrop-blur-md" x-data="copyText">
                    <span class="text-xs font-semibold tracking-wide text-white/60 uppercase">Sipariş no</span>
                    <strong class="ml-2 font-display text-xl tracking-wider sm:text-2xl">{{ $order->number }}</strong>
                    <button type="button" x-on:click="copy" data-copy="{{ $order->number }}" data-done="Kopyalandı" class="ml-2 inline-flex items-center gap-1.5 rounded-xl bg-white/10 px-3 py-2 text-xs font-bold transition hover:bg-accent hover:text-ink">
                        <x-icon name="check" class="size-3.5" /><span x-text="label">Kopyala</span>
                    </button>
                </div>
                <span class="rounded-2xl bg-white/[0.08] px-4 py-3 text-sm font-bold ring-1 ring-white/15 backdrop-blur-md">{{ Money::format($order->total) }}</span>
            </div>
        </div>
    </section>

    <div class="page-tint relative pb-16">
        <div class="container-page relative z-10 max-w-5xl">
            {{-- Adım çizelgesi (sipariş takip sayfasıyla aynı görünüm): ilk adımdayız --}}
            <div class="-mt-16 rounded-3xl border border-line/70 bg-white p-5 shadow-[0_30px_60px_-30px_rgba(15,23,42,0.35)] sm:p-7">
                <div class="relative">
                    <div class="absolute top-5 right-[10%] left-[10%] h-1.5 rounded-full bg-line sm:top-6" aria-hidden="true">
                        <div class="track-fill h-full rounded-full bg-gradient-to-r from-primary via-primary to-accent" style="--p: 4%"></div>
                    </div>
                    <ol class="relative grid grid-cols-5">
                        @foreach ($steps as $key => $label)
                            @php($state = $loop->first ? 'now' : 'next')
                            <li class="track-step flex flex-col items-center text-center" data-state="{{ $state }}" style="--i: {{ $loop->index }}">
                                <span class="track-dot relative flex size-10 items-center justify-center rounded-2xl sm:size-12"><x-icon :name="$icons[$key]" class="size-4 sm:size-5" /></span>
                                <span class="mt-2.5 px-0.5 text-[10px] leading-tight font-bold [overflow-wrap:anywhere] sm:text-sm {{ $state === 'next' ? 'text-muted' : 'text-ink' }}">{{ $label }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>

            {{-- Bilgi kartları --}}
            <ul class="done-info mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (array_filter([
                    $deliveryLabel ? ['truck', 'Teslimat', $deliveryLabel, 'primary'] : null,
                    [$order->payment_method === 'card' ? 'card' : 'cash', 'Ödeme', $order->paymentLabel(), 'accent'],
                    ['pin', 'Adres', $order->neighborhood.', '.$order->address.' — '.$order->district_name, 'primary'],
                    ['user', 'Alıcı', $order->customer_name.' · '.\App\Rules\TurkishPhone::format($order->phone), 'accent'],
                ]) as [$icon, $label, $value, $tone])
                    <li class="done-tile card flex items-start gap-3 p-4" style="--i: {{ $loop->index }}">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl {{ $tone === 'accent' ? 'bg-accent-soft text-accent-ink' : 'bg-primary-soft text-primary' }}"><x-icon :name="$icon" class="size-5" /></span>
                        <span class="min-w-0">
                            <span class="block text-[11px] font-bold tracking-wide text-muted uppercase">{{ $label }}</span>
                            <span class="mt-0.5 block text-sm leading-snug font-bold">{{ $value }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>

            <div class="mt-6 grid items-start gap-6 lg:grid-cols-[1fr_340px]">
                <section class="card overflow-hidden">
                    <h2 class="flex items-center gap-3 border-b border-line px-5 py-4 font-bold">
                        <span class="flex size-8 items-center justify-center rounded-xl bg-primary-soft text-primary"><x-icon name="package" class="size-4" /></span>Siparişinizdeki ürünler
                        <span class="ml-auto rounded-full bg-surface px-2.5 py-0.5 text-xs font-semibold text-muted">{{ $order->items->sum('quantity') }} ürün</span>
                    </h2>
                    <ul class="divide-y divide-line">
                        @foreach ($order->items as $item)
                            <li class="flex items-center gap-4 px-5 py-4 text-sm">
                                <span class="cart-thumb flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-xl">
                                    @if ($item->meta['image'] ?? null)<img src="{{ $item->meta['image'] }}" alt="" class="max-h-full max-w-full object-contain p-1 drop-shadow">
                                    @else<x-icon name="{{ $item->kind === 'print' ? 'printer' : 'book' }}" class="size-6 text-primary/40" />@endif
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-bold leading-snug">{{ $item->title }}</span>
                                    <span class="text-xs text-muted">{{ collect([$item->meta['publisher'] ?? null, $item->meta['summary'] ?? $item->printTypeLabel(), $item->quantity.' × '.Money::format($item->unit_price)])->filter()->implode(' · ') }}</span>
                                </span>
                                <span class="font-display font-extrabold whitespace-nowrap">{{ Money::format($item->line_total) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <dl class="space-y-1.5 border-t border-line bg-surface/60 px-5 py-4 text-sm">
                        <div class="flex justify-between"><dt class="text-muted">Ara toplam</dt><dd class="font-semibold">{{ Money::format($order->subtotal) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Teslimat</dt><dd class="font-semibold">{{ (float) $order->delivery_fee > 0 ? Money::format($order->delivery_fee) : 'Ücretsiz' }}</dd></div>
                        <div class="flex items-baseline justify-between border-t border-dashed border-line pt-2.5"><dt class="font-bold">Toplam</dt><dd class="font-display text-2xl font-extrabold text-primary-dark">{{ Money::format($order->total) }}</dd></div>
                    </dl>
                </section>

                <aside class="space-y-4 lg:sticky lg:top-40">
                    <div class="card space-y-4 p-5">
                        <p class="flex gap-3 text-sm leading-relaxed text-muted">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-info-soft text-info"><x-icon name="mail" class="size-4" /></span>
                            <span>
                                @if ($order->email)Sipariş özetini <strong class="text-ink">{{ $order->email }}</strong> adresine gönderdik. @endif
                                {{ Settings::get('done_note', 'Siparişinizin durumunu sipariş numaranız ve telefonunuzla dilediğiniz zaman takip edebilirsiniz.') }}
                            </span>
                        </p>
                        <a href="{{ $order->trackingUrl() }}" class="cart-checkout btn-primary w-full py-3"><x-icon name="truck" class="size-4" />Siparişi takip et</a>
                        <a href="{{ route('home') }}" class="btn-ghost w-full py-3"><x-icon name="book" class="size-4" />Alışverişe devam et</a>
                    </div>
                    @if ($whatsapp)
                        <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="done-wa group card flex items-center gap-3 p-4">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#25d366] text-white transition group-hover:scale-110"><x-icon name="whatsapp" class="size-5" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold">{{ Settings::get('done_help_title', 'Sorunuz mu var?') }}</span>
                                <span class="block text-xs text-muted">{{ Settings::get('done_help_text', 'WhatsApp üzerinden bize yazın.') }}</span>
                            </span>
                            <x-icon name="chevron-right" class="size-4 text-muted transition group-hover:translate-x-0.5" />
                        </a>
                    @endif
                </aside>
            </div>
        </div>
    </div>
</x-layouts.store>
