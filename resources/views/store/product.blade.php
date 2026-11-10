@php
    /** @var \App\Models\Product $product */
    $types = $product->printTypes();
    $labels = ['bw' => 'Siyah-beyaz', 'color' => 'Renkli'];
    $crumbs = $trail->map(fn ($c) => [$c->name, route('category.show', $c)])->push([$product->title, null])->all();
    $specs = array_filter([
        'Yazar' => $product->author?->name,
        'Yayınevi' => $product->publisher?->name,
        'Kategori' => $trail->isNotEmpty() ? $trail->pluck('name')->implode(' › ') : null,
        'Sayfa sayısı' => $product->page_count,
        'Baskı yılı' => $product->print_year,
        'ISBN' => $product->isbn,
        'Ürün kodu' => $product->code,
        'Baskı' => $types ? implode(' / ', array_map(fn ($t) => $labels[$t], $types)) : null,
    ]);
    $canBuy = $product->isPurchasable() && \Illuminate\Support\Facades\Route::has('cart.add');
    $schema = array_filter([
        '@context' => 'https://schema.org', '@type' => 'Product', 'name' => $product->title,
        'image' => $product->imageUrl('lg'), 'sku' => $product->code, 'gtin13' => $product->isbn,
        'brand' => $product->publisher ? ['@type' => 'Brand', 'name' => $product->publisher->name] : null,
        'offers' => $types ? ['@type' => 'AggregateOffer', 'priceCurrency' => 'TRY', 'lowPrice' => min(array_map(fn ($t) => (float) $product->price($t), $types)),
            'highPrice' => max(array_map(fn ($t) => (float) $product->price($t), $types)), 'offerCount' => count($types), 'availability' => 'https://schema.org/InStock'] : null,
    ]);
