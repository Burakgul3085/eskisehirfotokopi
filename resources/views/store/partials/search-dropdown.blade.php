{{--
    Anlık arama açılır paneli (liveSearch bileşeni, resources/js/components.js). Header ve ana sayfa arama kutusu ortak kullanır.
    Yalnız yazınca açılır ve yalnız eşleşenleri gösterir: en üstte adı birebir eşleşen hedef ("doğrudan git"),
    altında ürünler (solda) ve sayfa/kategori/yayınevi/yazar/SSS (sağda). Aranan kelimeler vurgulanır (sunucudan "hl").
--}}
<div x-show="isOpen" x-cloak
     class="search-panel absolute inset-x-0 top-full z-50 mt-2 overflow-y-auto rounded-2xl border border-line bg-white text-left text-ink shadow-lift {{ $max ?? 'max-h-[75vh]' }}">
    <div x-show="isLoading" class="search-loading h-0.5 w-full overflow-hidden rounded-t-2xl bg-primary-soft"><span class="block h-full w-1/3 bg-primary"></span></div>

    <div x-show="hasQuery">
        {{-- Doğrudan git --}}
        <a x-show="hasDirect" x-bind:href="directUrl" data-suggest class="search-direct m-2 flex items-center gap-3 rounded-xl px-3 py-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary text-white"><x-icon name="arrow-right" class="size-4" /></span>
            <span class="min-w-0 flex-1">
                <span class="block text-[11px] font-bold tracking-wider text-primary uppercase" x-text="directType"></span>
                <span class="line-clamp-1 font-semibold" x-mark="directHl"></span>
            </span>
            <span class="hidden shrink-0 items-center gap-1 rounded-lg bg-white px-2 py-1 text-xs font-semibold text-muted ring-1 ring-line sm:flex">Git <kbd class="font-sans">↵</kbd></span>
        </a>

        <p x-show="isEmpty" class="px-4 py-8 text-center text-sm text-muted">Eşleşen sonuç bulunamadı.</p>

        {{-- İki sütun yalnız hem ürün hem diğer sonuç varken: md:grid-cols-[1.35fr_1fr] --}}
        <div class="grid gap-x-2 px-2 pb-2" x-bind:class="gridClass">
            <div x-show="hasProducts" class="min-w-0">
                <p class="px-3 pt-2 pb-1 text-[11px] font-bold tracking-wider text-muted uppercase">Ürünler</p>
                <template x-for="item in products" x-bind:key="item.url">
                    <a x-bind:href="item.url" data-suggest class="search-hit flex items-center gap-3 rounded-xl px-3 py-2">
                        <span class="flex h-14 w-11 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-surface">
                            <img x-show="item.image" x-bind:src="item.image" alt="" class="max-h-full max-w-full object-contain" loading="lazy">
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="line-clamp-1 text-sm font-semibold" x-mark="item.hl"></span>
                            <span class="line-clamp-1 text-xs text-muted" x-text="item.meta"></span>
                        </span>
                        <span class="shrink-0 text-sm font-bold" x-text="item.price"></span>
                    </a>
                </template>
            </div>

            <div x-show="hasOthers" class="min-w-0" x-bind:class="sideClass">
                <template x-for="group in otherGroups" x-bind:key="group.key">
                    <div>
                        <p class="px-3 pt-2 pb-1 text-[11px] font-bold tracking-wider text-muted uppercase" x-text="group.label"></p>
                        <template x-for="item in group.items" x-bind:key="item.url">
                            <a x-bind:href="item.url" data-suggest class="search-hit flex items-center gap-2.5 rounded-xl px-3 py-2">
                                <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-primary-soft text-primary"><x-icon name="search" class="size-3.5" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="line-clamp-1 text-sm font-semibold" x-mark="item.hl"></span>
                                    <span x-show="item.meta" class="line-clamp-1 text-xs text-muted" x-text="item.meta"></span>
                                </span>
                            </a>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        <a x-show="hasAll" x-bind:href="results.all_url" data-suggest
           class="m-2 mt-0 flex items-center justify-center gap-2 rounded-xl bg-primary-soft px-3 py-2.5 text-sm font-semibold text-primary-dark transition hover:bg-primary hover:text-white focus:bg-primary focus:text-white focus:outline-none">
            <span x-text="totalLabel"></span><x-icon name="arrow-right" class="size-4" />
        </a>
    </div>
</div>
