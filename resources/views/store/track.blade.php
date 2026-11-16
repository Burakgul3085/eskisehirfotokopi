@php
    use App\Support\Settings;
    $points = Settings::iconLines('track_points');
@endphp
<x-layouts.store :title="Settings::get('track_kicker', 'Sipariş takibi')" :description="Settings::get('track_intro')" :flush="true">
    {{--
        Koyu üst alan: solda başlık + sorgu formu, sağda 3D şehir adası (resources/js/scenes/order-track.js; minibüs dükkândan
        eve döngüyle paket götürür). Bütün yazılar Yönetim › Ayarlar › Genel › Sipariş takibi sayfası'ndan.
    --}}
    <section class="hero-3d surface-deep track-hero relative isolate overflow-hidden text-white">
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
            <div class="hero-grid absolute inset-0"></div>
            <div class="hero-spot absolute inset-0"></div>
        </div>
        <div class="container-page relative grid items-center gap-8 py-8 lg:grid-cols-[1fr_1.05fr] lg:py-14">
            <div class="relative z-10">
                <x-breadcrumb :items="[[Settings::get('track_kicker', 'Sipariş takibi'), null]]" class="hero-breadcrumb" />
                @if (Settings::get('track_kicker'))
                    <p class="hero-reveal mt-6 flex items-center gap-3 text-xs font-bold tracking-[0.25em] text-accent uppercase" style="--d: 0">
                        <span class="relative flex size-2.5"><span class="absolute inline-flex size-full animate-ping rounded-full bg-accent opacity-60"></span><span class="relative inline-flex size-2.5 rounded-full bg-accent"></span></span>
                        {{ Settings::get('track_kicker') }}
                    </p>
                @endif
                <h1 class="hero-reveal mt-4 text-4xl leading-[1.05] font-extrabold tracking-tight sm:text-5xl" style="--d: 1">
                    <span class="block bg-gradient-to-br from-white via-white to-white/70 bg-clip-text text-transparent">{{ Settings::get('track_title', 'Sipariş takibi') }}</span>
                    @if (Settings::get('track_title_accent'))<span class="block text-accent">{{ Settings::get('track_title_accent') }}</span>@endif
                </h1>
                @if (Settings::get('track_intro'))<p class="hero-reveal mt-4 max-w-lg text-base leading-relaxed text-white/75" style="--d: 2">{{ Settings::get('track_intro') }}</p>@endif

                {{-- Sorgu formu: koyu zeminde beyaz, hafif eğik 3D kart --}}
                <div class="track-card hero-reveal mt-7 max-w-lg rounded-3xl bg-white p-5 text-ink shadow-[0_40px_80px_-30px_rgba(0,0,0,0.65)] sm:p-7" style="--d: 3">
                    <div class="flex items-center gap-3">
                        <span class="track-card-icon flex size-11 items-center justify-center rounded-2xl text-white"><x-icon name="package" class="size-5" /></span>
                        <h2 class="font-display text-lg font-extrabold">{{ Settings::get('track_form_title', 'Sipariş takibi') }}</h2>
                    </div>
                    <form method="post" action="{{ route('orders.track') }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                        @csrf
                        <x-field name="number" label="Sipariş numarası" required placeholder="ES26…" autocomplete="off" class="uppercase" />
                        <x-field name="phone" type="tel" label="Telefon" required placeholder="05xx xxx xx xx" inputmode="tel" />
                        <button class="btn-primary group w-full py-3 sm:col-span-2">
                            <x-icon name="search" class="size-4" />{{ Settings::get('track_button', 'Sorgula') }}
                            <x-icon name="arrow-right" class="size-4 transition group-hover:translate-x-1" />
                        </button>
                    </form>
                    @auth('web')
                        <p class="mt-4 text-center text-sm"><a href="{{ route('account.orders') }}" class="font-semibold text-primary hover:underline">Siparişlerim</a></p>
                    @endauth
                </div>
            </div>

            <div class="relative">
                <div data-scene="order-track" class="relative h-[280px] sm:h-[360px] lg:h-[440px]">
                    <div class="hero-fallback pointer-events-none absolute inset-0 flex items-center justify-center transition-opacity duration-1000" aria-hidden="true">
                        <div class="size-56 rounded-full bg-gradient-to-br from-white/25 to-accent/20 blur-2xl md:size-72"></div>
                    </div>
                    <div class="hero-stage absolute inset-0">
                        <canvas data-scene-canvas class="block size-full" aria-hidden="true"></canvas>
                    </div>
                </div>
                @if ($points)
                    <ul class="relative z-10 -mt-4 grid gap-3 {{ [1 => 'sm:grid-cols-1', 2 => 'sm:grid-cols-2'][count($points)] ?? 'sm:grid-cols-3' }}">
                        @foreach ($points as $i => $point)
                            <li class="hero-card rounded-2xl border border-white/10 bg-white/[0.06] p-4 backdrop-blur-xl" style="--depth: {{ 6 + $i * 4 }}">
                                <span class="flex size-9 items-center justify-center rounded-xl bg-white/10 text-accent ring-1 ring-white/10"><x-icon :name="$point['icon']" class="size-4" /></span>
                                <p class="mt-3 text-sm font-bold">{{ $point['title'] }}</p>
                                @if ($point['text'])<p class="mt-0.5 text-xs leading-relaxed text-white/60">{{ $point['text'] }}</p>@endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </section>
</x-layouts.store>
