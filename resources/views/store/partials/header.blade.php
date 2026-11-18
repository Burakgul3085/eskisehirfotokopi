@php
    use App\Support\Settings;

    $logo = Settings::fileUrl('logo_path');
    $customer = auth('web')->user();
    // Yalnız içinde yayında ürün olan kategoriler (boş kategori menüde görünmez)
    $menu = app(\App\Domain\Catalog\CategoryTree::class)->storefront();
    $headerLinks = Settings::links('header_links');
    $whatsapp = Settings::whatsappUrl();

    // Kayan üst bant: duyuru (varsa) başta, ardından Ayarlar › Genel › Kayan üst bant satırları
    $ticker = Settings::iconLines('topbar_items');
    if ($settings['announcement']) {
        array_unshift($ticker, ['icon' => 'chat', 'title' => $settings['announcement'], 'text' => '', 'url' => $settings['announcement_link']]);
    }

    // Menü bağlantılarına adreslerine göre simge (tanınmayan adreste simgesiz)
    $navIcons = ['/urunler' => 'book', '/yayinevleri' => 'layers', '/fotokopi' => 'printer', '/siparis-takip' => 'truck', '/iletisim' => 'phone', '/' => 'home'];
    $current = '/'.ltrim(request()->path(), '/');
@endphp
{{-- Kayan üst bant yapışkan header'ın dışında: sayfayla birlikte yukarı kayıp gider (yükseklik animasyonu yok → zıplama yok).
     Header: ana satır (logo, arama, hesap, sepet) ve menü satırı ayrı cam zeminlerde. Kaydırınca zemin buzlu cama döner;
     aşağı inerken menü satırı ana satırın altına kayarak gizlenir, yukarı çıkınca geri gelir (resources/js/app.js).
     Header'ın kutusu hiç boyut değiştirmez; yalnız saydamlık ve transform değişir. --}}
@if ($ticker)
    <div class="header-ticker relative overflow-hidden text-white" aria-label="Duyurular">
        <div class="ticker-track flex w-max items-center">
            {{-- Kesintisiz kayma için liste iki kez çizilir; ikinci kopya ekran okuyuculardan gizlenir --}}
            @foreach ([false, true] as $copy)
                <ul class="flex shrink-0 items-center" @if ($copy) aria-hidden="true" @endif>
                    @foreach ($ticker as $item)
                        <li class="flex items-center">
                            @php($tag = ! empty($item['url']) ? 'a' : 'span')
                            <{{ $tag }} @if ($tag === 'a') href="{{ $item['url'] }}" @if ($copy) tabindex="-1" @endif @endif class="flex items-center gap-2.5 px-6 py-2 text-[13px] font-semibold whitespace-nowrap {{ $tag === 'a' ? 'hover:underline underline-offset-4' : '' }}">
                                <x-icon :name="$item['icon']" class="size-4 text-accent" />
                                <span>{{ $item['title'] }}</span>
                                @if ($item['text'])<span class="ticker-badge rounded-md px-2 py-0.5 text-[11px] font-extrabold tracking-wider">{{ $item['text'] }}</span>@endif
                            </{{ $tag }}>
                            <span class="size-1.5 rounded-full bg-white/30" aria-hidden="true"></span>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </div>
    </div>
@endif

<header data-site-header class="site-header sticky top-0 z-30">
    <div class="header-bar relative z-10">
        <div class="header-glow pointer-events-none absolute inset-0 -z-10 overflow-hidden" aria-hidden="true"></div>
        <div class="header-main container-page flex items-center gap-3 py-3 sm:gap-5 sm:py-4 xl:gap-8">
            <button type="button" x-on:click="toggle" class="-ml-1 rounded-xl p-2 text-ink hover:bg-surface lg:hidden" aria-label="Menü">
                <x-icon name="menu" class="size-6" />
            </button>

            <a href="{{ route('home') }}" class="header-brand group/brand flex shrink-0 items-center gap-3" aria-label="{{ $settings['site_name'] }} ana sayfa">
                <x-brand :logo="$logo" :name="$settings['site_name']" />
            </a>

            <div class="header-search hidden flex-1 md:block">@include('store.partials.search', ['id' => 'q'])</div>

            <nav class="ml-auto flex items-center gap-2 sm:gap-3" aria-label="Hesap">
                <a href="{{ $customer ? route('account.index') : route('login') }}" class="header-account group/acc flex items-center gap-2.5 rounded-2xl p-1 pr-1 xl:pr-3">
                    <span class="flex size-10 items-center justify-center rounded-full bg-surface text-ink ring-1 ring-line transition group-hover/acc:bg-primary group-hover/acc:text-white group-hover/acc:ring-primary sm:size-11">
                        <x-icon name="user" class="size-5" />
                    </span>
                    <span class="hidden leading-tight xl:block">
                        <span class="block text-xs text-muted">{{ $customer ? \Illuminate\Support\Str::of($customer->name)->before(' ') : Settings::get('header_welcome') }}</span>
                        <span class="block text-sm font-bold text-ink">{{ $customer ? 'Hesabım' : 'Giriş yap' }}</span>
                    </span>
                </a>
                @php($cartCount = \Illuminate\Support\Facades\Route::has('cart.index') ? app(\App\Domain\Cart\Cart::class)->count() : 0)
                <a href="{{ \Illuminate\Support\Facades\Route::has('cart.index') ? route('cart.index') : '#' }}" class="header-cart relative flex items-center gap-2.5 rounded-2xl px-3.5 py-3 text-sm font-bold text-white sm:px-5">
                    <x-icon name="cart" class="size-5" /><span class="hidden lg:inline">Sepetim</span>
                    <span data-cart-count @if (! $cartCount) hidden @endif class="cart-badge absolute -top-1.5 -right-1.5 flex min-w-5 items-center justify-center rounded-full bg-accent px-1 text-[11px] font-bold text-ink ring-2 ring-white">{{ $cartCount }}</span>
                </a>
            </nav>
        </div>

        <div class="header-search container-page pb-3 md:hidden">@include('store.partials.search', ['id' => 'q-mobile'])</div>
        <div class="header-bar-line pointer-events-none absolute inset-x-0 bottom-0 h-px" aria-hidden="true"></div>
    </div>

        {{-- Masaüstü menü satırı: "Tüm kategoriler" paneli + yönetimden gelen bağlantılar (simgeli, bulunduğun sayfa vurgulu) + WhatsApp --}}
        @if ($menu || $headerLinks || $whatsapp)
            <nav data-header-nav class="header-nav relative hidden lg:block" aria-label="Ana menü">
                <div class="container-page flex items-center gap-2 py-1.5 text-sm font-medium">
                    @if ($menu)
                        @include('store.partials.category-panel', ['menu' => $menu])
                    @endif
                    {{-- Bağlantılar kalan alana eşit aralıkla yayılır; WhatsApp düğmesi yoksa satırın sonuna kadar --}}
                    <div class="flex flex-1 items-center justify-evenly gap-1 {{ $menu ? 'ml-3' : '' }} {{ $whatsapp ? 'mr-3' : '' }}">
                        @foreach ($headerLinks as $link)
                            @php($path = parse_url($link['url'], PHP_URL_PATH) ?: '/')
                            @php($active = $path === '/' ? $current === '/' : str_starts_with($current, $path))
                            <a href="{{ $link['url'] }}" @if ($active) aria-current="page" @endif
                               class="nav-link flex items-center gap-2 rounded-xl px-2 py-2.5 font-semibold whitespace-nowrap xl:px-3.5 {{ $active ? 'nav-link-active' : '' }}">
                                @isset($navIcons[$path])<x-icon :name="$navIcons[$path]" class="nav-icon size-4" />@endisset
                                {{ $link['label'] }}
                            </a>
                        @endforeach
                    </div>
                    @if ($whatsapp)
                        <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="header-support group/wa ml-auto flex shrink-0 items-center gap-3 rounded-2xl border border-line bg-white py-1.5 pr-4 pl-2 transition hover:border-[#25d366]/50 hover:shadow-[0_12px_30px_-16px_#25d366]">
                            <span class="relative flex size-9 items-center justify-center rounded-xl bg-[#25d366] text-white">
                                <x-icon name="whatsapp" class="size-5" />
                                <span class="absolute -top-0.5 -right-0.5 flex size-3"><span class="absolute inline-flex size-full animate-ping rounded-full bg-[#25d366] opacity-60 motion-reduce:hidden"></span><span class="relative inline-flex size-3 rounded-full bg-[#25d366] ring-2 ring-white"></span></span>
                            </span>
                            <span class="leading-tight">
                                <span class="block text-[10px] font-bold tracking-wider text-muted uppercase">{{ Settings::get('header_support_label') }}</span>
                                <span class="block text-sm font-bold text-ink">WhatsApp</span>
                            </span>
                        </a>
                    @endif
                </div>
                <div class="header-line pointer-events-none absolute inset-x-0 bottom-0 h-px" aria-hidden="true"></div>
            </nav>
        @endif
</header>
