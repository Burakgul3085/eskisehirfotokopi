{{-- Yardım ve SSS sayfalarının yan menüsü: yardım sayfaları, sipariş takibi, iletişim, yasal metinler; altında yardım kartı. --}}
@php
    $helpExtra = array_values(array_filter([
        ['Sipariş takibi', route('orders.track'), 'package'],
        $legalLinks->first() ? [$settings['pages_legal_title'] ?: $legalLinks->first()->title, $legalLinks->first()->url(), 'file'] : null,
    ]));
@endphp
<aside class="order-last min-w-0 space-y-5 lg:sticky lg:top-32 lg:order-first lg:self-start print:hidden">
    <x-info.side-nav :title="$settings['pages_help_nav_title']" :links="$helpLinks" :current="$page" :extra="$helpExtra" />
    @include('store.pages._help-card')
</aside>
