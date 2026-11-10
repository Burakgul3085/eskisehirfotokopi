<x-layouts.store title="Yayınevleri" description="Tüm yayınevlerinin kitapları, soru bankaları ve deneme sınavları.">
    <div class="container-page py-6 sm:py-8" x-data="filterList">
        <x-breadcrumb :items="[['Yayınevleri', null]]" />
        <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-extrabold sm:text-3xl">Yayınevleri</h1>
                <p class="mt-1 text-sm text-muted">{{ $total }} yayınevi</p>
            </div>
            <input type="search" x-model="term" placeholder="Yayınevi ara…" aria-label="Yayınevi ara" class="field-input w-full sm:w-72">
        </div>

        @if ($featured->isNotEmpty())
            <div class="mt-6 grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                @foreach ($featured as $p)
                    <a href="{{ route('publisher.show', $p) }}" title="{{ $p->name }}" data-name="{{ \App\Support\Text::key($p->name) }}" x-show="matches"
                       class="flex aspect-[3/2] items-center justify-center rounded-2xl border border-line/70 bg-white p-3 transition hover:border-primary-line hover:shadow-lift">
                        @if ($p->logoUrl())<img src="{{ $p->logoUrl() }}" alt="{{ $p->name }}" class="max-h-full max-w-full object-contain" loading="lazy">
                        @else<span class="text-center font-display text-sm font-bold text-muted">{{ $p->name }}</span>@endif
                    </a>
                @endforeach
            </div>
        @endif

        {{-- Harf dizini --}}
        <nav class="sticky top-[4.5rem] z-10 -mx-4 mt-8 flex gap-1 overflow-x-auto bg-surface/95 px-4 py-2 backdrop-blur sm:mx-0 sm:flex-wrap sm:px-0" aria-label="Harf dizini">
            @foreach ($groups as $letter => $list)
                @if ($list->isNotEmpty())
                    <a href="#harf-{{ $loop->index }}" class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white text-sm font-bold ring-1 ring-line hover:bg-primary hover:text-white">{{ $letter }}</a>
                @else
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg text-sm font-bold text-slate-300">{{ $letter }}</span>
                @endif
            @endforeach
        </nav>

        <div class="mt-4 space-y-8">
            @foreach ($groups as $letter => $list)
                @continue($list->isEmpty())
                <section id="harf-{{ $loop->index }}" class="scroll-mt-32">
                    <h2 class="mb-3 font-display text-xl font-extrabold text-primary">{{ $letter }}</h2>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        @foreach ($list as $p)
                            <a href="{{ route('publisher.show', $p) }}" data-name="{{ \App\Support\Text::key($p->name) }}" x-show="matches"
                               class="flex items-center gap-3 rounded-xl border border-line/70 bg-white p-2.5 transition hover:border-primary-line hover:shadow-card">
                                <span class="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-surface">
                                    @if ($p->logoUrl())<img src="{{ $p->logoUrl() }}" alt="" class="max-h-full max-w-full object-contain p-1" loading="lazy">
                                    @else<span class="font-display text-xs font-bold text-muted">{{ $p->initials() }}</span>@endif
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold">{{ $p->name }}</span>
                                    <span class="text-xs text-muted">{{ $p->products_count }} ürün</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-layouts.store>
