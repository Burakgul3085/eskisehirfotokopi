<section class="container-page py-8 sm:py-10">
    <div class="mb-5 flex items-end justify-between gap-4">
        <div>
            <h2 class="text-xl font-extrabold sm:text-2xl">{{ $block->title ?: 'Yayınevleri' }}</h2>
            @if ($block->subtitle)<p class="mt-1 text-sm text-muted">{{ $block->subtitle }}</p>@endif
        </div>
        <a href="{{ route('publishers.index') }}" class="shrink-0 text-sm font-semibold text-primary hover:underline">Tüm yayınevleri</a>
    </div>
    <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6">
        @foreach ($data as $publisher)
            <a href="{{ route('publisher.show', $publisher) }}" title="{{ $publisher->name }}"
               class="group flex aspect-[3/2] items-center justify-center rounded-2xl border border-line/70 bg-white p-3 transition hover:border-primary-line hover:shadow-lift">
                @if ($publisher->logoUrl())
                    <img src="{{ $publisher->logoUrl() }}" alt="{{ $publisher->name }}" loading="lazy" class="max-h-full max-w-full object-contain grayscale-[30%] transition group-hover:grayscale-0">
                @else
                    <span class="line-clamp-2 text-center font-display text-sm font-bold text-muted group-hover:text-primary">{{ $publisher->name }}</span>
                @endif
            </a>
        @endforeach
    </div>
</section>
