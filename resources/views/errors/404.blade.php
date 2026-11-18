@php
    /**
     * Bulunamadı (404) sayfası: olmayan adresler ve hiçbir eşleşme vermeyen aramalar (CatalogController@search).
     * Sade ve öneri içermez: koyu tam ekran bant, 3D sahne (resources/js/scenes/not-found.js), başlık, arama kutusu, ana sayfa.
     * Oturumsuz istekte (ör. dosya adresi) site düzeni çizilemez; sade sürüm gösterilir.
     */
    $search = isset($search) && $search !== '' ? $search : null;
    $s = \App\Support\Settings::all();
@endphp
@if (! request()->hasSession())
<!DOCTYPE html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $s['notfound_title'] }}</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;font-family:system-ui,sans-serif;background:#0b2b28;color:#fff;text-align:center}b{display:block;font-size:6rem;letter-spacing:-.05em;color:#f59e0b}a{color:#fff}</style></head>
<body><main><b>404</b><h1>{{ $s['notfound_title'] }} {{ $s['notfound_accent'] }}</h1><p>{{ $s['notfound_text'] }}</p><p><a href="{{ url('/') }}">Ana sayfaya dön</a></p></main></body></html>
@else
<x-layouts.store :title="trim($s['notfound_title'].' '.$s['notfound_accent'])" :noindex="true" :flush="true">
    <section class="hero-3d surface-deep nf-hero relative isolate flex min-h-[calc(100svh-10rem)] items-center overflow-hidden text-white">
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
            <div class="hero-grid absolute inset-0"></div>
            <div class="hero-spot absolute inset-0"></div>
        </div>
        <div class="container-page relative flex flex-col items-center py-10 text-center sm:py-14">
            <div data-scene="not-found" class="relative h-[220px] w-full max-w-3xl cursor-pointer sm:h-[300px] lg:h-[360px]" title="Tıklayın">
                <div class="hero-fallback pointer-events-none absolute inset-0 flex items-center justify-center transition-opacity duration-1000" aria-hidden="true">
                    <span class="nf-fallback font-display text-[8rem] leading-none font-extrabold tracking-tighter text-white/15 sm:text-[11rem]">404</span>
                </div>
                <div class="pointer-events-none absolute inset-x-[25%] bottom-3 h-8 rounded-[100%] bg-black/40 blur-2xl" aria-hidden="true"></div>
                <div class="hero-stage absolute inset-0">
                    <canvas data-scene-canvas class="block size-full" aria-hidden="true"></canvas>
                </div>
            </div>

            <p class="hero-reveal mt-2 inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5 text-xs font-bold tracking-[0.22em] uppercase ring-1 ring-white/15 backdrop-blur" style="--d: 0">
                <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-accent opacity-60"></span><span class="relative inline-flex size-2 rounded-full bg-accent"></span></span>
                {{ $s['notfound_kicker'] }}
            </p>
            <h1 class="hero-reveal mt-4 text-4xl font-extrabold tracking-tight sm:text-5xl" style="--d: 1">
                {{ $s['notfound_title'] }}@if ($s['notfound_accent'])<span class="bg-gradient-to-r from-accent to-white bg-clip-text text-transparent"> {{ $s['notfound_accent'] }}</span>@endif
            </h1>
            <p class="hero-reveal mt-4 max-w-xl text-white/70" style="--d: 2">
                @if ($search)
                    {!! str_replace(':q', '<strong class="text-white">“'.e($search).'”</strong>', e($s['notfound_search_text'])) !!}
                @else
                    {{ $s['notfound_text'] }}
                @endif
            </p>

            {{-- Yeniden arama (öneri paneli yok, düz form) --}}
            <form action="{{ route('search') }}" method="get" role="search"
                  class="hero-reveal mt-7 flex w-full max-w-lg gap-2 rounded-2xl bg-white/95 p-1.5 text-left shadow-[0_24px_60px_-20px_rgba(0,0,0,0.55)] ring-1 ring-white/40 transition focus-within:ring-4 focus-within:ring-accent/40" style="--d: 3">
                <span class="flex items-center pl-3 text-slate-400"><x-icon name="search" class="size-5" /></span>
                <input name="q" type="search" value="{{ $search }}" placeholder="{{ \App\Domain\Search\SiteSearch::placeholder() }}" aria-label="Ara"
                       class="min-w-0 flex-1 rounded-xl px-2 text-[15px] text-ink placeholder:text-slate-400 focus:outline-none">
                <button class="btn-accent px-6">Ara</button>
            </form>

            <a href="{{ route('home') }}" class="hero-reveal nf-chip mt-5" style="--d: 4"><x-icon name="home" class="size-4" />Ana sayfaya dön</a>
        </div>
    </section>
</x-layouts.store>
@endif
