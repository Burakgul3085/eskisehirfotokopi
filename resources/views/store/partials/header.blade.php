@php
    $logo = \App\Support\Settings::fileUrl('logo_path');
    $customer = auth('web')->user();
    $menu = app(\App\Domain\Catalog\CategoryTree::class)->nested(true, true);
    $headerLinks = \App\Support\Settings::links('header_links');
    $inlineRoots = array_slice($menu, 0, 6);
@endphp
{{-- Cam header: fareyi izleyen ışık, akan alt çizgi, 3D logo; kaydırınca süzülen gölge (resources/js/app.js → data-site-header). --}}
<header data-site-header data-scene="header" class="site-header sticky top-0 z-30">
    <div class="header-glow pointer-events-none absolute inset-0 -z-10 overflow-hidden" aria-hidden="true"></div>
    <canvas data-scene-canvas class="scene-canvas header-canvas pointer-events-none absolute inset-0 -z-10 block size-full" aria-hidden="true"></canvas>
    <div class="header-line pointer-events-none absolute inset-x-0 bottom-0 h-px" aria-hidden="true"></div>
    @if ($settings['announcement'])
        <div class="header-announce relative overflow-hidden bg-primary-dark text-white">
            <div class="container-page py-2 text-center text-xs font-medium sm:text-sm">
                @if ($settings['announcement_link'])
                    <a href="{{ $settings['announcement_link'] }}" class="underline-offset-4 hover:underline">{{ $settings['announcement'] }}</a>
                @else
                    {{ $settings['announcement'] }}
                @endif
            </div>
        </div>
    @endif

    <div class="header-main container-page flex items-center gap-3 py-3 sm:gap-6 sm:py-4">
        <button type="button" x-on:click="toggle" class="-ml-1 rounded-lg p-2 text-ink hover:bg-surface lg:hidden" aria-label="Menü">
            <x-icon name="menu" class="size-6" />
        </button>

        <a href="{{ route('home') }}" class="header-brand group/brand flex shrink-0 items-center gap-3" aria-label="{{ $settings['site_name'] }} ana sayfa">
            @if ($logo)
                <img src="{{ $logo }}" alt="{{ $settings['site_name'] }}" class="h-9 w-auto transition duration-500 group-hover/brand:scale-105 sm:h-11">
            @else
                <span class="logo-tile relative flex size-11 items-center justify-center rounded-2xl text-white">
                    <span class="logo-paper" aria-hidden="true"></span>
                    <x-icon name="printer" class="relative size-5" />
                </span>
                <span class="hidden leading-tight sm:block">
                    <span class="block font-display text-lg font-extrabold tracking-tight text-ink">{{ $settings['site_name'] }}</span>
                    @if ($settings['site_tagline'])<span class="header-tagline hidden max-w-72 truncate text-[11px] font-medium text-muted xl:block">{{ $settings['site_tagline'] }}</span>@endif
                </span>
            @endif
        </a>

        <div class="header-search hidden flex-1 md:block">@include('store.partials.search', ['id' => 'q'])</div>

        <nav class="ml-auto flex items-center gap-1 sm:gap-2" aria-label="Hesap">
            @if ($customer)
                <a href="{{ route('account.index') }}" class="header-action flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold">
                    <x-icon name="user" class="size-5" /><span class="hidden lg:inline">Hesabım</span>
                </a>
            @else
                <a href="{{ route('login') }}" class="header-action flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold">
                    <x-icon name="user" class="size-5" /><span class="hidden lg:inline">Giriş yap</span>
                </a>
            @endif
            @php($cartCount = \Illuminate\Support\Facades\Route::has('cart.index') ? app(\App\Domain\Cart\Cart::class)->count() : 0)
            <a href="{{ \Illuminate\Support\Facades\Route::has('cart.index') ? route('cart.index') : '#' }}" class="header-cart relative flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-semibold text-white">
                <x-icon name="cart" class="size-5" /><span class="hidden lg:inline">Sepetim</span>
                <span data-cart-count @if (! $cartCount) hidden @endif class="cart-badge absolute -top-1.5 -right-1.5 flex min-w-5 items-center justify-center rounded-full bg-accent px-1 text-[11px] font-bold text-ink ring-2 ring-white">{{ $cartCount }}</span>
            </a>
        </nav>
    </div>

    <div class="header-search container-page pb-3 md:hidden">@include('store.partials.search', ['id' => 'q-mobile'])</div>

    {{-- Kategori menüsü (masaüstü): fareyle veya klavyeyle (focus-within) açılır, JavaScript gerektirmez. --}}
    @if ($menu || $headerLinks)
        <nav class="header-nav relative hidden lg:block" aria-label="Kategoriler">
            <div class="container-page flex items-center gap-1 text-sm font-medium">
                @if ($menu)
                    <div class="group">
                        <button type="button" class="nav-all my-1.5 flex items-center gap-2 rounded-xl px-3.5 py-2 font-semibold text-white">
                            <x-icon name="menu" class="size-4" />Tüm kategoriler
                        </button>
                        <div class="mega-panel invisible absolute inset-x-0 top-full z-40 opacity-0 group-focus-within:visible group-focus-within:opacity-100 group-hover:visible group-hover:opacity-100">
                            <div class="container-page grid max-h-[70vh] grid-cols-4 gap-x-8 gap-y-6 overflow-y-auto py-6 xl:grid-cols-5">
                                @foreach ($menu as $root)
                                    <div>
                                        <a href="{{ route('category.show', $root['slug']) }}" class="font-display font-bold text-ink hover:text-primary">{{ $root['name'] }}</a>
                                        @if ($root['children'])
                                            <ul class="mt-2 space-y-1.5 text-[13px] text-muted">
                                                @foreach (array_slice($root['children'], 0, 8) as $child)
                                                    <li><a href="{{ route('category.show', $child['slug']) }}" class="hover:text-primary">{{ $child['name'] }}</a></li>
                                                @endforeach
                                                @if (count($root['children']) > 8)
                                                    <li><a href="{{ route('category.show', $root['slug']) }}" class="font-semibold text-primary">Tümü ({{ count($root['children']) }})</a></li>
                                                @endif
                                            </ul>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                @foreach ($inlineRoots as $root)
                    <div class="group">
                        <a href="{{ route('category.show', $root['slug']) }}" class="nav-link flex items-center gap-1 rounded-lg px-3 py-2.5">
                            {{ $root['name'] }}@if ($root['children'])<x-icon name="chevron-down" class="size-3.5 text-muted" />@endif
                        </a>
                        @if ($root['children'])
                            <div class="mega-panel invisible absolute inset-x-0 top-full z-40 opacity-0 group-focus-within:visible group-focus-within:opacity-100 group-hover:visible group-hover:opacity-100">
                                <div class="container-page grid max-h-[70vh] grid-cols-4 gap-x-8 gap-y-6 overflow-y-auto py-6">
                                    @foreach ($root['children'] as $child)
                                        <div>
                                            <a href="{{ route('category.show', $child['slug']) }}" class="font-semibold text-ink hover:text-primary">{{ $child['name'] }}</a>
                                            @if ($child['children'])
                                                <ul class="mt-2 space-y-1.5 text-[13px] text-muted">
                                                    @foreach (array_slice($child['children'], 0, 8) as $leaf)
                                                        <li><a href="{{ route('category.show', $leaf['slug']) }}" class="hover:text-primary">{{ $leaf['name'] }}</a></li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach

                <div class="ml-auto flex items-center gap-1">
                    @foreach ($headerLinks as $link)
                        <a href="{{ $link['url'] }}" class="nav-link nav-link-strong rounded-lg px-3 py-2.5 font-semibold">{{ $link['label'] }}</a>
                    @endforeach
                </div>
            </div>
        </nav>
    @endif
</header>
