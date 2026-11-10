@php
    $logo = \App\Support\Settings::fileUrl('logo_path');
    $customer = auth('web')->user();
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

        {{-- Arama: Aşama 4'te anlık öneri paneli eklenir --}}
        <form action="{{ url('/') }}" method="get" role="search" class="relative hidden flex-1 md:block">
            <label for="q" class="sr-only">Ara</label>
            <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="Kitap, yayınevi, yazar veya ISBN ara…"
                   class="w-full rounded-2xl border border-line bg-surface py-3 pr-14 pl-5 text-[15px] transition placeholder:text-slate-400 focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/12 focus:outline-none">
            <button class="absolute top-1.5 right-1.5 flex size-10 items-center justify-center rounded-xl bg-primary text-white hover:bg-primary-hover" aria-label="Ara">
                <x-icon name="search" class="size-5" />
            </button>
        </form>

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
            <a href="#" class="relative flex items-center gap-2 rounded-xl bg-primary-soft px-3 py-2 text-sm font-semibold text-primary-dark hover:bg-primary-line">
                <x-icon name="cart" class="size-5" /><span class="hidden lg:inline">Sepetim</span>
            </a>
        </nav>
    </div>

    <div class="container-page pb-3 md:hidden">
        <form action="{{ url('/') }}" method="get" role="search" class="relative">
            <input name="q" type="search" value="{{ request('q') }}" placeholder="Kitap, yayınevi veya ISBN ara…" aria-label="Ara"
                   class="w-full rounded-xl border border-line bg-surface py-2.5 pr-11 pl-4 text-[15px] focus:border-primary focus:bg-white focus:outline-none">
            <x-icon name="search" class="pointer-events-none absolute top-3 right-3.5 size-5 text-muted" />
        </form>
    </div>

</header>
