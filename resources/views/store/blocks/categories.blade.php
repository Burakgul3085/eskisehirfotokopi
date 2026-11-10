<section class="container-page py-8 sm:py-10">
    @if ($block->title)
        <div class="mb-5">
            <h2 class="text-xl font-extrabold sm:text-2xl">{{ $block->title }}</h2>
            @if ($block->subtitle)<p class="mt-1 text-sm text-muted">{{ $block->subtitle }}</p>@endif
        </div>
    @endif
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-6">
        @foreach ($data as $i => $category)
            <a href="{{ route('category.show', $category) }}"
               class="group relative flex min-h-28 flex-col justify-between overflow-hidden rounded-2xl border border-line/70 bg-white p-4 transition hover:-translate-y-0.5 hover:border-primary-line hover:shadow-lift">
                @if ($category->image_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($category->image_path) }}" alt="" loading="lazy"
                         class="absolute -right-3 -bottom-3 size-20 object-contain opacity-90 transition group-hover:scale-105">
                @else
                    <span class="absolute -right-4 -bottom-4 size-20 rounded-full {{ ['bg-primary-soft', 'bg-accent-soft', 'bg-info-soft', 'bg-success-soft'][$i % 4] }}"></span>
                @endif
                <span class="relative font-display text-[15px] leading-tight font-bold text-ink group-hover:text-primary">{{ $category->name }}</span>
                @if ($category->child_names)
                    <span class="relative mt-2 line-clamp-2 pr-10 text-xs text-muted">{{ implode(' · ', $category->child_names) }}</span>
                @endif
            </a>
        @endforeach
    </div>
</section>
