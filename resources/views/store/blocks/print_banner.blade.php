{{-- Belge bandı: başlık, metin ve düğme + 3D kopyalama sahnesi (resources/js/scenes/print-banner.js). Metinler blok ayarlarından. --}}
<section class="container-page py-8 sm:py-10">
    <div class="scene-banner group/banner relative isolate overflow-hidden rounded-[2rem] bg-ink text-white shadow-[0_40px_80px_-40px_rgba(2,6,23,0.7)] ring-1 ring-white/5">
        {{-- Arka plan: ışık hareleri, ince ızgara, üst kenarda parlak çizgi --}}
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
            <div class="scene-aurora absolute -top-28 right-[8%] size-96 rounded-full bg-primary/45 blur-[100px]"></div>
            <div class="scene-aurora scene-aurora-2 absolute -bottom-32 -left-16 size-96 rounded-full bg-accent/25 blur-[100px]"></div>
            <div class="scene-grid absolute inset-0"></div>
            <div class="absolute inset-x-10 top-0 h-px bg-gradient-to-r from-transparent via-white/30 to-transparent"></div>
        </div>
        <div class="relative grid items-center gap-2 px-6 pt-10 sm:px-12 sm:pt-12 md:grid-cols-[1.15fr_1fr] md:gap-6 md:py-12">
            <div class="relative z-10">
                <span class="flex size-12 items-center justify-center rounded-2xl bg-gradient-to-br from-accent to-accent/70 text-ink shadow-[0_12px_30px_-10px_var(--color-accent)]"><x-icon name="printer" class="size-6" /></span>
                <h2 class="mt-5 text-2xl leading-tight font-extrabold tracking-tight sm:text-4xl">{{ $block->title }}</h2>
                @if ($block->subtitle)<p class="mt-3 max-w-xl leading-relaxed text-white/70">{{ $block->subtitle }}</p>@endif
                @if ($block->setting('button_label') && $block->setting('button_url'))
                    <a href="{{ $block->setting('button_url') }}" class="btn-accent group/btn mt-7 px-6 py-3 text-base shadow-[0_18px_40px_-14px_var(--color-accent)] transition hover:-translate-y-0.5">
                        <x-icon name="upload" class="size-5" />{{ $block->setting('button_label') }}
                        <span class="transition group-hover/btn:translate-x-0.5" aria-hidden="true">→</span>
                    </a>
                @endif
            </div>
            <div data-scene="print-banner" class="relative h-56 sm:h-64 md:-my-6 md:h-[19rem]">
                <div class="scene-fallback pointer-events-none absolute inset-0 flex items-center justify-center" aria-hidden="true">
                    <div class="size-40 rounded-full bg-gradient-to-br from-primary/40 to-accent/30 blur-2xl"></div>
                </div>
                <canvas data-scene-canvas class="scene-canvas absolute inset-0 block size-full" aria-hidden="true"></canvas>
            </div>
        </div>
    </div>
</section>
