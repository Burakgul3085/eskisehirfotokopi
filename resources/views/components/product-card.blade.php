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
<article {{ $attributes->merge(['class' => 'group relative flex h-full flex-col overflow-hidden rounded-2xl border border-line/70 bg-white transition hover:-translate-y-0.5 hover:shadow-lift']) }}>
    <a href="{{ $url }}" class="relative block aspect-[4/5] overflow-hidden bg-gradient-to-b from-surface to-white" tabindex="-1" aria-hidden="true">
        @if ($product->image_key)
            <img src="{{ $product->imageUrl('md') }}" srcset="{{ $product->imageSrcset() }}" sizes="(min-width: 1280px) 240px, (min-width: 640px) 30vw, 46vw"
                 alt="" width="{{ $product->image_width ?: 640 }}" height="{{ $product->image_height ?: 800 }}"
                 loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async" @if ($eager) fetchpriority="high" @endif
                 class="size-full object-contain p-2 transition duration-300 group-hover:scale-[1.03]">
        @else
            <span class="flex size-full flex-col items-center justify-center gap-2 p-4 text-center text-muted">
                <x-icon name="book" class="size-10 text-slate-300" />
                <span class="line-clamp-3 font-display text-sm font-semibold text-slate-400">{{ $product->title }}</span>
            </span>
        @endif
        @if ($discount)
            <span class="absolute top-2.5 left-2.5 rounded-lg bg-danger px-2 py-0.5 text-xs font-bold text-white">%{{ $discount }}</span>
        @endif
        @if ($hasColor)
            <span class="absolute top-2.5 right-2.5 rounded-lg bg-white/90 px-2 py-0.5 text-[11px] font-semibold text-primary-dark ring-1 ring-primary-line">Renkli</span>
        @endif
    </a>
    <div class="flex flex-1 flex-col gap-1 p-3 sm:p-3.5">
        @if ($product->publisher)
            <p class="truncate text-xs font-medium text-muted">{{ $product->publisher->name }}</p>
        @endif
        <h3 class="line-clamp-2 min-h-[2.6em] text-sm leading-snug font-semibold text-ink">
            <a href="{{ $url }}" class="after:absolute after:inset-0 hover:text-primary">{{ $product->title }}</a>
        </h3>
        <div class="mt-auto pt-2">
            @if ($price !== null)
                @if ($compare)<p class="text-xs text-muted line-through">{{ \App\Support\Money::format($compare) }}</p>@endif
                <p class="font-display text-lg leading-none font-bold text-ink">{{ \App\Support\Money::format($price) }}</p>
                @if ($hasBoth)
                    <p class="mt-1 text-[11px] text-muted">S-B · Renkli {{ \App\Support\Money::format($product->price_color) }}</p>
                @endif
            @else
                <p class="text-sm font-medium text-muted">Fiyat için sorunuz</p>
            @endif
        </div>
    </div>
</article>
