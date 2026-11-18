{{-- Yardım sayfası (alt bilgide Yardım sütunu, ör. Teslimat ve İade): yardım yan menüsü; teslimat sayfasında güncel
     teslimat kurallarından üç özet kart (bölge ve ücret, saatler, ödeme) ve öne çıkanlar şeridi, altında sayfa metni
     (numaralı adımlar zaman çizelgesi olarak). --}}
@php
    use App\Support\Money;
    use App\Support\Settings;
    $doc = $page->document();
    $isDelivery = isset($delivery);
    $highlights = $isDelivery ? Settings::iconLines('delivery_highlights') : [];
@endphp
<x-layouts.store :title="$page->title" :description="$page->meta_description ?: \Illuminate\Support\Str::limit(trim(strip_tags($doc['html'])), 160)">
    <x-info.hero :page="$page" :kicker="$settings['help_kicker']" :lead="$isDelivery ? $settings['delivery_lead'] : null" />

    <div class="container-page grid gap-8 py-10 lg:grid-cols-[17.5rem_minmax(0,1fr)] lg:py-14 print:block print:py-4">
        @include('store.pages._help-aside')

        <div class="min-w-0 space-y-6">
            @if ($isDelivery)
                <div class="grid gap-4 md:grid-cols-3 print:grid-cols-3 print:gap-3">
                    @if ($delivery['districts'])
                        <div class="info-card flex flex-col">
                            <div class="flex items-center gap-3">
                                <span class="info-card-icon"><x-icon name="truck" class="size-6" /></span>
                                <p class="font-display text-lg font-extrabold">{{ $settings['delivery_card_home'] }}</p>
                            </div>
                            @if ($settings['delivery_card_home_text'])<p class="mt-3 text-sm leading-relaxed text-muted">{{ $settings['delivery_card_home_text'] }}</p>@endif
                            @if ($delivery['district_names'])
                                <ul class="mt-4 flex flex-wrap gap-1.5">
                                    @foreach ($delivery['district_names'] as $name)<li class="inline-flex items-center gap-1 rounded-full bg-surface px-2.5 py-1 text-xs font-semibold"><x-icon name="pin" class="size-3 text-primary" />{{ $name }}</li>@endforeach
                                </ul>
                            @endif
                            <dl class="info-facts mt-auto pt-4">
                                <div><dt>Teslimat ücreti</dt><dd>{{ $delivery['fee_min'] == $delivery['fee_max'] ? ((float) $delivery['fee_min'] > 0 ? Money::format($delivery['fee_min']) : 'Ücretsiz') : Money::format($delivery['fee_min']).' – '.Money::format($delivery['fee_max']) }}</dd></div>
                                @if ($delivery['free_threshold'])<div><dt>Ücretsiz teslimat</dt><dd>{{ Money::format($delivery['free_threshold']) }} ve üzeri</dd></div>@endif
                                @if ($delivery['min_order'])<div><dt>En düşük sipariş</dt><dd>{{ Money::format($delivery['min_order']) }}</dd></div>@endif
                            </dl>
                        </div>
                    @endif
                    @if ($delivery['slots'])
                        <div class="info-card flex flex-col">
                            <div class="flex items-center gap-3">
                                <span class="info-card-icon"><x-icon name="clock" class="size-6" /></span>
                                <p class="font-display text-lg font-extrabold">{{ $settings['delivery_card_slots'] }}</p>
                            </div>
                            @if ($settings['delivery_card_slots_text'])<p class="mt-3 text-sm leading-relaxed text-muted">{{ $settings['delivery_card_slots_text'] }}</p>@endif
                            <ul class="mt-4 space-y-2">
                                @foreach ($delivery['slots'] as $slot)
                                    <li class="delivery-slot"><span class="size-2 shrink-0 rounded-full bg-accent"></span><span class="tabular-nums">{{ $slot }}</span></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if ($delivery['payments'])
                        <div class="info-card flex flex-col">
                            <div class="flex items-center gap-3">
                                <span class="info-card-icon"><x-icon name="cash" class="size-6" /></span>
                                <p class="font-display text-lg font-extrabold">{{ $settings['delivery_card_pay'] }}</p>
                            </div>
                            @if ($settings['delivery_card_pay_text'])<p class="mt-3 text-sm leading-relaxed text-muted">{{ $settings['delivery_card_pay_text'] }}</p>@endif
                            <ul class="mt-4 space-y-2">
                                @foreach ($delivery['payments'] as $method)
                                    <li class="flex items-center gap-2.5 rounded-xl bg-primary-soft px-3 py-2.5 text-sm font-semibold text-primary-dark"><x-icon name="check" class="size-4" />{{ $method }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                @if ($highlights)
                    <ul class="delivery-highlights print:hidden">
                        @foreach ($highlights as $h)
                            <li>
                                <span class="delivery-highlight-icon"><x-icon :name="$h['icon']" class="size-5" /></span>
                                <span class="min-w-0"><span class="block font-display font-extrabold text-white">{{ $h['title'] }}</span>@if ($h['text'])<span class="block text-xs text-white/60">{{ $h['text'] }}</span>@endif</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endif

            <article class="legal-doc print:rounded-none print:border-0 print:p-0 print:shadow-none">
                <div class="prose-page prose-legal prose-help">{{ $doc['html'] }}</div>
                <x-info.print-note />
            </article>
        </div>
    </div>
</x-layouts.store>
