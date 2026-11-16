{{-- Yayınevleri: logo ya da renkli harf rozeti, ad, ürün sayısı; az yayınevinde satırı doldurur. --}}
<section class="container-page py-8 sm:py-10">
    <x-section-head :title="$block->title ?: 'Yayınevleri'" :subtitle="$block->subtitle">
        <a href="{{ route('publishers.index') }}" class="group/all inline-flex items-center gap-1 rounded-full bg-primary-soft px-3.5 py-1.5 text-sm font-semibold text-primary-dark transition hover:bg-primary hover:text-white">Tüm yayınevleri<x-icon name="arrow-right" class="size-4 transition group-hover/all:translate-x-0.5" /></a>
    </x-section-head>
    <div class="grid gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-[repeat(auto-fit,minmax(18rem,1fr))] lg:gap-5">
        @foreach ($data as $publisher)
            <a href="{{ route('publisher.show', $publisher) }}" title="{{ $publisher->name }}"
               class="group relative flex items-center gap-4 overflow-hidden rounded-3xl border border-line/70 bg-white p-4 transition duration-300 hover:-translate-y-1 hover:border-primary-line hover:shadow-lift sm:p-5">
                <span class="absolute inset-y-0 left-0 w-1.5 bg-gradient-to-b {{ $publisher->tone() }}" aria-hidden="true"></span>
                <span class="pointer-events-none absolute -top-14 -right-14 size-36 rounded-full bg-gradient-to-br opacity-0 blur-2xl transition duration-500 group-hover:opacity-30 {{ $publisher->tone() }}" aria-hidden="true"></span>
                <span class="relative flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl sm:size-[4.5rem] {{ $publisher->logoUrl() ? 'bg-white ring-1 ring-line' : 'bg-gradient-to-br text-white shadow-md '.$publisher->tone() }}">
                    @if ($publisher->logoUrl())<img src="{{ $publisher->logoUrl() }}" alt="{{ $publisher->name }}" loading="lazy" class="max-h-full max-w-full object-contain p-2">
                    @else<span class="font-display text-xl font-extrabold drop-shadow">{{ $publisher->initials() }}</span>@endif
                </span>
                <span class="relative min-w-0 flex-1">
                    <span class="line-clamp-2 font-display text-base leading-snug font-bold text-ink group-hover:text-primary-dark">{{ $publisher->name }}</span>
                    @if ($publisher->products_count !== null)
                        <span class="mt-1.5 inline-flex items-center gap-1.5 rounded-full bg-surface px-2.5 py-0.5 text-xs font-semibold text-muted"><x-icon name="book" class="size-3.5" />{{ $publisher->products_count }} ürün</span>
                    @endif
                </span>
                <span class="relative flex size-9 shrink-0 items-center justify-center rounded-full bg-surface text-muted transition group-hover:bg-primary group-hover:text-white"><x-icon name="arrow-right" class="size-4" /></span>
            </a>
        @endforeach
    </div>
</section>
