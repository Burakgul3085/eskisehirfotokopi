{{--
    Anlık arama açılır paneli (liveSearch bileşeni, resources/js/components.js). Header ve ana sayfa arama kutusu ortak kullanır.
    Kutu boşken hızlı erişim (site bölümleri, popüler kategoriler); yazınca ürünler solda, sayfa/kategori/yayınevi/yazar/SSS sağda.
--}}
<div x-show="isOpen" x-cloak
     class="search-panel absolute inset-x-0 top-full z-50 mt-2 overflow-y-auto rounded-2xl border border-line bg-white text-left text-ink shadow-lift {{ $max ?? 'max-h-[75vh]' }}">
    <div x-show="isLoading" class="search-loading h-0.5 w-full overflow-hidden rounded-t-2xl bg-primary-soft"><span class="block h-full w-1/3 bg-primary"></span></div>

    {{-- Hızlı erişim --}}
    <div x-show="hasQuick" class="p-3">
        <p class="px-2 pb-2 text-[11px] font-bold tracking-wider text-muted uppercase">Hızlı erişim</p>
        <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-3">
            <template x-for="item in quickSections" x-bind:key="item.url">
                <a x-bind:href="item.url" data-suggest class="search-quick flex items-center gap-2 rounded-xl px-3 py-2.5 text-sm font-semibold">
                    <span class="search-quick-dot flex size-7 shrink-0 items-center justify-center rounded-lg bg-primary-soft text-primary"><x-icon name="arrow-right" class="size-3.5" /></span>
                    <span class="truncate" x-text="item.title"></span>
                </a>
            </template>
        </div>
        <div x-show="hasQuickCategories">
            <p class="px-2 pt-3 pb-2 text-[11px] font-bold tracking-wider text-muted uppercase">Popüler kategoriler</p>
            <div class="flex flex-wrap gap-1.5 px-1">
                <template x-for="item in quickCategories" x-bind:key="item.url">
                    <a x-bind:href="item.url" data-suggest x-text="item.title" class="rounded-full border border-line px-3 py-1.5 text-xs font-semibold transition hover:border-primary hover:bg-primary-soft hover:text-primary-dark focus:border-primary focus:outline-none"></a>
                </template>
            </div>
        </div>
    </div>

    {{-- Arama sonuçları --}}
    <div x-show="hasQuery">
        <p x-show="isCorrected" x-text="correctionLabel" class="mx-3 mt-3 rounded-xl bg-accent-soft px-3 py-2 text-xs font-semibold text-accent-ink"></p>
        <p x-show="isEmpty" class="px-4 py-8 text-center text-sm text-muted">Eşleşen sonuç bulunamadı. Farklı bir kelime, yazar adı veya ISBN deneyin.</p>

        {{-- İki sütun yalnız hem ürün hem diğer sonuç varken: md:grid-cols-[1.35fr_1fr] --}}
        <div class="grid gap-x-2 p-2" x-bind:class="gridClass">
            <div x-show="hasProducts" class="min-w-0">
                <p class="px-3 pt-2 pb-1 text-[11px] font-bold tracking-wider text-muted uppercase">Ürünler</p>
                <template x-for="item in results.products" x-bind:key="item.url">
                    <a x-bind:href="item.url" data-suggest class="search-hit flex items-center gap-3 rounded-xl px-3 py-2">
                        <span class="flex h-14 w-11 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-surface">
                            <img x-show="item.image" x-bind:src="item.image" alt="" class="max-h-full max-w-full object-contain" loading="lazy">
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="line-clamp-1 text-sm font-semibold" x-text="item.title"></span>
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
                                    <span class="line-clamp-1 text-sm font-semibold" x-text="item.title"></span>
                                    <span x-show="item.meta" class="line-clamp-1 text-xs text-muted" x-text="item.meta"></span>
                                </span>
                            </a>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        <a x-bind:href="results.all_url" data-suggest
           class="m-2 mt-0 flex items-center justify-center gap-2 rounded-xl bg-primary-soft px-3 py-2.5 text-sm font-semibold text-primary-dark transition hover:bg-primary hover:text-white focus:bg-primary focus:text-white focus:outline-none">
            <span x-text="totalLabel"></span><x-icon name="arrow-right" class="size-4" />
        </a>
    </div>
</div>
