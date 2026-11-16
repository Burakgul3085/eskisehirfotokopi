@php
    use App\Support\Settings;
    use App\Support\Text;
    $tones = ['from-primary to-primary-dark', 'from-accent to-amber-700', 'from-sky-500 to-blue-800', 'from-pink-500 to-rose-800', 'from-violet-500 to-purple-900', 'from-emerald-500 to-green-800'];
@endphp
<x-layouts.store :title="Settings::get('publishers_kicker', 'Yayınevleri')" description="Tüm yayınevlerinin kitapları, soru bankaları ve deneme sınavları." :flush="true">
    <div x-data="filterList">
        {{--
            Koyu üst alan: başlık, sayılar ve arama; sağda dönen 3D kitap karuseli (resources/js/scenes/publisher-carousel.js;
            her kitap bir yayınevi, tıklanınca sayfası açılır). Yazılar Yönetim › Ayarlar › Genel › Yayınevleri sayfası'ndan.
        --}}
        <section class="hero-3d surface-deep relative isolate overflow-hidden text-white">
            <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
                <div class="hero-grid absolute inset-0"></div>
                <div class="hero-spot absolute inset-0"></div>
            </div>
            <div class="container-page relative grid items-center gap-6 pt-6 pb-24 lg:grid-cols-[1fr_1.1fr] lg:pb-28">
                <div class="relative z-10">
                    <x-breadcrumb :items="[[Settings::get('publishers_kicker', 'Yayınevleri'), null]]" class="hero-breadcrumb" />
                    @if (Settings::get('publishers_kicker'))
                        <p class="hero-reveal mt-6 flex items-center gap-3 text-xs font-bold tracking-[0.25em] text-accent uppercase" style="--d: 0">
                            <x-icon name="book" class="size-4" />{{ Settings::get('publishers_kicker') }}
                        </p>
                    @endif
                    <h1 class="hero-reveal mt-4 text-4xl leading-[1.05] font-extrabold tracking-tight sm:text-5xl" style="--d: 1">
                        <span class="block bg-gradient-to-br from-white via-white to-white/70 bg-clip-text text-transparent">{{ Settings::get('publishers_title', 'Yayınevleri') }}</span>
                        @if (Settings::get('publishers_title_accent'))<span class="block text-accent">{{ Settings::get('publishers_title_accent') }}</span>@endif
                    </h1>
                    @if (Settings::get('publishers_intro'))<p class="hero-reveal mt-4 max-w-lg text-base leading-relaxed text-white/75" style="--d: 2">{{ Settings::get('publishers_intro') }}</p>@endif

                    <div class="hero-reveal mt-6 flex flex-wrap gap-3" style="--d: 3">
                        <span class="pub-stat"><strong class="font-display text-2xl font-extrabold text-white">{{ $total }}</strong><span>yayınevi</span></span>
                        <span class="pub-stat"><strong class="font-display text-2xl font-extrabold text-accent">{{ number_format($productTotal, 0, ',', '.') }}</strong><span>ürün</span></span>
                    </div>

                    <label class="hero-reveal relative mt-6 block max-w-lg" style="--d: 4">
                        <span class="sr-only">{{ Settings::get('publishers_search', 'Yayınevi ara…') }}</span>
                        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-white/60" />
                        <input type="search" x-model="term" placeholder="{{ Settings::get('publishers_search', 'Yayınevi ara…') }}"
                               class="w-full rounded-2xl border border-white/15 bg-white/10 py-3.5 pr-4 pl-12 text-white placeholder-white/55 shadow-[0_20px_40px_-24px_rgba(0,0,0,0.7)] backdrop-blur-xl transition outline-none focus:border-accent focus:bg-white/15 focus:ring-4 focus:ring-accent/25">
                    </label>
                </div>

                <div data-scene="publisher-carousel" data-count-label="ürün" class="relative h-[300px] sm:h-[380px] lg:h-[440px]">
                    <script type="application/json" data-carousel>@json($carousel)</script>
                    <div class="hero-fallback pointer-events-none absolute inset-0 flex items-center justify-center transition-opacity duration-1000" aria-hidden="true">
                        <div class="size-56 rounded-full bg-gradient-to-br from-white/25 to-accent/20 blur-2xl md:size-72"></div>
                    </div>
                    <div class="hero-stage absolute inset-0">
                        <canvas data-scene-canvas class="block size-full" aria-hidden="true"></canvas>
                        <div data-carousel-tip class="pub-tip pointer-events-none absolute top-0 left-0 rounded-xl bg-white px-3 py-1.5 text-sm font-semibold whitespace-nowrap text-ink shadow-lg" role="presentation"></div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Alt kısım: renkli ışık lekeli zemin; öne çıkanlar renk şeritli kartlar, tam liste tek beyaz panelde --}}
        <div class="pub-lower page-tint relative pb-20">
            <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden" aria-hidden="true">
                <div class="pub-blob absolute top-40 -left-32 size-[28rem] rounded-full bg-primary/20"></div>
                <div class="pub-blob absolute top-[30rem] -right-24 size-[26rem] rounded-full bg-accent/20" style="animation-delay: -6s"></div>
                <div class="pub-blob absolute bottom-0 left-1/3 size-[22rem] rounded-full bg-sky-400/15" style="animation-delay: -12s"></div>
            </div>
            <div class="container-page relative z-10">
                @php($few = $featured->count() <= 3)
                @if ($featured->isNotEmpty())
                    <section class="-mt-20" x-show="groupMatches">
                        <h2 class="pub-heading mb-4 flex items-center gap-2 text-xs font-bold tracking-[0.25em] text-white uppercase"><x-icon name="heart" class="size-4 text-accent" />{{ Settings::get('publishers_featured', '') }}</h2>
                        <div class="grid gap-4 {{ $few ? 'sm:grid-cols-2 lg:grid-cols-3' : 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6' }}">
                            @foreach ($featured as $i => $p)
                                @php($tone = $tones[crc32($p->name) % count($tones)])
                                <a href="{{ route('publisher.show', $p) }}" data-name="{{ Text::key($p->name) }}" x-show="matches"
                                   class="pub-card group relative flex overflow-hidden rounded-3xl bg-white {{ $few ? 'items-center gap-5 p-5' : 'flex-col items-center p-5 text-center' }}" style="--i: {{ $i }}">
                                    <span class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r {{ $tone }}"></span>
                                    <span class="absolute -top-16 -right-16 size-40 rounded-full bg-gradient-to-br opacity-0 blur-2xl transition duration-500 group-hover:opacity-40 {{ $tone }}"></span>
                                    <span class="pub-logo relative flex shrink-0 items-center justify-center overflow-hidden rounded-2xl {{ $few ? 'size-24' : 'aspect-square w-full max-w-24' }} {{ $p->logoUrl() ? 'bg-white ring-1 ring-line' : 'bg-gradient-to-br text-white '.$tone }}">
                                        @if ($p->logoUrl())<img src="{{ $p->logoUrl() }}" alt="{{ $p->name }}" class="max-h-full max-w-full object-contain p-3" loading="lazy">
                                        @else<span class="font-display text-3xl font-extrabold drop-shadow">{{ $p->initials() }}</span>@endif
                                    </span>
                                    <span class="relative min-w-0 flex-1 {{ $few ? '' : 'mt-4' }}">
                                        <span class="block font-display text-lg leading-snug font-bold text-ink">{{ $p->name }}</span>
                                        <span class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-surface px-2.5 py-1 text-xs font-bold text-muted"><x-icon name="book" class="size-3.5" />{{ $p->products_count }} ürün</span>
                                        <span class="mt-3 flex items-center gap-1 text-sm font-semibold text-primary {{ $few ? '' : 'justify-center' }}">Ürünleri gör<x-icon name="arrow-right" class="size-4 transition group-hover:translate-x-1" /></span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- Tam liste: tek beyaz panel --}}
                <section class="pub-panel {{ $featured->isNotEmpty() ? 'mt-14' : 'mt-10' }} rounded-[2rem] border border-white bg-white/90 p-4 backdrop-blur-xl sm:p-8">
                    <h2 class="flex flex-wrap items-center gap-3 font-display text-2xl font-extrabold sm:text-3xl">{{ Settings::get('publishers_all', '') }}
                        <span class="rounded-full bg-primary-soft px-3 py-0.5 text-sm font-bold text-primary-dark">{{ $total }}</span></h2>
                    @if (Settings::get('publishers_all_sub'))<p class="mt-1 text-sm text-muted">{{ Settings::get('publishers_all_sub') }}</p>@endif

                    <nav class="pub-letters sticky top-[4.5rem] z-20 -mx-4 mt-6 flex gap-1 overflow-x-auto border-y border-line/70 bg-white/95 px-4 py-2.5 backdrop-blur-xl sm:-mx-8 sm:px-8 lg:justify-between" aria-label="Harf dizini">
                        @foreach ($groups as $letter => $list)
                            @if ($list->isNotEmpty())
                                <a href="#harf-{{ $loop->index }}" class="pub-letter flex size-9 shrink-0 lg:size-8 items-center justify-center rounded-xl bg-primary-soft text-sm font-extrabold text-primary-dark">{{ $letter }}</a>
                            @else
                                <span class="flex size-9 shrink-0 items-center justify-center text-sm font-semibold text-slate-300 lg:size-8">{{ $letter }}</span>
                            @endif
                        @endforeach
                    </nav>

                    <p x-show="noMatch" x-cloak class="mt-8 flex flex-col items-center gap-3 rounded-2xl bg-surface px-5 py-10 text-center text-muted"><x-icon name="search" class="size-8 text-slate-300" />{{ Settings::get('publishers_no_match', '') }}</p>

                    <div class="mt-8 space-y-10">
                        @foreach ($groups as $letter => $list)
                            @continue($list->isEmpty())
                            <section id="harf-{{ $loop->index }}" class="scroll-mt-44" x-show="groupMatches">
                                <div class="mb-4 flex items-center gap-4">
                                    <span class="pub-badge flex size-12 shrink-0 items-center justify-center rounded-2xl font-display text-xl font-extrabold text-white">{{ $letter }}</span>
                                    <span class="h-0.5 flex-1 rounded-full bg-gradient-to-r from-primary/40 via-accent/30 to-transparent"></span>
                                    <span class="rounded-full bg-surface px-2.5 py-0.5 text-xs font-bold text-muted">{{ $list->count() }}</span>
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                    @foreach ($list as $p)
                                        @php($tone = $tones[crc32($p->name) % count($tones)])
                                        <a href="{{ route('publisher.show', $p) }}" data-name="{{ Text::key($p->name) }}" x-show="matches"
                                           class="pub-row group relative flex items-center gap-3 overflow-hidden rounded-2xl border border-line/70 bg-white p-3">
                                            <span class="absolute inset-y-0 left-0 w-1 bg-gradient-to-b opacity-0 transition group-hover:opacity-100 {{ $tone }}"></span>
                                            <span class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-xl {{ $p->logoUrl() ? 'bg-white ring-1 ring-line' : 'bg-gradient-to-br text-white shadow-md '.$tone }}">
                                                @if ($p->logoUrl())<img src="{{ $p->logoUrl() }}" alt="" class="max-h-full max-w-full object-contain p-1" loading="lazy">
                                                @else<span class="font-display text-sm font-extrabold">{{ $p->initials() }}</span>@endif
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-sm font-bold text-ink">{{ $p->name }}</span>
                                                <span class="text-xs text-muted">{{ $p->products_count }} ürün</span>
                                            </span>
                                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-surface text-muted transition group-hover:bg-primary group-hover:text-white"><x-icon name="arrow-right" class="size-4" /></span>
                                        </a>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-layouts.store>
