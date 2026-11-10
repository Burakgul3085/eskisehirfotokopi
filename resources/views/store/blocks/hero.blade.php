{{--
    Karşılama alanı: başlık, metin, arama ve güven öğeleri (Ayarlar › Genel › Güven şeridi) + 3D sahne (resources/js/hero-scene.js).
    Sahnedeki kitaplar vitrindeki görselli ürünlerdir (HomePage::heroBookIds). WebGL yoksa ya da yüklenemezse parlayan
    küre yedeği ve cam kartlar görünür; "hareketi azalt" tercihinde animasyonlar durur.
--}}
@php
    $tiles = array_slice(\App\Support\Settings::trustItems(), 0, 3);
    $heroBooks = collect($data ?? [])->map(fn ($p) => ['img' => $p->imageUrl('md'), 'url' => route('product.show', $p), 'title' => $p->title])->values();
    // Cam kartların fareye tepki derinlikleri (geniş ekranda sahnenin altında yüzen şerit)
    $cardDepth = [10, 16, 10];
@endphp
<section class="hero-3d relative isolate overflow-hidden text-white">
    {{-- Arka plan katmanları --}}
    <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
        <div class="hero-aurora absolute -top-40 right-[-10%] size-[42rem] rounded-full bg-accent/20 blur-[120px]"></div>
        <div class="hero-aurora hero-aurora-2 absolute -bottom-48 left-[-12%] size-[38rem] rounded-full bg-white/10 blur-[110px]"></div>
        <div class="hero-grid absolute inset-0"></div>
        <div class="hero-spot absolute inset-0"></div>
        <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-white/25 to-transparent"></div>
    </div>

    <div class="container-page relative grid items-center gap-6 py-12 md:grid-cols-[1.02fr_1fr] md:gap-4 md:py-14 lg:py-16">
        <div class="relative z-10">
            @if ($block->setting('badge'))
                <span class="hero-reveal inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-white ring-1 ring-white/20 backdrop-blur" style="--d: 0">
                    <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-accent opacity-60 motion-reduce:hidden"></span><span class="relative inline-flex size-2 rounded-full bg-accent"></span></span>
                    {{ $block->setting('badge') }}
                </span>
            @endif
            <h1 class="hero-reveal mt-5 bg-gradient-to-br from-white via-white to-white/65 bg-clip-text text-4xl leading-[1.05] font-extrabold tracking-tight text-transparent sm:text-5xl lg:text-6xl" style="--d: 1">
                {{ $block->setting('heading') ?: $settings['site_tagline'] }}
            </h1>
            @if ($block->setting('text'))
                <p class="hero-reveal mt-5 max-w-lg text-base leading-relaxed text-white/75 sm:text-lg" style="--d: 2">{{ $block->setting('text') }}</p>
            @endif
            <form action="{{ route('search') }}" method="get" role="search" class="hero-reveal group mt-8 flex max-w-lg gap-2 rounded-2xl bg-white/95 p-1.5 shadow-[0_24px_60px_-20px_rgba(0,0,0,0.55)] ring-1 ring-white/40 transition focus-within:ring-4 focus-within:ring-accent/40" style="--d: 3">
                <span class="flex items-center pl-3 text-slate-400"><x-icon name="search" class="size-5" /></span>
                <input name="q" type="search" placeholder="Kitap adı, yayınevi, yazar veya ISBN" aria-label="Ara"
                       class="min-w-0 flex-1 rounded-xl px-2 text-[15px] text-ink placeholder:text-slate-400 focus:outline-none">
                <button class="btn-accent px-6">Ara</button>
            </form>
            @if ($block->setting('button_label') && $block->setting('button_url'))
                <a href="{{ $block->setting('button_url') }}" class="hero-reveal group/link mt-5 inline-flex items-center gap-2.5 rounded-full bg-white/10 py-2 pr-4 pl-2 text-sm font-semibold text-white ring-1 ring-white/15 backdrop-blur transition hover:bg-white/15" style="--d: 4">
                    <span class="flex size-7 items-center justify-center rounded-full bg-accent text-ink"><x-icon name="upload" class="size-4" /></span>
                    {{ $block->setting('button_label') }}
                    <span class="transition group-hover/link:translate-x-0.5" aria-hidden="true">→</span>
                </a>
            @endif
        </div>

        <div class="relative">
            {{-- 3D sahne --}}
            <div data-hero-scene class="relative h-[320px] sm:h-[400px] md:h-[500px] lg:h-[540px]">
                <div class="hero-fallback pointer-events-none absolute inset-0 flex items-center justify-center transition-opacity duration-1000" aria-hidden="true">
                    <div class="size-56 rounded-full bg-gradient-to-br from-white/25 to-accent/20 blur-2xl md:size-72"></div>
                </div>
                <div class="hero-stage absolute inset-0 md:-right-10">
                    <canvas data-hero-canvas class="block size-full" aria-hidden="true"></canvas>
                    <div data-hero-tip class="pointer-events-none absolute top-0 left-0 max-w-[16rem] truncate rounded-lg bg-ink/85 px-3 py-1.5 text-xs font-semibold text-white shadow-lg ring-1 ring-white/10 backdrop-blur" role="presentation"></div>
                </div>
                <script type="application/json" data-hero-books>@json($heroBooks)</script>
            </div>

            {{-- Cam kartlar: telefonda sahnenin altında sıra, geniş ekranda sahnenin alt kenarında yüzen şerit --}}
            @if ($tiles)
                <ul class="relative z-10 mt-2 grid grid-cols-3 gap-2 md:absolute md:inset-x-0 md:bottom-1 md:mt-0 md:gap-3 lg:-mr-6 lg:-ml-2">
                    @foreach ($tiles as $i => $tile)
                        <li class="hero-card" style="--depth: {{ $cardDepth[$i] ?? 12 }}">
                            <div class="hero-card-float flex h-full flex-col gap-2 rounded-2xl border border-white/15 bg-white/[0.08] p-3 shadow-[0_24px_50px_-24px_rgba(0,0,0,0.6)] backdrop-blur-xl sm:p-4 lg:flex-row lg:items-center lg:gap-3" style="--d: {{ $i }}">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-accent to-accent/70 text-ink shadow-[0_8px_20px_-6px_var(--color-accent)] sm:size-10">
                                    <x-icon :name="$tile['icon']" class="size-5" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block font-display text-xs leading-tight font-semibold sm:text-sm">{{ $tile['title'] }}</span>
                                    @if ($tile['text'])<span class="mt-0.5 hidden text-xs leading-snug text-white/65 sm:block">{{ $tile['text'] }}</span>@endif
                                </span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</section>
