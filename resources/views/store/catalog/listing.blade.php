@php
    /** Ortak liste sayfası: kategori, yayınevi, arama ve tüm ürünler. */
    $sort = request('sirala', 'onerilen');
    $selectedPublishers = array_map('intval', (array) request('yayinevi', []));
    $filterCount = \App\Domain\Catalog\ProductListing::activeFilterCount(request());
    $crumbs = [];
    if (isset($publisher)) {
        $crumbs = [['Yayınevleri', route('publishers.index')], [$publisher->name, null]];
    } elseif ($trail->isNotEmpty()) {
        $crumbs = $trail->map(fn ($c) => [$c->name, route('category.show', $c)])->all();
    } else {
        $crumbs = [[$heading, null]];
    }
@endphp
<x-layouts.store :title="$metaTitle" :description="$metaDescription" :noindex="$noindex ?? false">
    <div class="container-page py-6 sm:py-8">
        <x-breadcrumb :items="$crumbs" />

        <div class="mt-4 flex flex-wrap items-start gap-4">
            @isset($publisher)
                <span class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-line bg-white p-2 sm:size-20">
                    @if ($publisher->logoUrl())<img src="{{ $publisher->logoUrl() }}" alt="" class="max-h-full max-w-full object-contain">
                    @else<span class="font-display text-lg font-bold text-muted">{{ $publisher->initials() }}</span>@endif
                </span>
            @endisset
            <div class="min-w-0 flex-1">
                <h1 class="text-2xl font-extrabold sm:text-3xl">{{ $heading }}</h1>
                @if ($products)
                    <p class="mt-1 text-sm text-muted">{{ number_format($products->total(), 0, ',', '.') }} ürün</p>
                @endif
                @if ($description)<p class="mt-3 max-w-3xl text-[15px] text-muted">{{ $description }}</p>@endif
            </div>
        </div>

        @php($chipList = $children->isNotEmpty() ? $children : ($chips ?? collect()))
        @if ($chipList->isNotEmpty())
            <div class="-mx-4 mt-5 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] sm:mx-0 sm:flex-wrap sm:px-0">
                @foreach ($chipList as $chip)
                    <a href="{{ route('category.show', $chip) }}" class="shrink-0 rounded-full border border-line bg-white px-4 py-2 text-sm font-medium hover:border-primary hover:bg-primary-soft hover:text-primary-dark">
                        {{ $chip->name }}@isset($chip->products_count)<span class="ml-1 text-xs text-muted">{{ $chip->products_count }}</span>@endisset
                    </a>
                @endforeach
            </div>
        @endif

        <div class="mt-6 grid gap-6 lg:grid-cols-[260px_1fr]">
            {{-- Filtreler: masaüstünde yan panel, mobilde açılır panel --}}
            @if ($facets)
                <div x-data="toggle">
                    <button type="button" x-on:click="toggle" class="btn-ghost w-full lg:hidden">
                        <x-icon name="list" class="size-4" />Filtrele @if ($filterCount)<span class="badge bg-primary text-white">{{ $filterCount }}</span>@endif
                    </button>
                    <form method="get" x-bind:class="slideInClass" class="mt-3 hidden space-y-5 rounded-2xl border border-line/70 bg-white p-4 lg:mt-0 lg:block [&.translate-x-0]:block">
                        @if (isset($search) && $search !== '')<input type="hidden" name="q" value="{{ $search }}">@endif
                        <input type="hidden" name="sirala" value="{{ $sort }}">

                        @if (! isset($publisher) && $facets['publishers']->count() > 1)
                            <fieldset x-data="filterList">
                                <legend class="mb-2 text-sm font-bold">Yayınevi</legend>
                                @if ($facets['publishers']->count() > 8)
                                    <input type="search" x-model="term" placeholder="Yayınevi ara" class="field-input mb-2 py-1.5 text-sm" aria-label="Yayınevi ara">
                                @endif
                                <div class="max-h-60 space-y-1 overflow-y-auto pr-1">
                                    @foreach ($facets['publishers'] as $p)
                                        <label class="flex cursor-pointer items-center gap-2 rounded-lg px-1.5 py-1 text-sm hover:bg-surface" data-name="{{ \App\Support\Text::key($p->name) }}" x-show="matches">
                                            <input type="checkbox" name="yayinevi[]" value="{{ $p->id }}" @checked(in_array($p->id, $selectedPublishers)) class="accent-primary" data-autosubmit>
                                            <span class="flex-1 truncate">{{ $p->name }}</span><span class="text-xs text-muted">{{ $p->products_count }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endif

                        <fieldset>
                            <legend class="mb-2 text-sm font-bold">Baskı</legend>
                            @foreach (['' => 'Tümü', 'siyah-beyaz' => 'Siyah-beyaz', 'renkli' => 'Renkli'] as $v => $l)
                                <label class="flex cursor-pointer items-center gap-2 rounded-lg px-1.5 py-1 text-sm hover:bg-surface">
                                    <input type="radio" name="tip" value="{{ $v }}" @checked(request('tip', '') === (string) $v) class="accent-primary" data-autosubmit> {{ $l }}
                                </label>
                            @endforeach
                        </fieldset>

                        <fieldset>
                            <legend class="mb-2 text-sm font-bold">Fiyat (₺)</legend>
                            <div class="flex items-center gap-2">
                                <input type="number" name="min" min="0" step="1" value="{{ request('min') }}" placeholder="{{ $facets['price']['min'] ?? 'En az' }}" class="field-input py-1.5 text-sm" aria-label="En düşük fiyat">
                                <span class="text-muted">–</span>
                                <input type="number" name="max" min="0" step="1" value="{{ request('max') }}" placeholder="{{ $facets['price']['max'] ?? 'En çok' }}" class="field-input py-1.5 text-sm" aria-label="En yüksek fiyat">
                            </div>
                        </fieldset>

                        @if ($facets['years']->count() > 1)
                            <fieldset>
                                <legend class="mb-2 text-sm font-bold">Baskı yılı</legend>
                                <select name="yil" class="field-input py-1.5 text-sm" data-autosubmit>
                                    <option value="">Tümü</option>
                                    @foreach ($facets['years'] as $y)<option value="{{ $y }}" @selected((int) request('yil') === (int) $y)>{{ $y }}</option>@endforeach
                                </select>
                            </fieldset>
                        @endif

                        <div class="flex gap-2">
                            <button class="btn-primary flex-1">Uygula</button>
                            @if ($filterCount)
                                <a href="{{ url()->current() }}{{ isset($search) && $search !== '' ? '?q='.urlencode($search) : '' }}" class="btn-ghost">Temizle</a>
                            @endif
                        </div>
                    </form>
                </div>
            @endif

            <div class="{{ $facets ? '' : 'lg:col-span-2' }}">
                @if ($products && $products->total())
                    <div class="mb-4 flex items-center justify-end gap-2">
                        <form method="get" class="flex items-center gap-2">
                            @foreach (request()->except(['sirala', 'page']) as $k => $v)
                                @foreach ((array) $v as $vv)<input type="hidden" name="{{ is_array($v) ? $k.'[]' : $k }}" value="{{ $vv }}">@endforeach
                            @endforeach
                            <label for="sirala" class="text-sm text-muted">Sırala</label>
                            <select id="sirala" name="sirala" class="field-input w-auto py-1.5 text-sm" data-autosubmit>
                                @foreach (\App\Domain\Catalog\ProductListing::SORTS as $k => $l)<option value="{{ $k }}" @selected($sort === $k)>{{ $l }}</option>@endforeach
                            </select>
                        </form>
                    </div>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 xl:grid-cols-4">
                        @foreach ($products as $i => $product)
                            <x-product-card :product="$product" :eager="$i < 4" />
                        @endforeach
                    </div>
                    <div class="mt-8">{{ $products->onEachSide(1)->links() }}</div>
                @else
                    <div class="rounded-2xl border border-dashed border-line bg-white px-6 py-16 text-center">
                        <x-icon name="search" class="mx-auto size-10 text-slate-300" />
                        <p class="mt-4 font-display text-lg font-bold">Ürün bulunamadı</p>
                        <p class="mt-1 text-sm text-muted">
                            @if ($filterCount)Filtreleri azaltmayı deneyin.@else Farklı bir kelime, yazar adı veya ISBN ile arayabilirsiniz.@endif
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.store>
