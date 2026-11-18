@props(['title', 'heading' => null, 'text' => null])
@php
    /**
     * Hesabım ortak düzeni: solda profil kartı ve kenar menü, sağda sayfa başlığı ve içerik.
     * Kullanım: <x-account-layout title="Siparişlerim" :text="$settings['account_orders_text']"> … </x-account-layout>
     */
    $user = auth('web')->user();
    $words = preg_split('/\s+/u', trim((string) $user->name)) ?: [];
    $initials = mb_strtoupper(collect([$words[0] ?? '', count($words) > 1 ? end($words) : ''])->filter()->map(fn ($w) => mb_substr($w, 0, 1))->implode('')) ?: '?';
    $openOrders = $user->orders()->whereNotIn('status', ['delivered', 'cancelled'])->count();
    $favoriteCount = $user->favorites()->visible()->count();
    $menu = [
        ['Genel bakış', 'account.index', 'account.index', 'home', null],
        ['Siparişlerim', 'account.orders', 'account.orders*', 'package', $openOrders ?: null],
        ['Adreslerim', 'account.addresses.index', 'account.addresses.*', 'pin', null],
        ['Favorilerim', 'account.favorites', 'account.favorites', 'heart', $favoriteCount ?: null],
        [$settings['account_reviews_title'], 'account.reviews', 'account.reviews', 'star', null],
        ['Mesajlarım', 'account.messages', 'account.messages', 'chat', null],
        ['Bilgilerim ve parola', 'account.settings', 'account.settings', 'lock', null],
    ];
@endphp
<x-layouts.store :title="$title" :noindex="true">
    <div class="account-shell">
        <div class="container-page grid items-start gap-6 py-8 lg:grid-cols-[290px_1fr] lg:gap-8 lg:py-12">
            <aside class="min-w-0 space-y-4 lg:sticky lg:top-40">
                {{-- Profil kartı --}}
                <div class="account-card surface-deep relative isolate overflow-hidden rounded-3xl p-6 text-white">
                    <span class="account-shape account-shape-a" aria-hidden="true"></span>
                    <span class="account-shape account-shape-b" aria-hidden="true"></span>
                    <div class="flex items-center gap-4 lg:block">
                        <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-white/10 font-display text-xl font-extrabold ring-1 ring-white/20 backdrop-blur">{{ $initials }}</span>
                        <div class="min-w-0 lg:mt-4">
                            <p class="truncate font-display text-lg font-extrabold">{{ $user->name }}</p>
                            <p class="truncate text-sm text-white/60">{{ $user->email }}</p>
                        </div>
                    </div>
                    <p class="mt-4 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-semibold ring-1 ring-white/15">
                        <x-icon name="star" class="size-3.5 text-accent" />{{ $user->created_at->locale('tr')->translatedFormat('F Y') }} tarihinden beri üye
                    </p>
                </div>

                {{-- Kenar menü (küçük ekranda yatay kaydırılır) --}}
                <nav class="card p-2" aria-label="Hesap menüsü">
                    <ul class="-mx-1 flex gap-1 overflow-x-auto px-1 [scrollbar-width:none] lg:mx-0 lg:block lg:space-y-1 lg:overflow-visible lg:px-0">
                        @foreach ($menu as [$label, $route, $match, $icon, $badge])
                            @php($on = request()->routeIs($match))
                            <li class="shrink-0">
                                <a href="{{ route($route) }}" @if ($on) aria-current="page" @endif
                                   class="account-link flex items-center gap-3 rounded-2xl px-3.5 py-2.5 text-sm font-semibold {{ $on ? 'is-active' : '' }}">
                                    <span class="account-link-icon flex size-8 shrink-0 items-center justify-center rounded-xl"><x-icon :name="$icon" class="size-4" /></span>
                                    <span class="whitespace-nowrap">{{ $label }}</span>
                                    @if ($badge)<span class="account-link-badge ml-auto rounded-full px-2 py-0.5 text-xs font-bold">{{ $badge }}</span>@endif
                                </a>
                            </li>
                        @endforeach
                        <li class="shrink-0 lg:mt-1 lg:border-t lg:border-line lg:pt-1">
                            <form method="post" action="{{ route('logout') }}">@csrf
                                <button class="account-link account-logout flex w-full items-center gap-3 rounded-2xl px-3.5 py-2.5 text-sm font-semibold text-danger">
                                    <span class="account-link-icon flex size-8 shrink-0 items-center justify-center rounded-xl"><x-icon name="logout" class="size-4" /></span>
                                    <span class="whitespace-nowrap">Çıkış yap</span>
                                </button>
                            </form>
                        </li>
                    </ul>
                </nav>
            </aside>

            <main class="min-w-0">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-xs font-bold tracking-[0.25em] text-accent-ink uppercase">{{ $settings['account_kicker'] }}</p>
                        <h1 class="mt-1 text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $heading ?? $title }}</h1>
                        @if ($text)<p class="mt-1.5 text-muted">{{ $text }}</p>@endif
                    </div>
                    @isset($actions)<div class="flex flex-wrap gap-2">{{ $actions }}</div>@endisset
                </div>
                <x-flash class="mt-6" />
                <div class="mt-6">{{ $slot }}</div>
            </main>
        </div>
    </div>
</x-layouts.store>
