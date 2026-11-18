@props(['title', 'subtitle' => null, 'tabs' => false])
@php
    use App\Support\Settings;
    // Sol panel yazıları: Yönetim › Ayarlar › Genel › Giriş ve üyelik paneli
    $points = Settings::iconLines('auth_panel_points');
    $tab = request()->routeIs('register') ? 'register' : 'login';
@endphp
<x-layouts.store :title="$title">
    {{--
        İki bölmeli kart: solda koyu panel (başlık, küçük 3D kilit sahnesi — resources/js/scenes/auth.js — ve avantajlar),
        sağda form. Telefonda panel kısa bir üst banda dönüşür; avantaj listesi gizlenir.
    --}}
    <div class="page-tint relative">
        <div class="container-page py-8 sm:py-12 lg:py-16">
            <div data-auth-shell class="auth-shell mx-auto grid max-w-5xl overflow-clip rounded-[1.75rem] bg-white lg:grid-cols-[1fr_1.05fr]">
                <aside data-auth-panel class="hero-3d surface-deep relative isolate overflow-clip p-6 text-white sm:p-8 lg:p-10">
                    <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
                        <div class="hero-grid absolute inset-0"></div>
                        <div class="auth-glow absolute inset-0"></div>
                    </div>
                    {{-- Uzun formda (üye ol) panel içeriği ekranda kalır --}}
                    <div class="lg:sticky lg:top-40">
                    <div class="relative z-10">
                        @if (Settings::get('auth_panel_kicker'))
                            <p class="hero-reveal flex items-center gap-2.5 text-[11px] font-bold tracking-[0.25em] text-accent uppercase" style="--d: 0">
                                <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-accent opacity-60 motion-reduce:hidden"></span><span class="relative inline-flex size-2 rounded-full bg-accent"></span></span>
                                {{ Settings::get('auth_panel_kicker') }}
                            </p>
                        @endif
                        @if (Settings::get('auth_panel_title'))
                            <p class="hero-reveal mt-3 text-2xl leading-tight font-extrabold tracking-tight sm:text-3xl" style="--d: 1">
                                {{ Settings::get('auth_panel_title') }}
                                @if (Settings::get('auth_panel_accent')) <span class="text-accent">{{ Settings::get('auth_panel_accent') }}</span>@endif
                            </p>
                        @endif
                        @if (Settings::get('auth_panel_text'))<p class="hero-reveal mt-2.5 hidden max-w-sm text-sm leading-relaxed text-white/70 sm:block" style="--d: 2">{{ Settings::get('auth_panel_text') }}</p>@endif
                    </div>

                    <div data-scene="auth" class="relative -mx-6 my-2 h-44 sm:-mx-8 sm:h-56 lg:my-3 lg:h-72">
                        <div class="hero-fallback pointer-events-none absolute inset-0 flex items-center justify-center transition-opacity duration-1000" aria-hidden="true">
                            <div class="size-40 rounded-full bg-gradient-to-br from-white/25 to-accent/20 blur-2xl"></div>
                        </div>
                        <div class="hero-stage absolute inset-0">
                            <canvas data-scene-canvas class="block size-full" aria-hidden="true"></canvas>
                        </div>
                    </div>

                    @if ($points)
                        <ul class="relative z-10 hidden gap-2.5 lg:grid">
                            @foreach ($points as $i => $p)
                                <li class="hero-reveal flex items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.06] p-3 backdrop-blur-md" style="--d: {{ 3 + $i }}">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-accent to-accent/70 text-ink"><x-icon :name="$p['icon']" class="size-[1.1rem]" /></span>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-bold">{{ $p['title'] }}</span>
                                        @if ($p['text'])<span class="block text-xs text-white/60">{{ $p['text'] }}</span>@endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    </div>
                </aside>

                <div class="flex flex-col justify-center p-6 sm:p-10 lg:p-12">
                    @if ($tabs)
                        <nav class="auth-tabs mb-7 grid grid-cols-2 rounded-2xl bg-surface p-1 text-sm font-bold" aria-label="{{ $title }}">
                            <a href="{{ route('login') }}" @if ($tab === 'login') aria-current="page" @endif class="auth-tab">Giriş yap</a>
                            <a href="{{ route('register') }}" @if ($tab === 'register') aria-current="page" @endif class="auth-tab">Üye ol</a>
                        </nav>
                    @endif
                    <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $title }}</h1>
                    @if ($subtitle)<p class="mt-1.5 text-sm text-muted">{{ $subtitle }}</p>@endif
                    <x-flash class="mt-5" />
                    <div class="auth-form mt-6">{{ $slot }}</div>
                    @isset($below)<div class="mt-6 border-t border-dashed border-line pt-5 text-center text-sm text-muted">{{ $below }}</div>@endisset
                </div>
            </div>
        </div>
    </div>
</x-layouts.store>
