@props(['title', 'subtitle' => null, 'products', 'url' => null])
{{-- Yatay kaydırmalı ürün satırı: mobilde parmakla kaydırılır, masaüstünde oklarla; geniş ekranda 6 kart. --}}
<section {{ $attributes->merge(['class' => 'container-page py-8 sm:py-10']) }} x-data="scroller">
    <x-section-head :title="$title" :subtitle="$subtitle">
        @if ($url)<a href="{{ $url }}" class="group/all inline-flex items-center gap-1 rounded-full bg-primary-soft px-3.5 py-1.5 text-sm font-semibold text-primary-dark transition hover:bg-primary hover:text-white">Tümünü gör<x-icon name="arrow-right" class="size-4 transition group-hover/all:translate-x-0.5" /></a>@endif
        <button type="button" x-on:click="prev" class="hidden size-10 items-center justify-center rounded-full border border-line bg-white shadow-sm transition hover:border-primary hover:bg-primary hover:text-white sm:flex" aria-label="Önceki"><x-icon name="chevron-right" class="size-4 rotate-180" /></button>
        <button type="button" x-on:click="next" class="hidden size-10 items-center justify-center rounded-full border border-line bg-white shadow-sm transition hover:border-primary hover:bg-primary hover:text-white sm:flex" aria-label="Sonraki"><x-icon name="chevron-right" class="size-4" /></button>
    </x-section-head>
    <div x-ref="track" class="-mx-4 flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth px-4 pt-1 pb-4 [scrollbar-width:none] sm:mx-0 sm:gap-4 sm:px-0 lg:gap-5 [&::-webkit-scrollbar]:hidden">
        @foreach ($products as $product)
            <x-product-card :product="$product" class="w-[46%] shrink-0 snap-start sm:w-[calc((100%-2rem)/3)] md:w-[calc((100%-3rem)/4)] lg:w-[calc((100%-5rem)/5)] 2xl:w-[calc((100%-6.25rem)/6)]" />
        @endforeach
    </div>
</section>
