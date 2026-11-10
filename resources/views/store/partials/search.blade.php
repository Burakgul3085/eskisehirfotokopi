{{-- Anlık aramalı arama kutusu (header). JavaScript yoksa normal arama formu olarak çalışır. --}}
<form action="{{ route('search') }}" method="get" role="search" class="relative" x-data="liveSearch" data-url="{{ route('search.suggest') }}"
      x-on:click.outside="close" x-on:keydown.escape="close" x-on:keydown.down="down" x-on:keydown.up="up">
    <label for="{{ $id }}" class="sr-only">Ara</label>
    <input id="{{ $id }}" name="q" type="search" value="{{ request()->routeIs('search') ? request('q') : '' }}" x-ref="input" x-model="q"
           x-on:input="onInput" x-on:focus="onFocus" autocomplete="off" placeholder="Kitap, yayınevi, yazar veya ISBN ara…"
           class="w-full rounded-2xl border border-line bg-surface py-3 pr-14 pl-5 text-[15px] transition placeholder:text-slate-400 focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/12 focus:outline-none">
    <button class="absolute top-1.5 right-1.5 flex size-10 items-center justify-center rounded-xl bg-primary text-white hover:bg-primary-hover" aria-label="Ara">
        <x-icon name="search" class="size-5" />
    </button>

    <div x-show="isOpen" x-cloak
         class="absolute inset-x-0 top-full z-50 mt-2 max-h-[75vh] overflow-y-auto rounded-2xl border border-line bg-white p-2 shadow-lift">
        <p x-show="isEmpty" class="px-3 py-6 text-center text-sm text-muted">Sonuç bulunamadı. Farklı bir kelime veya ISBN deneyin.</p>

        <div x-show="hasProducts">
            <p class="px-3 pt-2 pb-1 text-[11px] font-semibold tracking-wider text-muted uppercase">Ürünler</p>
            <template x-for="item in results.products" x-bind:key="item.url">
                <a x-bind:href="item.url" data-suggest class="flex items-center gap-3 rounded-xl px-3 py-2 hover:bg-surface focus:bg-surface focus:outline-none">
                    <span class="flex h-14 w-11 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-surface">
                        <img x-show="item.image" x-bind:src="item.image" alt="" class="max-h-full max-w-full object-contain" loading="lazy">
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="line-clamp-1 text-sm font-semibold text-ink" x-text="item.title"></span>
                        <span class="line-clamp-1 text-xs text-muted" x-text="item.meta"></span>
                    </span>
                    <span class="shrink-0 text-sm font-bold text-ink" x-text="item.price"></span>
                </a>
            </template>
        </div>

        <div x-show="hasPublishers">
            <p class="px-3 pt-3 pb-1 text-[11px] font-semibold tracking-wider text-muted uppercase">Yayınevleri</p>
            <template x-for="item in results.publishers" x-bind:key="item.url">
                <a x-bind:href="item.url" data-suggest class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium hover:bg-surface focus:bg-surface focus:outline-none">
                    <x-icon name="book" class="size-4 text-muted" /><span x-text="item.title"></span>
                </a>
            </template>
        </div>

        <div x-show="hasCategories">
            <p class="px-3 pt-3 pb-1 text-[11px] font-semibold tracking-wider text-muted uppercase">Kategoriler</p>
            <template x-for="item in results.categories" x-bind:key="item.url">
                <a x-bind:href="item.url" data-suggest class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium hover:bg-surface focus:bg-surface focus:outline-none">
                    <x-icon name="layers" class="size-4 text-muted" /><span x-text="item.title"></span>
                </a>
            </template>
        </div>

        <a x-show="hasProducts" x-bind:href="results.all_url" data-suggest x-text="totalLabel"
           class="mt-2 block rounded-xl bg-primary-soft px-3 py-2.5 text-center text-sm font-semibold text-primary-dark hover:bg-primary-line focus:outline-none"></a>
    </div>
</form>
