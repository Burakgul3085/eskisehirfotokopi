@php
    use App\Support\Money;
    use App\Support\Settings;
    // Yazılar Yönetim › Ayarlar › Genel › Sepet sayfası
    $steps = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) Settings::get('cart_steps')))));
    $trust = Settings::iconLines('cart_trust');
    $count = collect($lines)->sum('quantity');
    $payments = collect(app(\App\Domain\Delivery\DeliveryRules::class)->paymentMethods());
    $pct = ($remaining !== null && $threshold) ? min(100, (int) round($subtotal / $threshold * 100)) : 100;
    $belowMin = $minOrder !== null && $subtotal < $minOrder;
@endphp
<x-layouts.store title="Sepetim" :noindex="true" :flush="true">
    {{--
        Kurumsal sepet: üstte adım göstergesi, solda ürün satırları (adet artır/azalt, kaldır), ücretsiz teslimat
        çubuğu; sağda yapışkan sipariş özeti ve güven rozetleri; altta öneri ürünleri. Boş sepette ayrı karşılama.
    --}}
    <div class="page-tint relative pb-14">
        <div class="container-page pt-6 sm:pt-8">
            <x-breadcrumb :items="[['Sepetim', null]]" />

            <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
                <h1 class="flex items-baseline gap-3 text-2xl font-extrabold tracking-tight sm:text-3xl">
                    Sepetim
                    @if ($count)<span class="rounded-full bg-primary-soft px-3 py-1 text-sm font-bold text-primary-dark">{{ $count }} ürün</span>@endif
                </h1>
                @if ($steps && $lines !== [])
                    <ol class="cart-steps flex items-center gap-2 text-xs font-semibold sm:text-sm" aria-label="Sipariş adımları">
                        @foreach ($steps as $i => $step)
                            @if ($i)<li aria-hidden="true" class="h-px w-6 bg-line sm:w-10"></li>@endif
                            <li class="flex items-center gap-2 {{ $i === 0 ? 'text-ink' : 'text-muted' }}" @if ($i === 0) aria-current="step" @endif>
                                <span class="flex size-7 items-center justify-center rounded-full text-xs font-bold {{ $i === 0 ? 'bg-primary text-white shadow-[0_6px_14px_-6px_var(--color-primary)]' : 'bg-white text-muted ring-1 ring-line' }}">{{ $i + 1 }}</span>
                                <span class="{{ $i === 0 ? '' : 'hidden sm:inline' }}">{{ $step }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
            <x-flash class="mt-5" />

            @if ($lines === [])
                <div class="cart-empty card relative mt-6 overflow-hidden px-6 py-14 text-center sm:py-20">
                    <div class="cart-empty-art relative mx-auto size-36" aria-hidden="true">
                        <span class="absolute inset-0 rounded-full bg-gradient-to-br from-primary-soft to-white"></span>
                        <span class="cart-empty-ring absolute -inset-3 rounded-full border border-dashed border-primary/25"></span>
                        <span class="absolute inset-0 flex items-center justify-center text-primary"><x-icon name="cart" class="size-14" /></span>
                        <span class="cart-empty-chip absolute -top-1 right-0 flex size-10 items-center justify-center rounded-2xl bg-accent text-ink shadow-lg" style="--d: 0"><x-icon name="book" class="size-5" /></span>
                        <span class="cart-empty-chip absolute bottom-1 -left-3 flex size-10 items-center justify-center rounded-2xl bg-white text-primary shadow-lg ring-1 ring-line" style="--d: 1"><x-icon name="printer" class="size-5" /></span>
                    </div>
                    <h2 class="mt-8 text-xl font-extrabold sm:text-2xl">{{ Settings::get('cart_empty_title', 'Sepetiniz boş') }}</h2>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-muted">{{ $settings['delivery_info'] }}</p>
                    <div class="mt-7 flex flex-wrap justify-center gap-3">
                        <a href="{{ route('products.index') }}" class="btn-primary px-6 py-3"><x-icon name="book" class="size-4" />Ürünlere göz at</a>
                        @if (Route::has('print.index'))<a href="{{ route('print.index') }}#yukle" class="btn-ghost px-6 py-3"><x-icon name="upload" class="size-4" />Belge yazdır</a>@endif
                    </div>
                    @if ($trust)
                        <ul class="mx-auto mt-10 grid max-w-3xl gap-3 border-t border-dashed border-line pt-8 text-left sm:grid-cols-{{ min(3, count($trust)) }}">
                            @foreach ($trust as $t)
                                <li class="flex items-center gap-3">
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary"><x-icon :name="$t['icon']" class="size-5" /></span>
                                    <span><span class="block text-sm font-bold">{{ $t['title'] }}</span>@if ($t['text'])<span class="block text-xs text-muted">{{ $t['text'] }}</span>@endif</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @else
                <div class="mt-6 grid items-start gap-6 lg:grid-cols-[1fr_380px] xl:gap-8">
                    <div class="space-y-4">
                        {{-- Ücretsiz teslimat çubuğu --}}
                        @if ($threshold !== null)
                            <div class="cart-free card flex items-center gap-4 p-4 sm:p-5">
                                <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl {{ $remaining === null ? 'bg-success-soft text-success' : 'bg-primary-soft text-primary' }}"><x-icon name="truck" class="size-5" /></span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm">
                                        @if ($remaining === null)<strong class="text-success">Teslimat ücretsiz!</strong> Sepetiniz ücretsiz teslimat tutarını geçti.
                                        @else<strong>{{ Money::format($remaining) }}</strong> daha ekleyin, teslimat ücretsiz olsun.@endif
                                    </p>
                                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-surface"><div class="cart-free-bar h-full rounded-full" style="width: {{ $pct }}%"></div></div>
                                </div>
                            </div>
                        @endif

                        <div class="card overflow-hidden">
                            <div class="hidden grid-cols-[1fr_140px_120px] gap-4 border-b border-line bg-surface/60 px-5 py-3 text-xs font-bold tracking-wide text-muted uppercase md:grid">
                                <span>Ürün</span><span class="text-center">Adet</span><span class="text-right">Tutar</span>
                            </div>
                            <ul class="divide-y divide-line">
                                @foreach ($lines as $line)
                                    <li class="cart-line grid grid-cols-[auto_1fr] gap-4 p-4 sm:p-5 md:grid-cols-[1fr_140px_120px] md:items-center">
                                        <div class="contents md:flex md:min-w-0 md:gap-4">
                                            <a href="{{ $line->url ?? '#' }}" class="cart-thumb flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl sm:size-24">
                                                @if ($line->image)<img src="{{ $line->image }}" alt="" class="max-h-full max-w-full object-contain p-1.5 drop-shadow-md" loading="lazy">
                                                @else<x-icon name="{{ $line->kind === 'print' ? 'printer' : 'book' }}" class="size-8 text-primary/40" />@endif
                                            </a>
                                            <div class="min-w-0">
                                                <span class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-bold tracking-wide uppercase {{ $line->kind === 'print' ? 'bg-accent-soft text-accent-ink' : 'bg-primary-soft text-primary-dark' }}">
                                                    <x-icon name="{{ $line->kind === 'print' ? 'printer' : 'book' }}" class="size-3" />{{ $line->kind === 'print' ? 'Belge baskısı' : 'Kitap' }}
                                                </span>
                                                @if ($line->url)<a href="{{ $line->url }}" class="mt-1 line-clamp-2 font-bold leading-snug hover:text-primary">{{ $line->title }}</a>
                                                @else<p class="mt-1 line-clamp-2 font-bold leading-snug">{{ $line->title }}</p>@endif
                                                @if ($sub = collect([$line->subtitle, $line->printTypeLabel()])->filter()->implode(' · '))<p class="mt-0.5 text-xs text-muted">{{ $sub }}</p>@endif
                                                <p class="mt-1 text-xs text-muted">Birim fiyat: <span class="font-semibold text-ink">{{ Money::format($line->unitPrice) }}</span></p>
                                                <form method="post" action="{{ route('cart.remove') }}" class="mt-2">@csrf @method('delete')
                                                    <input type="hidden" name="key" value="{{ $line->key }}">
                                                    <button class="inline-flex items-center gap-1 text-xs font-semibold text-muted transition hover:text-danger"><x-icon name="trash" class="size-3.5" />Kaldır</button>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="col-start-2 flex items-center justify-between gap-3 md:col-start-auto md:contents">
                                            <div class="md:flex md:justify-center">
                                                @if ($line->maxQuantity > 1)
                                                    <form method="post" action="{{ route('cart.update') }}" class="qty-stepper inline-flex items-center rounded-xl border border-line bg-white p-1">@csrf @method('patch')
                                                        <input type="hidden" name="key" value="{{ $line->key }}">
                                                        <button name="quantity" value="{{ $line->quantity - 1 }}" class="qty-btn" aria-label="Azalt" @disabled($line->quantity <= 1)><x-icon name="minus" class="size-4" /></button>
                                                        <span class="w-9 text-center text-sm font-bold tabular-nums" aria-live="polite">{{ $line->quantity }}</span>
                                                        <button name="quantity" value="{{ $line->quantity + 1 }}" class="qty-btn" aria-label="Artır" @disabled($line->quantity >= $line->maxQuantity)><x-icon name="plus" class="size-4" /></button>
                                                    </form>
                                                @else
                                                    <span class="text-sm text-muted">{{ $line->quantity }} adet</span>
                                                @endif
                                            </div>
                                            <span class="font-display text-lg font-extrabold md:text-right">{{ Money::format($line->total()) }}</span>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line bg-surface/60 px-5 py-3.5">
                                <a href="{{ route('products.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline"><x-icon name="chevron-right" class="size-4 rotate-180" />Alışverişe devam et</a>
                            </div>
                        </div>

                        @if (Route::has('print.index'))
                            {{-- Belge ekleme: fotokopi sayfasının yükleme alanına gider; baskı sepete ayrı satır olarak eklenir --}}
                            <a href="{{ route('print.index') }}#yukle" class="cart-print group flex items-center gap-4 rounded-[1.25rem] p-4 sm:p-5">
                                <span class="cart-print-icon flex size-12 shrink-0 items-center justify-center rounded-2xl text-white"><x-icon name="upload" class="size-6" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-bold">{{ Settings::get('cart_print_title', 'Belge de bastırmak ister misiniz?') }}</span>
                                    <span class="block text-sm text-muted">{{ Settings::get('cart_print_text', 'PDF veya Word dosyanızı yükleyin, fiyatı görün, siparişinize ekleyelim.') }}</span>
                                </span>
                                <span class="hidden shrink-0 items-center gap-1.5 rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white shadow-[0_10px_20px_-10px_var(--color-primary)] transition group-hover:-translate-y-0.5 sm:inline-flex"><x-icon name="printer" class="size-4" />Belge ekle</span>
                                <x-icon name="chevron-right" class="size-5 shrink-0 text-primary sm:hidden" />
                            </a>
                        @endif
                    </div>

                    {{-- Sipariş özeti --}}
                    <aside class="space-y-4 lg:sticky lg:top-40">
                        <div class="cart-summary card overflow-hidden">
                            <div class="surface-deep relative isolate overflow-hidden px-6 py-5 text-white">
                                <div class="cart-summary-glow pointer-events-none absolute inset-0 -z-10" aria-hidden="true"></div>
                                <h2 class="text-lg font-bold">Sipariş özeti</h2>
                                <p class="text-xs text-white/60">{{ $count }} ürün</p>
                            </div>
                            <div class="space-y-4 p-6">
                                <dl class="space-y-2.5 text-sm">
                                    <div class="flex justify-between"><dt class="text-muted">Ara toplam</dt><dd class="font-semibold">{{ Money::format($subtotal) }}</dd></div>
                                    <div class="flex justify-between"><dt class="text-muted">Teslimat</dt><dd class="{{ $remaining === null && $threshold !== null ? 'font-semibold text-success' : 'text-muted' }}">{{ $remaining === null && $threshold !== null ? 'Ücretsiz' : 'Adrese göre hesaplanır' }}</dd></div>
                                    <div class="flex items-baseline justify-between border-t border-dashed border-line pt-3.5">
                                        <dt class="font-bold">Toplam</dt>
                                        <dd class="font-display text-2xl font-extrabold text-primary-dark">{{ Money::format($subtotal) }}</dd>
                                    </div>
                                </dl>
                                @if ($belowMin)
                                    <p class="flex gap-2 rounded-xl bg-accent-soft p-3 text-sm text-accent-ink"><x-icon name="alert" class="mt-0.5 size-4 shrink-0" />En düşük sipariş tutarı {{ Money::format($minOrder) }}. Sepetinize {{ Money::format($minOrder - $subtotal) }} daha ekleyin.</p>
                                @endif
                                <a href="{{ route('checkout.show') }}" class="cart-checkout btn-primary w-full py-3.5 text-base">Siparişi tamamla <x-icon name="arrow-right" class="size-4" /></a>
                                @if ($payments->isNotEmpty())
                                    <div>
                                        <p class="mb-2 text-center text-[11px] font-bold tracking-[0.18em] text-muted uppercase">{{ Settings::get('cart_payment_title', 'Ödeme seçenekleri') }}</p>
                                        <ul class="grid gap-2.5 {{ $payments->count() > 1 ? 'grid-cols-2' : '' }}">
                                            @foreach ($payments as $p)
                                                @php($isCard = str_contains(mb_strtolower($p), 'kart'))
                                                <li class="pay-chip {{ $isCard ? 'pay-chip-card' : 'pay-chip-cash' }}" style="--d: {{ $loop->index }}">
                                                    <span class="pay-icon"><x-icon :name="$isCard ? 'card' : 'cash'" class="size-5" /></span>
                                                    <span class="text-[13px] leading-tight font-bold text-ink">{{ $p }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        </div>
                        @if ($trust)
                            <ul class="card divide-y divide-line">
                                @foreach ($trust as $t)
                                    <li class="flex items-center gap-3 px-5 py-3.5">
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary"><x-icon :name="$t['icon']" class="size-[1.1rem]" /></span>
                                        <span><span class="block text-sm font-bold">{{ $t['title'] }}</span>@if ($t['text'])<span class="block text-xs text-muted">{{ $t['text'] }}</span>@endif</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </aside>
                </div>
            @endif
        </div>

        @if ($suggestions->isNotEmpty())
            <x-product-row class="mt-6" :title="Settings::get('cart_suggest_title', 'Bunlar da ilginizi çekebilir')" :products="$suggestions" :url="route('products.index')" />
        @endif
    </div>
</x-layouts.store>
