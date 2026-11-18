{{-- Bilgi sayfalarının ortak koyu üst bandı: konum çubuğu, simge, başlık ve "son güncelleme" etiketi.
     Sağ altta marka işaretindeki iki sayfayı anımsatan dönük kartlar (yazdırmada gizli).
     Yazdırınca antetli belge başlığı çıkar; A4 sayfa düzeni, altta site adı · belge adı ve "Sayfa X / Y". --}}
@props(['page', 'trail' => [], 'date' => true])
@php
    $domain = preg_replace('#^https?://#', '', rtrim(route('home'), '/'));
    // CSS dizgisi içine güvenli yazım (tırnak, ters bölü ve etiket kapanışı olmadan)
    $cssText = fn (string $s) => str_replace(['\\', '"', '<', '>', "\n", "\r"], ['\\\\', '\\"', '', '', ' ', ''], $s);
    $logo = \App\Support\Settings::fileUrl('logo_path');
@endphp
<style>
    @page {
        size: A4 portrait;
        margin: 16mm 16mm 18mm;
        @top-left { content: ""; }
        @top-center { content: ""; }
        @top-right { content: ""; }
        @bottom-left { content: "{{ $cssText($settings['site_name'].' · '.$page->title) }}"; font: 600 8pt/1.2 system-ui, sans-serif; color: #475569; }
        @bottom-center { content: ""; }
        @bottom-right { content: "Sayfa " counter(page) " / " counter(pages); font: 600 8pt/1.2 system-ui, sans-serif; color: #475569; }
    }
</style>
<section class="info-hero surface-deep relative isolate overflow-hidden text-white print:overflow-visible print:bg-none print:text-ink">
    <div class="info-hero-tiles pointer-events-none absolute -right-6 -bottom-12 hidden md:block print:hidden" aria-hidden="true">
        <span class="info-tile info-tile-back"></span>
        <span class="info-tile info-tile-front"></span>
    </div>
    <div class="container-page relative py-10 sm:py-14 print:max-w-none print:p-0">
        {{-- Antet: yalnız yazdırmada --}}
        <div class="info-letterhead hidden print:flex">
            <span class="flex items-center gap-3"><x-brand :logo="$logo" :name="$settings['site_name']" dark /></span>
            <span class="text-right">
                <span class="block font-semibold text-ink">{{ $domain }}</span>
                <span class="block">{{ $page->title }}</span>
            </span>
        </div>
        <x-breadcrumb class="info-breadcrumb print:hidden" :items="array_merge($trail, [[$page->title, null]])" />
        <div class="mt-6 flex items-start gap-4 print:mt-0 print:block">
            <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-white/10 text-accent ring-1 ring-white/15 print:hidden"><x-icon :name="$page->icon()" class="size-7" /></span>
            <div class="min-w-0">
                <h1 class="font-display text-3xl leading-tight font-extrabold tracking-tight sm:text-4xl print:text-center print:text-[20pt] print:tracking-normal print:uppercase">{{ $page->title }}</h1>
                @if ($date || ! $page->is_active)
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-semibold print:justify-center print:gap-6 print:font-normal print:text-slate-600">
                        @if ($date && $settings['pages_effective'])
                            <span class="hidden print:inline">{{ $settings['pages_effective'] }}: {{ $page->created_at?->translatedFormat('j F Y') }}</span>
                        @endif
                        @if ($date && $settings['pages_updated'])
                            <span class="rounded-full bg-white/10 px-3 py-1 ring-1 ring-white/15 print:bg-transparent print:p-0 print:ring-0">{{ $settings['pages_updated'] }}: {{ $page->updated_at->translatedFormat('j F Y') }}</span>
                        @endif
                        @unless ($page->is_active)<span class="rounded-full bg-accent px-3 py-1 text-ink print:hidden">Gizli sayfa · yalnız yöneticiler görüyor</span>@endunless
                    </div>
                @endif
                {{ $slot }}
            </div>
        </div>
    </div>
</section>
