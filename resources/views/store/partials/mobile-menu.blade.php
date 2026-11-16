@php
    $customer = auth('web')->user();
    $menu = app(\App\Domain\Catalog\CategoryTree::class)->storefront(); // yalnız içinde yayında ürün olan kategoriler
@endphp
{{-- Header'daki backdrop-blur sabit konumlu çocukları header'a hapsettiği için mobil menü body düzeyindedir. --}}
<div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-ink/40 lg:hidden" x-on:click="close"></div>
<aside x-show="open" x-cloak x-transition:enter="transition duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
       class="fixed inset-y-0 left-0 z-50 w-80 max-w-[85vw] overflow-y-auto bg-white p-5 shadow-lift lg:hidden">
    <div class="mb-6 flex items-center justify-between">
        <span class="font-display text-lg font-bold">{{ $settings['site_name'] }}</span>
        <button type="button" x-on:click="close" class="rounded-lg p-2 hover:bg-surface" aria-label="Menüyü kapat"><x-icon name="x" /></button>
    </div>
    <nav class="space-y-1 text-[15px] font-medium">
        <a href="{{ route('home') }}" class="block rounded-lg px-3 py-2.5 hover:bg-surface">Ana sayfa</a>
        @foreach (\App\Support\Settings::links('header_links') as $link)
            <a href="{{ $link['url'] }}" class="block rounded-lg px-3 py-2.5 hover:bg-surface">{{ $link['label'] }}</a>
        @endforeach
        <a href="{{ $customer ? route('account.index') : route('login') }}" class="block rounded-lg px-3 py-2.5 hover:bg-surface">{{ $customer ? 'Hesabım' : 'Giriş yap / Üye ol' }}</a>
    </nav>

    @if ($menu)
        {{-- Kategoriler: arama + ana kategori (açılır) → bütün alt kategoriler; sayılar yayındaki ürünler --}}
        <div class="mt-6" x-data="mobileCategories">
            <p class="mb-2 px-3 text-[11px] font-semibold tracking-wider text-muted uppercase">{{ $settings['menu_all_label'] ?: \App\Support\Settings::DEFAULTS['menu_all_label'] }}</p>
            <label class="relative mb-2 block">
                <span class="sr-only">{{ $settings['menu_search_placeholder'] ?: \App\Support\Settings::DEFAULTS['menu_search_placeholder'] }}</span>
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted" />
                <input type="search" x-on:input="search" autocomplete="off" placeholder="{{ $settings['menu_search_placeholder'] ?: \App\Support\Settings::DEFAULTS['menu_search_placeholder'] }}" class="field-input w-full rounded-xl py-2.5 pl-9 text-sm">
            </label>
            <nav class="text-[15px]" aria-label="{{ $settings['menu_all_label'] ?: \App\Support\Settings::DEFAULTS['menu_all_label'] }}">
                @foreach ($menu as $root)
                    <details class="group" data-group data-key="{{ $root['key'] }}">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 rounded-lg px-3 py-2.5 font-semibold hover:bg-surface [&::-webkit-details-marker]:hidden">
                            <span class="min-w-0 flex-1 truncate">{{ $root['name'] }}</span>
                            <span class="text-xs font-medium text-muted">{{ $root['count'] }}</span>
                            <x-icon name="chevron-down" class="size-4 text-muted transition group-open:rotate-180" />
                        </summary>
                        <div class="ml-3 border-l border-line pl-2">
                            <a href="{{ route('category.show', $root['slug']) }}" class="flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-semibold text-primary hover:bg-surface">{{ $settings['menu_see_all'] ?: \App\Support\Settings::DEFAULTS['menu_see_all'] }}<x-icon name="arrow-right" class="size-3.5" /></a>
                            @foreach ($root['children'] as $child)
                                <a href="{{ route('category.show', $child['slug']) }}" data-child data-key="{{ $child['key'] }}" class="flex items-baseline justify-between gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface">
                                    <span class="min-w-0 truncate">{{ $child['name'] }}</span><span class="text-xs text-muted">{{ $child['count'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </details>
                @endforeach
            </nav>
            <p data-no-match hidden class="px-3 py-4 text-sm text-muted">{{ $settings['menu_no_match'] ?: \App\Support\Settings::DEFAULTS['menu_no_match'] }}</p>
        </div>
    @endif
</aside>
