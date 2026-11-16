{{-- Kategori kutuları: her biri renk geçişli büyük kart; az kategoride satırı doldurur, çokta ızgaraya dizilir. --}}
@php($tones = ['from-primary to-primary-dark', 'from-accent to-amber-700', 'from-sky-500 to-blue-800', 'from-violet-500 to-purple-900', 'from-pink-500 to-rose-800', 'from-emerald-500 to-green-800'])
<section class="container-page py-8 sm:py-10">
    @if ($block->title)<x-section-head :title="$block->title" :subtitle="$block->subtitle" />@endif
    <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-[repeat(auto-fit,minmax(15rem,1fr))] lg:gap-5">
        @foreach ($data as $i => $category)
            <a href="{{ route('category.show', $category) }}"
               class="group relative isolate flex min-h-36 flex-col overflow-hidden rounded-3xl bg-gradient-to-br p-5 text-white shadow-[0_20px_40px_-24px_rgba(15,23,42,0.55)] transition duration-300 hover:-translate-y-1 sm:min-h-44 sm:p-6 {{ $tones[$i % count($tones)] }}">
                <span class="pointer-events-none absolute -top-10 -right-10 -z-10 size-40 rounded-full bg-white/10 transition duration-500 group-hover:scale-125" aria-hidden="true"></span>
                <span class="pointer-events-none absolute -bottom-16 left-1/3 -z-10 size-44 rounded-full bg-black/10" aria-hidden="true"></span>
                @if ($category->image_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($category->image_path) }}" alt="" loading="lazy"
                         class="pointer-events-none absolute right-3 bottom-3 -z-10 size-24 object-contain drop-shadow-xl transition duration-500 group-hover:scale-110 group-hover:-rotate-3 sm:size-28">
                @else
                    <span class="absolute top-5 right-5 flex size-11 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/25 backdrop-blur sm:size-12"><x-icon name="book" class="size-5 sm:size-6" /></span>
                @endif
                <span class="relative pr-14 font-display text-lg leading-tight font-extrabold sm:text-xl">{{ $category->name }}</span>
                @if ($category->child_names)
                    <span class="relative mt-3 hidden flex-wrap gap-1.5 pr-10 sm:flex">
                        @foreach ($category->child_names as $child)
                            <span class="rounded-full bg-white/15 px-2.5 py-0.5 text-xs font-medium ring-1 ring-white/20">{{ $child }}</span>
                        @endforeach
                    </span>
                    <span class="relative mt-2 line-clamp-2 text-xs text-white/80 sm:hidden">{{ implode(' · ', $category->child_names) }}</span>
                @endif
                <span class="relative mt-auto flex size-9 items-center justify-center self-start rounded-full bg-white text-ink shadow-lg transition group-hover:translate-x-1"><x-icon name="arrow-right" class="size-4" /></span>
            </a>
        @endforeach
    </div>
</section>
