@props(['title', 'subtitle' => null, 'products', 'url' => null])
{{-- Yatay kaydırmalı ürün satırı: mobilde parmakla kaydırılır, masaüstünde oklarla. --}}
<section {{ $attributes->merge(['class' => 'container-page py-8 sm:py-10']) }} x-data="scroller">
    <div class="mb-4 flex items-end justify-between gap-4">
        <div>
            <h2 class="text-xl font-extrabold sm:text-2xl">{{ $title }}</h2>
            @if ($subtitle)<p class="mt-1 text-sm text-muted">{{ $subtitle }}</p>@endif
        </div>
        <div class="flex shrink-0 items-center gap-2">
            @if ($url)<a href="{{ $url }}" class="text-sm font-semibold text-primary hover:underline">Tümünü gör</a>@endif
            <button type="button" x-on:click="prev" class="hidden size-9 items-center justify-center rounded-full border border-line bg-white hover:bg-surface sm:flex" aria-label="Önceki"><x-icon name="chevron-right" class="size-4 rotate-180" /></button>
            <button type="button" x-on:click="next" class="hidden size-9 items-center justify-center rounded-full border border-line bg-white hover:bg-surface sm:flex" aria-label="Sonraki"><x-icon name="chevron-right" class="size-4" /></button>
        </div>
    </div>
    <div x-ref="track" class="-mx-4 flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth px-4 pb-2 [scrollbar-width:none] sm:mx-0 sm:gap-4 sm:px-0 [&::-webkit-scrollbar]:hidden">
        @foreach ($products as $product)
            <x-product-card :product="$product" class="w-[46%] shrink-0 snap-start sm:w-[30%] md:w-[23%] lg:w-[18.6%]" />
        @endforeach
    </div>
</section>
