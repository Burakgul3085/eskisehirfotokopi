@props(['product', 'eager' => false])
@php
    /** @var \App\Models\Product $product */
    $type = $product->defaultPrintType();
    $price = $type ? $product->price($type) : null;
    $compare = $type ? $product->comparePrice($type) : null;
    $discount = $type ? $product->discountPercent($type) : null;
    $types = $product->printTypes();
    $hasColor = in_array('color', $types, true);
    $hasBoth = $type === 'bw' && $hasColor;
    $url = route('product.show', $product);
@endphp
<article {{ $attributes->merge(['class' => 'product-card group relative flex h-full flex-col overflow-hidden rounded-[1.4rem] border border-line/60 bg-white transition duration-300 hover:-translate-y-1.5 hover:border-primary-line']) }}>
    {{-- Kapak sahnesi: kapak kırpılmadan bütünüyle, gölgeli kitap gibi görünür; rozetler kapağın üstüne binmez --}}
    <a href="{{ $url }}" class="product-stage relative m-1.5 mb-0 block aspect-[4/5] overflow-hidden rounded-[1.1rem]" tabindex="-1" aria-hidden="true">
        @if ($product->image_key)
            <img src="{{ $product->imageUrl('md') }}" srcset="{{ $product->imageSrcset() }}" sizes="(min-width: 1536px) 260px, (min-width: 640px) 30vw, 46vw"
                 alt="" width="{{ $product->image_width ?: 640 }}" height="{{ $product->image_height ?: 800 }}"
                 loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async" @if ($eager) fetchpriority="high" @endif
                 class="product-cover absolute inset-0 m-auto max-h-[calc(100%-2.5rem)] max-w-[calc(100%-2.5rem)] object-contain">
        @else
            <span class="flex size-full flex-col items-center justify-center gap-2 p-4 text-center text-muted">
                <x-icon name="book" class="size-10 text-slate-300" />
                <span class="line-clamp-3 font-display text-sm font-semibold text-slate-400">{{ $product->title }}</span>
            </span>
        @endif
        @if ($discount)
            <span class="absolute top-2 left-2 rounded-lg bg-danger px-2 py-0.5 text-xs font-bold text-white shadow-sm">%{{ $discount }}</span>
        @endif
    </a>
    <div class="flex flex-1 flex-col gap-1.5 px-3.5 pt-3 pb-3.5 sm:px-4 sm:pb-4">
        <div class="flex items-center gap-2">
            @if ($product->publisher)<p class="min-w-0 flex-1 truncate text-xs font-medium text-muted">{{ $product->publisher->name }}</p>@endif
            @if ($hasColor)<span class="ml-auto shrink-0 rounded-md bg-gradient-to-r from-primary-soft to-accent-soft px-1.5 py-0.5 text-[10px] font-bold tracking-wide text-primary-dark uppercase">Renkli</span>@endif
        </div>
        <h3 class="line-clamp-2 min-h-[2.6em] font-display text-[15px] leading-snug font-bold text-ink">
            <a href="{{ $url }}" class="after:absolute after:inset-0 group-hover:text-primary-dark">{{ $product->title }}</a>
        </h3>
        @if ($product->rating_count && \App\Support\Settings::flag('reviews_enabled'))
            <p class="-mt-0.5 flex items-center gap-1.5 text-xs text-muted"><x-stars :value="$product->rating_avg" size="size-3.5" /><span class="font-semibold text-ink">{{ number_format($product->rating_avg, 1, ',', '') }}</span>({{ $product->rating_count }})</p>
        @endif
        <div class="mt-auto flex items-end justify-between gap-2 border-t border-dashed border-line/80 pt-3">
            <div class="min-w-0">
                @if ($price !== null)
                    @if ($compare)<p class="text-xs text-muted line-through">{{ \App\Support\Money::format($compare) }}</p>@endif
                    <p class="font-display text-xl leading-none font-extrabold text-ink">{{ \App\Support\Money::format($price) }}</p>
                    @if ($hasBoth)
                        <p class="mt-1 text-[11px] leading-tight text-muted">S-B · Renkli {{ \App\Support\Money::format($product->price_color) }}</p>
                    @endif
                @else
                    <p class="text-sm font-medium text-muted">Fiyat için sorunuz</p>
                @endif
            </div>
            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-surface text-muted transition duration-300 group-hover:bg-primary group-hover:text-white group-hover:shadow-[0_8px_20px_-8px_var(--color-primary)]" aria-hidden="true"><x-icon name="arrow-right" class="size-4 transition group-hover:-rotate-45" /></span>
        </div>
    </div>
</article>
