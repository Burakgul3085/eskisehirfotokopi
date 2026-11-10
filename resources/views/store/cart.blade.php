<x-layouts.store title="Sepetim" :noindex="true">
    <div class="container-page py-6 sm:py-10">
        <x-breadcrumb :items="[['Sepetim', null]]" />
        <h1 class="mt-4 text-2xl font-extrabold sm:text-3xl">Sepetim</h1>
        <x-flash class="mt-5" />

        @if ($lines === [])
            <div class="card mt-6 flex flex-col items-center gap-4 px-6 py-16 text-center">
                <span class="flex size-16 items-center justify-center rounded-full bg-primary-soft text-primary"><x-icon name="cart" class="size-8" /></span>
                <p class="text-lg font-semibold">Sepetiniz boş</p>
                <p class="max-w-md text-sm text-muted">{{ $settings['delivery_info'] }}</p>
                <div class="flex flex-wrap justify-center gap-3">
                    <a href="{{ route('products.index') }}" class="btn-primary">Ürünlere göz at</a>
                    @if (Route::has('print.index'))<a href="{{ route('print.index') }}" class="btn-ghost"><x-icon name="printer" class="size-4" />Belge yazdır</a>@endif
                </div>
            </div>
        @else
            <div class="mt-6 grid items-start gap-6 lg:grid-cols-[1fr_360px]">
                <div class="card divide-y divide-line">
                    @foreach ($lines as $line)
                        <div class="flex gap-4 p-4 sm:p-5">
                            <a href="{{ $line->url ?? '#' }}" class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-surface sm:size-24">
                                @if ($line->image)<img src="{{ $line->image }}" alt="" class="max-h-full max-w-full object-contain p-1" loading="lazy">
                                @else<x-icon name="{{ $line->kind === 'print' ? 'printer' : 'book' }}" class="size-8 text-slate-300" />@endif
                            </a>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        @if ($line->url)<a href="{{ $line->url }}" class="line-clamp-2 font-semibold hover:text-primary">{{ $line->title }}</a>
                                        @else<p class="line-clamp-2 font-semibold">{{ $line->title }}</p>@endif
                                        <p class="mt-0.5 text-xs text-muted">{{ collect([$line->subtitle, $line->printTypeLabel()])->filter()->implode(' · ') }}</p>
                                    </div>
                                    <form method="post" action="{{ route('cart.remove') }}">@csrf @method('delete')
                                        <input type="hidden" name="key" value="{{ $line->key }}">
                                        <button class="rounded-lg p-1.5 text-muted hover:bg-danger-soft hover:text-danger" aria-label="Sepetten çıkar"><x-icon name="trash" class="size-4" /></button>
                                    </form>
                                </div>
                                <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                                    @if ($line->maxQuantity > 1)
                                        <form method="post" action="{{ route('cart.update') }}" class="flex items-center gap-2">@csrf @method('patch')
                                            <input type="hidden" name="key" value="{{ $line->key }}">
                                            <label class="sr-only" for="q{{ $loop->index }}">Adet</label>
                                            <select id="q{{ $loop->index }}" name="quantity" class="field-input w-20 py-1.5" data-autosubmit>
                                                @for ($q = 1; $q <= max($line->maxQuantity, $line->quantity); $q++)
                                                    <option value="{{ $q }}" @selected($q === $line->quantity)>{{ $q }}</option>
                                                @endfor
                                            </select>
                                            <span class="text-xs text-muted">× {{ \App\Support\Money::format($line->unitPrice) }}</span>
                                            <noscript><button class="btn-ghost btn-sm">Güncelle</button></noscript>
                                        </form>
                                    @else
                                        <span class="text-xs text-muted">{{ $line->quantity }} adet</span>
                                    @endif
                                    <span class="font-display text-lg font-bold">{{ \App\Support\Money::format($line->total()) }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <aside class="card space-y-4 p-5 sm:p-6 lg:sticky lg:top-24">
                    <h2 class="text-lg font-bold">Sipariş özeti</h2>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-muted">Ara toplam</dt><dd class="font-semibold">{{ \App\Support\Money::format($subtotal) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Teslimat</dt><dd class="text-muted">{{ $remaining === null && $threshold !== null ? 'Ücretsiz' : 'Adrese göre hesaplanır' }}</dd></div>
                    </dl>
                    @if ($remaining !== null)
                        @php($pct = $threshold ? min(100, (int) round($subtotal / $threshold * 100)) : 0)
                        <div class="rounded-xl bg-primary-soft p-3 text-sm text-primary-dark">
                            <p><strong>{{ \App\Support\Money::format($remaining) }}</strong> daha ekleyin, teslimat ücretsiz olsun.</p>
                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white"><div class="h-full rounded-full bg-primary" style="width: {{ $pct }}%"></div></div>
                        </div>
                    @endif
                    @if ($minOrder !== null && $subtotal < $minOrder)
                        <p class="rounded-xl bg-accent-soft p-3 text-sm text-accent-ink">En düşük sipariş tutarı {{ \App\Support\Money::format($minOrder) }}. Sepetinize {{ \App\Support\Money::format($minOrder - $subtotal) }} daha ekleyin.</p>
                    @endif
                    <a href="{{ route('checkout.show') }}" class="btn-primary w-full py-3 text-base">Siparişi tamamla <x-icon name="chevron-right" class="size-4" /></a>
                    <p class="flex items-center gap-2 text-xs text-muted"><x-icon name="cash" class="size-4" />{{ collect(app(\App\Domain\Delivery\DeliveryRules::class)->paymentMethods())->implode(' veya ') }}</p>
                    <a href="{{ route('products.index') }}" class="block text-center text-sm font-semibold text-primary hover:underline">Alışverişe devam et</a>
                </aside>
            </div>
        @endif
    </div>
</x-layouts.store>