@endphp
<x-layouts.store :title="$product->meta_title ?: $product->title" :description="$product->meta_description ?: \Illuminate\Support\Str::limit(trim($product->title.'. '.collect($specs)->only(['Yazar', 'Yayınevi', 'Sayfa sayısı'])->map(fn ($v, $k) => $k.': '.$v)->implode(', ')), 160)"
                 :image="$product->imageUrl('lg')" :noindex="$preview">
    <div class="container-page py-6 sm:py-8">
        @if ($preview)
            <p class="mb-4 rounded-xl bg-warning-soft px-4 py-3 text-sm font-medium text-warning">Önizleme: bu ürün yayında değil, yalnızca yöneticiler görebilir.</p>
        @endif
        <x-breadcrumb :items="$crumbs" />

        <div class="mt-5 grid gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)] lg:gap-12">
            {{-- Görsel --}}
            <div class="lg:sticky lg:top-36 lg:self-start">
                <div class="overflow-hidden rounded-3xl border border-line/70 bg-gradient-to-b from-surface to-white">
                    @if ($product->image_key)
                        <a href="{{ $product->imageUrl('lg') }}" target="_blank" rel="noopener" class="block cursor-zoom-in">
                            <img src="{{ $product->imageUrl('lg') }}" srcset="{{ $product->imageSrcset() }}" sizes="(min-width: 1024px) 560px, 100vw"
                                 alt="{{ $product->title }}" width="{{ $product->image_width }}" height="{{ $product->image_height }}" fetchpriority="high"
                                 class="mx-auto max-h-[70vh] w-auto object-contain p-4 sm:p-8">
                        </a>
                    @else
                        <div class="flex aspect-[4/5] flex-col items-center justify-center gap-3 p-8 text-center">
                            <x-icon name="book" class="size-16 text-slate-300" />
                            <p class="font-display text-lg font-bold text-slate-400">{{ $product->title }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Bilgi ve satın alma --}}
            <div>
                @if ($product->publisher)
                    <a href="{{ route('publisher.show', $product->publisher) }}" class="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline">
                        @if ($product->publisher->logoUrl())<img src="{{ $product->publisher->logoUrl() }}" alt="" class="h-6 w-auto">@endif
                        {{ $product->publisher->name }}
                    </a>
                @endif
                <h1 class="mt-2 text-2xl leading-tight font-extrabold sm:text-3xl">{{ $product->title }}</h1>
                @if ($product->author)<p class="mt-2 text-muted">{{ $product->author->name }}</p>@endif

                <div class="mt-6 rounded-2xl border border-line/70 bg-white p-5">
                    @if ($types)
                        <form method="post" action="{{ $canBuy ? route('cart.add') : '#' }}" class="space-y-5">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <fieldset>
                                <legend class="mb-2 text-sm font-semibold">Baskı tipi</legend>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    @foreach ($types as $t)
                                        <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-line p-3.5 transition has-checked:border-primary has-checked:bg-primary-soft has-checked:ring-2 has-checked:ring-primary/15">
                                            <span class="flex items-center gap-2.5">
                                                <input type="radio" name="print_type" value="{{ $t }}" @checked($loop->first) class="accent-primary">
                                                <span class="text-sm font-semibold">{{ $labels[$t] }}</span>
                                            </span>
                                            <span class="text-right">
                                                @if ($cmp = $product->comparePrice($t))<span class="block text-xs text-muted line-through">{{ \App\Support\Money::format($cmp) }}</span>@endif
                                                <span class="font-display text-lg font-bold">{{ \App\Support\Money::format($product->price($t)) }}</span>
                                                @if ($pct = $product->discountPercent($t))<span class="ml-1 rounded bg-danger-soft px-1.5 py-0.5 text-xs font-bold text-danger">%{{ $pct }}</span>@endif
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                            <div class="flex gap-3">
                                <label class="sr-only" for="qty">Adet</label>
                                <input id="qty" type="number" name="quantity" value="1" min="1" max="50" class="field-input w-20 text-center">
                                <button class="btn-primary flex-1 py-3 text-base" @disabled(! $canBuy)><x-icon name="cart" class="size-5" />Sepete ekle</button>
                            </div>
                        </form>
                        @auth('web')
                            <form method="post" action="{{ route('favorites.toggle', $product) }}" class="mt-3" x-data="favoriteButton" data-active="{{ auth('web')->user()->favorites()->whereKey($product->id)->exists() ? '1' : '0' }}">@csrf
                                <button class="inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-danger" x-on:click="toggle" x-bind:disabled="busy"><x-icon name="heart" class="size-4" x-bind:class="iconClass" /><span x-text="label">Favorilere ekle</span></button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-danger"><x-icon name="heart" class="size-4" />Favorilere ekle</a>
                        @endauth
                    @else
                        <p class="text-sm text-muted">Bu ürünün fiyatı henüz belirlenmedi. Bilgi için bize ulaşın.</p>
                        @if ($wa = \App\Support\Settings::whatsappUrl($product->title.' hakkında bilgi almak istiyorum.'))
                            <a href="{{ $wa }}" target="_blank" rel="noopener" class="btn-ghost mt-3"><x-icon name="whatsapp" class="size-4" />WhatsApp ile sor</a>
                        @endif
                    @endif

                    @if ($trust = array_slice(\App\Support\Settings::trustItems(), 0, 3))
                        <ul class="mt-5 grid gap-2 border-t border-line pt-4 text-sm sm:grid-cols-3">
                            @foreach ($trust as $item)
                                <li class="flex items-center gap-2 text-muted"><x-icon :name="$item['icon']" class="size-4 shrink-0 text-primary" />{{ $item['title'] }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <dl class="mt-6 divide-y divide-line overflow-hidden rounded-2xl border border-line/70 bg-white text-sm">
                    @foreach ($specs as $label => $value)
                        <div class="grid grid-cols-[8.5rem_1fr] gap-3 px-4 py-3">
                            <dt class="text-muted">{{ $label }}</dt>
                            <dd class="font-medium">
                                @if ($label === 'Yayınevi' && $product->publisher)<a href="{{ route('publisher.show', $product->publisher) }}" class="hover:text-primary">{{ $value }}</a>
                                @elseif ($label === 'Kategori' && $product->category)<a href="{{ route('category.show', $product->category) }}" class="hover:text-primary">{{ $value }}</a>
                                @else{{ $value }}@endif
                            </dd>
                        </div>
                    @endforeach
                </dl>

                @if ($product->description)
                    <div class="mt-6">
                        <h2 class="text-lg font-bold">Açıklama</h2>
                        <div class="mt-2 text-[15px] leading-relaxed whitespace-pre-line text-ink/85">{{ $product->description }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if ($similar->isNotEmpty())
        <x-product-row title="Benzer ürünler" :products="$similar" :url="$product->category ? route('category.show', $product->category) : null" />
    @endif
    @if ($samePublisher->isNotEmpty())
        <x-product-row :title="$product->publisher->name.' yayınlarından'" :products="$samePublisher" :url="route('publisher.show', $product->publisher)" />
    @endif

    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
</x-layouts.store>
