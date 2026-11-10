{{-- Karşılama alanı: başlık, metin, arama ve güven öğeleri (Ayarlar › Genel › Güven şeridi). --}}
@php($tiles = array_slice(\App\Support\Settings::trustItems(), 0, 4))
<section class="relative overflow-hidden bg-gradient-to-br from-primary-dark via-primary to-primary-hover text-white">
    <div class="pointer-events-none absolute -top-24 -right-24 size-96 rounded-full bg-accent/25 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-32 -left-20 size-96 rounded-full bg-white/10 blur-3xl"></div>
    <div class="container-page relative grid items-center gap-10 py-12 md:grid-cols-2 md:py-20">
        <div>
            @if ($block->setting('badge'))
                <span class="badge bg-white/15 text-white ring-1 ring-white/20">{{ $block->setting('badge') }}</span>
            @endif
            <h1 class="mt-5 text-3xl leading-[1.1] font-extrabold sm:text-5xl">{{ $block->setting('heading') ?: $settings['site_tagline'] }}</h1>
            @if ($block->setting('text'))
                <p class="mt-5 max-w-lg text-base text-white/80 sm:text-lg">{{ $block->setting('text') }}</p>
            @endif
            <form action="{{ route('search') }}" method="get" role="search" class="mt-8 flex max-w-lg gap-2 rounded-2xl bg-white p-1.5 shadow-lift">
                <input name="q" type="search" placeholder="Kitap adı, yayınevi, yazar veya ISBN" aria-label="Ara"
                       class="min-w-0 flex-1 rounded-xl px-4 text-[15px] text-ink placeholder:text-slate-400 focus:outline-none">
                <button class="btn-accent px-5">Ara</button>
            </form>
            @if ($block->setting('button_label') && $block->setting('button_url'))
                <a href="{{ $block->setting('button_url') }}" class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-white/90 underline-offset-4 hover:underline">
                    <x-icon name="upload" class="size-4" />{{ $block->setting('button_label') }}
                </a>
            @endif
        </div>
        @if ($tiles)
            <div class="hidden justify-end md:flex">
                <div class="grid w-full max-w-sm grid-cols-2 gap-4">
                    @foreach ($tiles as $i => $tile)
                        <div class="rounded-3xl bg-white/10 p-5 ring-1 ring-white/15 backdrop-blur {{ $i % 2 ? 'translate-y-6' : '' }}">
                            <x-icon :name="$tile['icon']" class="size-8 text-accent" />
                            <p class="mt-6 font-display font-semibold">{{ $tile['title'] }}</p>
                            @if ($tile['text'])<p class="mt-1 text-xs text-white/70">{{ $tile['text'] }}</p>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</section>
