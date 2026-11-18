{{-- Site geneli anlık arama (header): ürün, kategori, yayınevi, yazar, sayfa, SSS. JavaScript yoksa normal arama formu. --}}
<form action="{{ route('search') }}" method="get" role="search" class="relative" x-data="liveSearch" data-url="{{ route('search.suggest') }}"
      x-on:click.outside="close" x-on:keydown.escape="close" x-on:keydown.down="down" x-on:keydown.up="up">
    <label for="{{ $id }}" class="sr-only">Ara</label>
    <input id="{{ $id }}" name="q" type="search" value="{{ request()->routeIs('search') ? request('q') : '' }}" x-ref="input" x-model="q"
           x-on:input="onInput" x-on:focus="onFocus" autocomplete="off" placeholder="{{ \App\Domain\Search\SiteSearch::placeholder() }}"
           class="w-full rounded-2xl border border-line bg-surface py-3 pr-14 pl-12 text-[15px] sm:py-3.5 sm:pr-28 transition placeholder:text-slate-400 focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/12 focus:outline-none">
    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400" />
    <button class="absolute top-1.5 right-1.5 bottom-1.5 flex items-center justify-center gap-2 rounded-xl bg-primary px-3 font-bold text-white hover:bg-primary-hover sm:px-6" aria-label="Ara">
        <x-icon name="arrow-right" class="size-5 sm:hidden" /><span class="hidden sm:inline">Ara</span>
    </button>

    @include('store.partials.search-dropdown')
</form>
