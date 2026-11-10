{{-- Hesabım sekmeleri --}}
@php
    $tabs = [
        ['Hesabım', 'account.index', 'account.index', 'user'],
        ['Siparişlerim', 'account.orders', 'account.orders*', 'package'],
        ['Adreslerim', 'account.addresses.index', 'account.addresses.*', 'pin'],
        ['Favorilerim', 'account.favorites', 'account.favorites', 'heart'],
    ];
@endphp
<nav class="-mx-4 mt-6 flex gap-1 overflow-x-auto px-4 [scrollbar-width:none] sm:mx-0 sm:px-0" aria-label="Hesap menüsü">
    @foreach ($tabs as [$label, $route, $match, $icon])
        <a href="{{ route($route) }}" @if (request()->routeIs($match)) aria-current="page" @endif
           class="flex shrink-0 items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold {{ request()->routeIs($match) ? 'bg-primary text-white' : 'bg-white text-muted shadow-card hover:text-ink' }}">
            <x-icon name="{{ $icon }}" class="size-4" />{{ $label }}
        </a>
    @endforeach
</nav>
