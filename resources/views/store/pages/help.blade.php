{{-- Yardım sayfası (alt bilgide Yardım sütunu, ör. Teslimat ve İade): yardım yan menüsü; teslimat sayfasında güncel
     teslimat kurallarından özet kartlar, altında sayfa metni. --}}
@php
    use App\Support\Money;
    $doc = $page->document();
@endphp
<x-layouts.store :title="$page->title" :description="$page->meta_description ?: \Illuminate\Support\Str::limit(trim(strip_tags($doc['html'])), 160)">
    <x-info.hero :page="$page" />

    <div class="container-page grid gap-8 py-10 lg:grid-cols-[17rem_minmax(0,1fr)] print:block">
        @include('store.pages._help-aside')

        <div class="min-w-0 space-y-6">
            @isset($delivery)
                <div class="grid gap-4 sm:grid-cols-2 print:grid-cols-2 print:gap-3">
                    @if ($delivery['districts'])
                        <div class="info-card">
                            <span class="info-card-icon"><x-icon name="truck" class="size-6" /></span>
                            <p class="mt-4 font-display text-lg font-extrabold">{{ $settings['delivery_card_home'] }}</p>
                            @if ($settings['delivery_card_home_text'])<p class="mt-1 text-sm text-muted">{{ $settings['delivery_card_home_text'] }}</p>@endif
                            <dl class="info-facts">
                                <div><dt>Teslimat bölgesi</dt><dd>{{ $delivery['districts'] }} ilçe</dd></div>
                                <div><dt>Teslimat ücreti</dt><dd>{{ $delivery['fee_min'] == $delivery['fee_max'] ? ((float) $delivery['fee_min'] > 0 ? Money::format($delivery['fee_min']) : 'Ücretsiz') : Money::format($delivery['fee_min']).' – '.Money::format($delivery['fee_max']) }}</dd></div>
                                @if ($delivery['free_threshold'])<div><dt>Ücretsiz teslimat</dt><dd>{{ Money::format($delivery['free_threshold']) }} ve üzeri</dd></div>@endif
                                @if ($delivery['min_order'])<div><dt>En düşük sipariş</dt><dd>{{ Money::format($delivery['min_order']) }}</dd></div>@endif
                            </dl>
                            @if ($delivery['slots'])
                                <p class="mt-3 text-sm text-muted">Teslimat saatleri</p>
                                <ul class="mt-2 flex flex-wrap gap-2">
                                    @foreach ($delivery['slots'] as $slot)<li class="inline-flex items-center gap-1.5 rounded-full bg-surface px-3 py-1.5 text-sm font-semibold tabular-nums"><x-icon name="clock" class="size-3.5 text-primary" />{{ $slot }}</li>@endforeach
                                </ul>
                            @endif
                        </div>
                    @endif
                    @if ($delivery['payments'])
                        <div class="info-card">
                            <span class="info-card-icon"><x-icon name="cash" class="size-6" /></span>
                            <p class="mt-4 font-display text-lg font-extrabold">{{ $settings['delivery_card_pay'] }}</p>
                            @if ($settings['delivery_card_pay_text'])<p class="mt-1 text-sm text-muted">{{ $settings['delivery_card_pay_text'] }}</p>@endif
                            <ul class="mt-4 flex flex-wrap gap-2">
                                @foreach ($delivery['payments'] as $method)
                                    <li class="inline-flex items-center gap-1.5 rounded-full bg-primary-soft px-3 py-1.5 text-sm font-semibold text-primary-dark"><x-icon name="check" class="size-3.5" />{{ $method }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endisset

            <article class="rounded-3xl border border-line bg-white p-6 sm:p-10 print:border-0 print:p-0">
                <div class="prose-page prose-legal">{{ $doc['html'] }}</div>
                <x-info.print-note />
                <x-info.actions class="mt-10 border-t border-line pt-6" />
            </article>
        </div>
    </div>
</x-layouts.store>
