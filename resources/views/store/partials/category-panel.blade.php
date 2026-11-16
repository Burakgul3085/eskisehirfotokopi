{{--
    "Tüm kategoriler" paneli (masaüstü): tıklayınca açılır; solda arama + ana kategoriler (harf rozeti, alt kategori sayısı),
    üstüne gelinen ana kategorinin bütün alt kategorileri sağda. Dışarı tıklayınca veya Esc ile kapanır.
    $menu: CategoryTree::storefront() — yalnız içinde yayında ürün olan kategoriler.
--}}
@php
    $badgeColors = ['bg-primary', 'bg-accent', 'bg-info', 'bg-success', 'bg-danger'];
    $letter = fn (string $name) => mb_strtoupper(mb_substr(trim($name), 0, 1), 'UTF-8');
    $label = $settings['menu_all_label'] ?: \App\Support\Settings::DEFAULTS['menu_all_label'];
@endphp
<div x-data="categoryMenu" x-on:keydown.escape.window="close" x-on:click.outside="close">
    <button type="button" x-on:click="toggle" aria-controls="category-panel" aria-expanded="false" x-bind:aria-expanded="expanded"
            class="nav-all my-1.5 flex items-center gap-2 rounded-xl px-3.5 py-2 font-semibold text-white">
        <x-icon name="menu" class="size-4" />{{ $label }}<x-icon name="chevron-down" class="size-3.5 opacity-80 transition" x-bind:class="chevronClass" />
    </button>

    <div id="category-panel" x-ref="panel" x-show="open" x-cloak x-transition.opacity.duration.200ms class="mega-panel absolute inset-x-0 top-full z-40">
        <div class="container-page flex h-[min(72vh,560px)]">
            {{-- Sol: arama + ana kategoriler --}}
            <div class="flex w-72 shrink-0 flex-col border-r border-line py-4 pr-3 xl:w-80">
                <label class="relative mb-3 block">
                    <span class="sr-only">{{ $settings['menu_search_placeholder'] ?: \App\Support\Settings::DEFAULTS['menu_search_placeholder'] }}</span>
                    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted" />
                    <input type="search" x-ref="search" x-on:input="search" autocomplete="off"
                           placeholder="{{ $settings['menu_search_placeholder'] ?: \App\Support\Settings::DEFAULTS['menu_search_placeholder'] }}"
                           class="field-input w-full rounded-xl py-2.5 pl-9 text-sm">
                </label>
                <ul class="-mr-1 flex-1 space-y-1 overflow-y-auto pr-1">
                    @foreach ($menu as $i => $root)
                        <li data-group data-key="{{ $root['key'] }}" data-child-keys="{{ collect($root['children'])->pluck('key')->implode('|') }}">
                            <a href="{{ route('category.show', $root['slug']) }}" data-id="{{ $root['id'] }}" x-on:mouseenter="show" x-on:focus="show"
                               class="cat-group flex items-center gap-3 rounded-xl px-3 py-2.5 text-[15px] font-semibold text-ink">
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg text-sm font-bold text-white {{ $badgeColors[$i % count($badgeColors)] }}" aria-hidden="true">{{ $letter($root['name']) }}</span>
                                <span class="min-w-0 flex-1 truncate">{{ $root['name'] }}</span>
                                @if ($root['children'])<span class="text-xs font-medium text-muted">{{ count($root['children']) }}</span>@endif
                                <x-icon name="chevron-right" class="size-4 text-muted" />
                            </a>
                        </li>
                    @endforeach
                </ul>
                <p data-no-match hidden class="px-3 py-6 text-center text-sm text-muted">{{ $settings['menu_no_match'] ?: \App\Support\Settings::DEFAULTS['menu_no_match'] }}</p>
            </div>

            {{-- Sağ: seçili ana kategorinin bütün alt kategorileri --}}
            <div class="min-w-0 flex-1 overflow-y-auto py-5 pl-8">
                @foreach ($menu as $i => $root)
                    <section data-pane="{{ $root['id'] }}" @if ($i) hidden @endif aria-label="{{ $root['name'] }}">
                        <div class="flex items-center gap-4 border-b border-line pb-4">
                            <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl font-display text-2xl font-extrabold text-white {{ $badgeColors[$i % count($badgeColors)] }}" aria-hidden="true">{{ $letter($root['name']) }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="font-display text-xl font-extrabold text-ink">{{ $root['name'] }}</p>
                                <p class="text-xs text-muted">{{ number_format($root['count'], 0, ',', '.') }} ürün</p>
                            </div>
                            <a href="{{ route('category.show', $root['slug']) }}" class="btn-primary btn-sm shrink-0">
                                {{ $settings['menu_see_all'] ?: \App\Support\Settings::DEFAULTS['menu_see_all'] }}<x-icon name="arrow-right" class="size-4" />
                            </a>
                        </div>
                        @if ($root['children'])
                            <ul class="mt-4 grid grid-cols-2 gap-x-8 gap-y-1 xl:grid-cols-3">
                                @foreach ($root['children'] as $child)
                                    <li data-child data-key="{{ $child['key'] }}">
                                        <a href="{{ route('category.show', $child['slug']) }}" class="cat-child flex items-baseline justify-between gap-3 rounded-lg px-2 py-2 text-sm text-ink">
                                            <span class="flex min-w-0 items-center gap-2"><span class="size-1.5 shrink-0 rounded-full bg-primary/60" aria-hidden="true"></span><span class="truncate">{{ $child['name'] }}</span></span>
                                            <span class="shrink-0 text-xs text-muted">{{ $child['count'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @endforeach
            </div>
        </div>
    </div>
</div>
