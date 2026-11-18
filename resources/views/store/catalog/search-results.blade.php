@php
    /**
     * Arama sayfasının ürün dışı sonuçları ve bilgilendirme şeridi (CatalogController@search → SiteSearch).
     * $searchMode: exact | corrected | partial | none
     */
    $groups = [
        'pages' => ['Sayfalar ve bölümler', 'list'],
        'categories' => ['Kategoriler', 'layers'],
        'publishers' => ['Yayınevleri', 'book'],
        'authors' => ['Yazarlar', 'user'],
        'faq' => ['Sıkça sorulan sorular', 'chat'],
    ];
    $hasOthers = collect($searchOthers)->flatten(1)->isNotEmpty();
@endphp

@if ($searchMode === 'none')
    <div class="search-note mt-5 flex items-start gap-3 rounded-2xl border border-dashed border-line bg-white px-5 py-4 text-sm">
        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary"><x-icon name="search" class="size-5" /></span>
        <span>
            <strong class="block text-ink">“{{ $search }}” ile eşleşen ürün yok.</strong>
            <span class="text-muted">Aramanızla eşleşen sayfa ve bölümler aşağıda.</span>
        </span>
    </div>
@endif

@if ($hasOthers)
    <div class="search-others mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($groups as $key => [$label, $icon])
            @continue(empty($searchOthers[$key]))
            <section class="card overflow-hidden">
                <h2 class="flex items-center gap-2.5 border-b border-line px-4 py-3 text-sm font-bold">
                    <span class="flex size-7 items-center justify-center rounded-lg bg-primary-soft text-primary"><x-icon :name="$icon" class="size-3.5" /></span>
                    {{ $label }}
                    <span class="ml-auto rounded-full bg-surface px-2 py-0.5 text-xs font-semibold text-muted">{{ count($searchOthers[$key]) }}</span>
                </h2>
                <ul class="divide-y divide-line">
                    @foreach ($searchOthers[$key] as $item)
                        <li>
                            <a href="{{ $item['url'] }}" class="search-hit group flex items-center gap-3 px-4 py-3">
                                <span class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-surface text-primary ring-1 ring-line">
                                    @if (! empty($item['image']))<img src="{{ $item['image'] }}" alt="" class="max-h-full max-w-full object-contain p-1">
                                    @else<x-icon :name="$item['icon'] ?? $icon" class="size-4" />@endif
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-ink group-hover:text-primary">{{ $item['title'] }}</span>
                                    @if (! empty($item['meta']))<span class="line-clamp-1 text-xs text-muted">{{ $item['meta'] }}</span>@endif
                                </span>
                                <x-icon name="chevron-right" class="size-4 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-primary" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
@endif
