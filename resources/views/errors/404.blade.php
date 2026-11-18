@php
    /**
     * Bulunamadı sayfası: bilinmeyen/gizli adresler ve hiçbir sonuç vermeyen aramalar (CatalogController@search).
     * Üstte koyu bant + 3D büyüteç sahnesi (resources/js/scenes/not-found.js), altta hızlı erişim ve çok satanlar.
     * Hızlı erişim ve ürünler AppServiceProvider'daki görünüm birleştiricisinden gelir ($notFound).
     * Oturumsuz istekte (ör. eşleşmeyen yönetim adresi) site düzeni çizilemez; sade sürüm gösterilir.
     */
    $search = isset($search) && $search !== '' ? $search : null;
    $s = \App\Support\Settings::all();
@endphp
@if (! request()->hasSession())
<!DOCTYPE html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $s['notfound_title'] }}</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;font-family:system-ui,sans-serif;background:#0b2b28;color:#fff;text-align:center}b{display:block;font-size:6rem;letter-spacing:-.05em;color:#f59e0b}a{color:#fff}</style></head>
<body><main><b>404</b><h1>{{ $s['notfound_title'] }}</h1><p>{{ $s['notfound_text'] }}</p><p><a href="{{ url('/') }}">Ana sayfaya dön</a></p></main></body></html>
@else
<x-layouts.store :title="$s['notfound_title']" :noindex="true" :flush="true">
    <section class="hero-3d surface-deep relative isolate overflow-hidden text-white">
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
            <div class="hero-grid absolute inset-0"></div>
            <div class="hero-spot absolute inset-0"></div>
        </div>
        <div class="container-page relative grid items-center gap-6 pt-10 pb-16 lg:grid-cols-[1fr_1.1fr] lg:pt-14 lg:pb-20">
            <div class="relative z-20 order-2 lg:order-1">
                <p class="hero-reveal inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5 text-xs font-bold tracking-[0.22em] uppercase ring-1 ring-white/15 backdrop-blur" style="--d: 0">
                    <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-accent opacity-60"></span><span class="relative inline-flex size-2 rounded-full bg-accent"></span></span>
                    {{ $s['notfound_kicker'] }}
                </p>
                <h1 class="hero-reveal mt-5 text-4xl font-extrabold tracking-tight sm:text-5xl" style="--d: 1">
                    {{ $s['notfound_title'] }}@if ($s['notfound_accent'])<span class="bg-gradient-to-r from-accent to-white bg-clip-text text-transparent"> {{ $s['notfound_accent'] }}</span>@endif
                </h1>
                <p class="hero-reveal mt-4 max-w-lg text-white/70" style="--d: 2">
                    @if ($search)
                        {!! str_replace(':q', '<strong class="text-white">“'.e($search).'”</strong>', e($s['notfound_search_text'])) !!}
                    @else
                        {{ $s['notfound_text'] }}
                    @endif
                </p>

                {{-- Site geneli anlık arama --}}
                <form action="{{ route('search') }}" method="get" role="search" x-data="liveSearch" data-url="{{ route('search.suggest') }}"
                      x-on:click.outside="close" x-on:keydown.escape="close" x-on:keydown.down="down" x-on:keydown.up="up"
                      class="hero-reveal relative z-20 mt-7 flex max-w-lg gap-2 rounded-2xl bg-white/95 p-1.5 shadow-[0_24px_60px_-20px_rgba(0,0,0,0.55)] ring-1 ring-white/40 transition focus-within:ring-4 focus-within:ring-accent/40" style="--d: 3">
                    <span class="flex items-center pl-3 text-slate-400"><x-icon name="search" class="size-5" /></span>
                    <input name="q" type="search" x-ref="input" x-model="q" x-on:input="onInput" x-on:focus="onFocus" autocomplete="off" value="{{ $search }}"
                           placeholder="{{ \App\Domain\Search\SiteSearch::placeholder() }}" aria-label="Ara"
                           class="min-w-0 flex-1 rounded-xl px-2 text-[15px] text-ink placeholder:text-slate-400 focus:outline-none">
                    <button class="btn-accent px-6">Ara</button>
                    @include('store.partials.search-dropdown', ['max' => 'max-h-[24rem]'])
                </form>

                <div class="hero-reveal mt-6 flex flex-wrap gap-2" style="--d: 4">
                    <a href="{{ route('home') }}" class="nf-chip"><x-icon name="home" class="size-4" />Ana sayfa</a>
                    <a href="{{ route('products.index') }}" class="nf-chip"><x-icon name="book" class="size-4" />Tüm kitaplar</a>
                    <a href="{{ route('print.index') }}" class="nf-chip"><x-icon name="printer" class="size-4" />Belge yükle</a>
                    <a href="{{ route('contact') }}" class="nf-chip"><x-icon name="chat" class="size-4" />Bize yazın</a>
                </div>
            </div>

            <div data-scene="not-found" class="relative order-1 h-[230px] cursor-pointer sm:h-[300px] lg:order-2 lg:h-[400px]" title="Tıklayın">
                <div class="hero-fallback pointer-events-none absolute inset-0 flex items-center justify-center transition-opacity duration-1000" aria-hidden="true">
                    <span class="nf-fallback font-display text-[7rem] font-extrabold tracking-tighter text-white/15 sm:text-[10rem]">404</span>
                </div>
                <div class="nf-floor pointer-events-none absolute inset-x-[22%] bottom-4 h-8 rounded-[100%] bg-black/40 blur-2xl" aria-hidden="true"></div>
                <div class="hero-stage absolute inset-0">
                    <canvas data-scene-canvas class="block size-full" aria-hidden="true"></canvas>
                </div>
            </div>
        </div>
    </section>

    <div class="page-tint relative pb-6">
        <div class="container-page relative z-10">
            {{-- Hızlı erişim: koyu bandın alt kenarına biner --}}
            <div class="-mt-8 grid gap-3 rounded-3xl border border-line/70 bg-white p-4 shadow-[0_30px_60px_-30px_rgba(15,23,42,0.35)] sm:grid-cols-2 sm:p-5 lg:grid-cols-3">
                @foreach ($notFound['sections'] as $item)
                    <a href="{{ $item['url'] }}" class="nf-link group flex items-center gap-3 rounded-2xl p-3" style="--i: {{ $loop->index }}">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary-soft text-primary transition group-hover:bg-primary group-hover:text-white"><x-icon :name="$item['icon'] ?? 'arrow-right'" class="size-5" /></span>
                        <span class="min-w-0 flex-1 truncate font-semibold">{{ $item['title'] }}</span>
                        <x-icon name="chevron-right" class="size-4 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-primary" />
                    </a>
                @endforeach
            </div>

            @if ($notFound['categories'] !== [])
                <div class="mt-8">
                    <h2 class="text-sm font-bold tracking-wider text-muted uppercase">Popüler kategoriler</h2>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($notFound['categories'] as $c)
                            <a href="{{ $c['url'] }}" class="rounded-full border border-line bg-white px-4 py-2 text-sm font-semibold transition hover:-translate-y-0.5 hover:border-primary hover:text-primary">{{ $c['title'] }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        @if ($notFound['products']->isNotEmpty())
            <x-product-row class="mt-4" title="En çok tercih edilenler" :products="$notFound['products']" :url="route('products.index', ['sirala' => 'cok-satan'])" />
        @endif
    </div>
</x-layouts.store>
@endif
