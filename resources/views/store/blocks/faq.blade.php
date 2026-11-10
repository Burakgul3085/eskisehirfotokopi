{{-- SSS: solda 3D soru işareti (resources/js/scenes/faq.js), sağda 3D açılan kartlar. Sorular blok ayarlarından. --}}
@php($items = collect($block->setting('items', []))->filter(fn ($i) => ! empty($i['q']) && ! empty($i['a']))->values())
<section class="container-page py-10 sm:py-14">
    <div class="grid items-center gap-6 lg:grid-cols-[0.9fr_1.4fr] lg:gap-12">
        <div class="relative text-center lg:sticky lg:top-24 lg:text-left">
            <div data-scene="faq" class="relative mx-auto h-48 max-w-xs sm:h-56 lg:mx-0 lg:h-72 lg:max-w-none">
                <div class="scene-fallback pointer-events-none absolute inset-0 flex items-center justify-center" aria-hidden="true">
                    <div class="size-36 rounded-full bg-gradient-to-br from-primary/30 to-accent/25 blur-2xl"></div>
                </div>
                <div class="faq-floor pointer-events-none absolute inset-x-[24%] bottom-1 h-7 rounded-[100%] bg-primary/25 blur-xl" aria-hidden="true"></div>
                <canvas data-scene-canvas class="scene-canvas absolute inset-0 block size-full" aria-hidden="true"></canvas>
            </div>
            @if ($block->title)<h2 class="text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $block->title }}</h2>@endif
            @if ($block->subtitle)<p class="mt-2 text-muted">{{ $block->subtitle }}</p>@endif
        </div>
        <div class="faq-stack space-y-3">
            @foreach ($items as $i => $item)
                <details class="faq-card group rounded-2xl border border-line/70 bg-white shadow-[0_1px_0_rgba(15,23,42,0.03)] open:border-primary/30 open:shadow-[0_24px_50px_-28px_color-mix(in_oklab,var(--color-primary)_55%,transparent)]">
                    <summary class="flex cursor-pointer list-none items-center gap-4 px-5 py-4 font-semibold [&::-webkit-details-marker]:hidden">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary-soft font-display text-sm font-bold text-primary transition group-open:bg-primary group-open:text-white">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="flex-1">{{ $item['q'] }}</span>
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-surface text-muted transition group-open:rotate-180 group-open:bg-accent group-open:text-ink"><x-icon name="chevron-down" class="size-4" /></span>
                    </summary>
                    <div class="faq-answer px-5 pb-5 pl-[4.25rem]">
                        <p class="text-[15px] leading-relaxed whitespace-pre-line text-muted">{{ $item['a'] }}</p>
                    </div>
                </details>
            @endforeach
        </div>
    </div>
</section>
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items->map(fn ($i) => ['@type' => 'Question', 'name' => $i['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $i['a']]])->all()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
