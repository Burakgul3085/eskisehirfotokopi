{{-- Bilgi sayfalarının ortak koyu üst bandı: konum çubuğu, simge, başlık ve "son güncelleme" etiketi.
     Sağ altta marka işaretindeki iki sayfayı anımsatan dönük kartlar (yazdırmada gizli). --}}
@props(['page', 'trail' => [], 'date' => true])
<section class="info-hero surface-deep relative isolate overflow-hidden text-white print:bg-none print:text-ink">
    <div class="info-hero-tiles pointer-events-none absolute -right-6 -bottom-12 hidden md:block print:hidden" aria-hidden="true">
        <span class="info-tile info-tile-back"></span>
        <span class="info-tile info-tile-front"></span>
    </div>
    <div class="container-page relative py-10 sm:py-14 print:py-0">
        <p class="mb-4 hidden items-center justify-between text-xs text-slate-600 print:flex">
            <span class="font-display font-extrabold text-ink">{{ $settings['site_name'] }}</span><span>{{ preg_replace('#^https?://#', '', rtrim(route('home'), '/')) }}</span>
        </p>
        <x-breadcrumb class="info-breadcrumb print:hidden" :items="array_merge($trail, [[$page->title, null]])" />
        <div class="mt-6 flex items-start gap-4 print:mt-0">
            <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-white/10 text-accent ring-1 ring-white/15 print:hidden"><x-icon :name="$page->icon()" class="size-7" /></span>
            <div class="min-w-0">
                <h1 class="font-display text-3xl leading-tight font-extrabold tracking-tight sm:text-4xl">{{ $page->title }}</h1>
                @if ($date || ! $page->is_active)
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-semibold">
                        @if ($date && $settings['pages_updated'])
                            <span class="rounded-full bg-white/10 px-3 py-1 ring-1 ring-white/15 print:bg-transparent print:p-0 print:ring-0">{{ $settings['pages_updated'] }}: {{ $page->updated_at->translatedFormat('j F Y') }}</span>
                        @endif
                        @unless ($page->is_active)<span class="rounded-full bg-accent px-3 py-1 text-ink">Gizli sayfa · yalnız yöneticiler görüyor</span>@endunless
                    </div>
                @endif
                {{ $slot }}
            </div>
        </div>
    </div>
</section>
