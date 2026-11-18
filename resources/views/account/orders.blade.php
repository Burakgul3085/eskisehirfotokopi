@php
    $filters = ['' => 'Tümü', 'devam' => 'Devam eden', 'teslim' => 'Teslim edilen', 'iptal' => 'İptal edilen'];
@endphp
<x-account-layout title="Siparişlerim" :text="$settings['account_orders_text']">
    @if ($counts[''] === 0)
        @include('account._empty', ['icon' => 'package', 'title' => 'Henüz siparişiniz yok.', 'text' => 'Verdiğiniz siparişler burada listelenir; her birinin durumunu adım adım takip edebilirsiniz.', 'cta' => ['Alışverişe başla', route('products.index')]])
    @else
        {{-- Durum süzgeci --}}
        <nav class="flex gap-2 overflow-x-auto [scrollbar-width:none]" aria-label="Sipariş durumu">
            @foreach ($filters as $key => $label)
                <a href="{{ route('account.orders', $key ? ['durum' => $key] : []) }}" @if ($filter === $key) aria-current="page" @endif
                   class="account-filter flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold {{ $filter === $key ? 'is-active' : '' }}">
                    {{ $label }}<span class="account-filter-count rounded-full px-1.5 text-xs">{{ $counts[$key] }}</span>
                </a>
            @endforeach
        </nav>

        @if ($orders->isEmpty())
            <div class="mt-5">@include('account._empty', ['icon' => 'search', 'title' => 'Bu durumda siparişiniz yok.', 'text' => null, 'cta' => ['Tüm siparişler', route('account.orders')]])</div>
        @else
            <div class="mt-5 space-y-4">
                @foreach ($orders as $order)
                    @include('account._order-card')
                @endforeach
            </div>
            <div class="mt-6">{{ $orders->links() }}</div>
        @endif
    @endif
</x-account-layout>
