@php
    $logo = \App\Support\Settings::fileUrl('logo_path');
    $customer = auth('web')->user();
    $menu = app(\App\Domain\Catalog\CategoryTree::class)->nested(true, true);
    $headerLinks = \App\Support\Settings::links('header_links');
    $inlineRoots = array_slice($menu, 0, 6);
@endphp
<header class="sticky top-0 z-30 bg-white/95 shadow-[0_1px_0_rgb(15_23_42/0.06)] backdrop-blur">
    @if ($settings['announcement'])
        <div class="bg-primary-dark text-white">
            <div class="container-page py-2 text-center text-xs font-medium sm:text-sm">
                @if ($settings['announcement_link'])
                    <a href="{{ $settings['announcement_link'] }}" class="underline-offset-4 hover:underline">{{ $settings['announcement'] }}</a>
                @else
                    {{ $settings['announcement'] }}
                @endif
            </div>
        </div>
    @endif

    <div class="container-page flex items-center gap-3 py-3 sm:gap-6 sm:py-4">
        <button type="button" x-on:click="toggle" class="-ml-1 rounded-lg p-2 text-ink hover:bg-surface lg:hidden" aria-label="Menü">
            <x-icon name="menu" class="size-6" />
        </button>

        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2.5" aria-label="{{ $settings['site_name'] }} ana sayfa">
            @if ($logo)
                <img src="{{ $logo }}" alt="{{ $settings['site_name'] }}" class="h-9 w-auto sm:h-11">
            @else
                <span class="flex size-10 items-center justify-center rounded-xl bg-primary text-white shadow-sm"><x-icon name="printer" class="size-5" /></span>
                <span class="hidden font-display text-lg leading-tight font-extrabold text-ink sm:block">{{ $settings['site_name'] }}</span>
            @endif
        </a>

        <div class="hidden flex-1 md:block">@include('store.partials.search', ['id' => 'q'])</div>

        <nav class="ml-auto flex items-center gap-1 sm:gap-2" aria-label="Hesap">
            @if ($customer)
                <a href="{{ route('account.index') }}" class="flex items-center gap-2 rounded-xl px-2.5 py-2 text-sm font-medium hover:bg-surface">
                    <x-icon name="user" class="size-5" /><span class="hidden lg:inline">Hesabım</span>
                </a>
            @else
                <a href="{{ route('login') }}" class="flex items-center gap-2 rounded-xl px-2.5 py-2 text-sm font-medium hover:bg-surface">
                    <x-icon name="user" class="size-5" /><span class="hidden lg:inline">Giriş yap</span>
                </a>
            @endif
            @php($cartCount = \Illuminate\Support\Facades\Route::has('cart.index') ? app(\App\Domain\Cart\Cart::class)->count() : 0)
            <a href="{{ \Illuminate\Support\Facades\Route::has('cart.index') ? route('cart.index') : '#' }}" class="relative flex items-center gap-2 rounded-xl bg-primary-soft px-3 py-2 text-sm font-semibold text-primary-dark hover:bg-primary-line">
                <x-icon name="cart" class="size-5" /><span class="hidden lg:inline">Sepetim</span>
                <span data-cart-count @if (! $cartCount) hidden @endif class="absolute -top-1.5 -right-1.5 flex min-w-5 items-center justify-center rounded-full bg-accent px-1 text-[11px] font-bold text-ink">{{ $cartCount }}</span>
            </a>
        </nav>
    </div>

    <div class="container-page pb-3 md:hidden">@include('store.partials.search', ['id' => 'q-mobile'])</div>

    {{-- Kategori menüsü (masaüstü): fareyle veya klavyeyle (focus-within) açılır, JavaScript gerektirmez. --}}
    @if ($menu || $headerLinks)
        <nav class="relative hidden border-t border-line/70 lg:block" aria-label="Kategoriler">
            <div class="container-page flex items-center gap-1 text-sm font-medium">
                @if ($menu)
                    <div class="group">
                        <button type="button" class="flex items-center gap-2 rounded-lg bg-primary px-3.5 py-2 my-1.5 font-semibold text-white">
                            <x-icon name="menu" class="size-4" />Tüm kategoriler
                        </button>
                        <div class="invisible absolute inset-x-0 top-full z-40 border-t border-line bg-white opacity-0 shadow-lift transition group-focus-within:visible group-focus-within:opacity-100 group-hover:visible group-hover:opacity-100">
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
                        <a href="{{ route('category.show', $root['slug']) }}" class="flex items-center gap-1 rounded-lg px-3 py-2.5 hover:bg-surface hover:text-primary">
                            {{ $root['name'] }}@if ($root['children'])<x-icon name="chevron-down" class="size-3.5 text-muted" />@endif
                        </a>
                        @if ($root['children'])
                            <div class="invisible absolute inset-x-0 top-full z-40 border-t border-line bg-white opacity-0 shadow-lift transition group-focus-within:visible group-focus-within:opacity-100 group-hover:visible group-hover:opacity-100">
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
                        <a href="{{ $link['url'] }}" class="rounded-lg px-3 py-2.5 font-semibold text-primary-dark hover:bg-primary-soft">{{ $link['label'] }}</a>
                    @endforeach
                </div>
            </div>
        </nav>
    @endif
</header>
