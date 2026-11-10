@php($slides = collect($block->setting('slides', []))->filter(fn ($s) => ! empty($s['image']))->values())
<section class="container-page pt-5 sm:pt-6">
    <div class="relative overflow-hidden rounded-3xl bg-surface" x-data="slider" data-count="{{ $slides->count() }}" x-on:mouseenter="stop" x-on:mouseleave="play">
        <div x-ref="track" x-on:scroll.debounce.80ms="onScroll" class="flex snap-x snap-mandatory overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            @foreach ($slides as $i => $slide)
                @php($tag = ! empty($slide['url']) ? 'a' : 'div')
                <{{ $tag }} @if ($tag === 'a') href="{{ $slide['url'] }}" @endif class="relative block w-full shrink-0 snap-start">
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($slide['image']) }}" alt="{{ $slide['title'] ?? '' }}"
                         class="aspect-[16/9] w-full object-cover sm:aspect-[21/7]" width="1600" height="533"
                         loading="{{ $first && $i === 0 ? 'eager' : 'lazy' }}" @if ($first && $i === 0) fetchpriority="high" @endif>
                    @if (! empty($slide['title']) || ! empty($slide['text']))
                        <div class="absolute inset-0 flex items-end bg-gradient-to-t from-ink/70 via-ink/10 to-transparent p-5 sm:items-center sm:bg-gradient-to-r sm:p-12">
                            <div class="max-w-lg text-white">
                                @if (! empty($slide['title']))<p class="font-display text-xl font-extrabold sm:text-4xl">{{ $slide['title'] }}</p>@endif
                                @if (! empty($slide['text']))<p class="mt-2 hidden text-white/85 sm:block">{{ $slide['text'] }}</p>@endif
                                @if (! empty($slide['button_label']))<span class="btn-accent mt-4 hidden sm:inline-flex">{{ $slide['button_label'] }}</span>@endif
                            </div>
                        </div>
                    @endif
                </{{ $tag }}>
            @endforeach
        </div>
        @if ($slides->count() > 1)
            <button type="button" x-on:click="prev" class="absolute top-1/2 left-3 hidden size-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 shadow sm:flex" aria-label="Önceki"><x-icon name="chevron-right" class="size-5 rotate-180" /></button>
            <button type="button" x-on:click="next" class="absolute top-1/2 right-3 hidden size-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 shadow sm:flex" aria-label="Sonraki"><x-icon name="chevron-right" class="size-5" /></button>
            <div class="absolute inset-x-0 bottom-3 flex justify-center gap-1.5">
                @foreach ($slides as $i => $slide)
                    <button type="button" data-index="{{ $i }}" x-on:click="goTo" x-bind:class="dotClass" class="h-2 rounded-full transition-all" aria-label="{{ $i + 1 }}. slayt"></button>
                @endforeach
            </div>
        @endif
    </div>
</section>
