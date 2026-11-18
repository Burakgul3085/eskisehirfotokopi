{{-- Sıkça Sorulan Sorular: "# Grup" ve "## Soru" satırlarından akordeon; grup çipleri, sorularda anlık arama
     (filterList bileşeni) ve arama motorları için FAQPage verisi. --}}
@php
    use App\Support\Text;
    $groups = $page->faqGroups();
    $schema = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => collect($groups)->flatMap(fn ($g) => $g['items'])
        ->map(fn ($i) => ['@type' => 'Question', 'name' => $i['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $i['plain']]])->values()->all()];
    $n = 0;
@endphp
<x-layouts.store :title="$page->title" :description="$page->meta_description ?: \Illuminate\Support\Str::limit(collect($groups)->flatMap(fn ($g) => $g['items'])->pluck('q')->implode(' · '), 160)">
    <x-info.hero :page="$page" :date="false" />

    <div class="container-page grid gap-8 py-10 lg:grid-cols-[17rem_minmax(0,1fr)]">
        @include('store.pages._help-aside')

        <div x-data="filterList" class="min-w-0">
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center">
                <label class="relative min-w-0 flex-1">
                    <span class="sr-only">{{ $settings['faq_search_placeholder'] }}</span>
                    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted" />
                    <input type="search" x-model="term" placeholder="{{ $settings['faq_search_placeholder'] }}" class="field-input rounded-2xl py-3 pl-11">
                </label>
                @if (count($groups) > 1)
                    <nav class="flex flex-wrap gap-2" aria-label="Soru grupları">
                        @foreach ($groups as $g)<a href="#{{ $g['id'] }}" class="info-chip">{{ $g['title'] }}</a>@endforeach
                    </nav>
                @endif
            </div>

            <div class="space-y-8">
                @foreach ($groups as $g)
                    <section id="{{ $g['id'] }}" x-show="groupMatches" class="scroll-mt-36">
                        @if ($g['title'])<h2 class="mb-3 font-display text-xl font-extrabold">{{ $g['title'] }}</h2>@endif
                        <div class="divide-y divide-line overflow-hidden rounded-3xl border border-line bg-white">
                            @foreach ($g['items'] as $item)
                                @php($n++)
                                <details id="soru-{{ $n }}" class="info-faq group scroll-mt-36" x-show="matches" data-name="{{ Text::key($item['q'].' '.$item['plain']) }}" @if ($n === 1) open @endif>
                                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-semibold hover:bg-surface [&::-webkit-details-marker]:hidden">
                                        <span class="flex items-center gap-3"><span class="info-faq-num">{{ $n }}</span>{{ $item['q'] }}</span>
                                        <span class="info-faq-toggle"><x-icon name="plus" class="size-4" /></span>
                                    </summary>
                                    <div class="prose-page px-5 pb-5 pl-16">{{ $item['a'] }}</div>
                                </details>
                            @endforeach
                        </div>
                    </section>
                @endforeach
                <p x-show="noMatch" x-cloak class="rounded-3xl border border-dashed border-line px-6 py-10 text-center text-muted">{{ $settings['faq_empty'] }}</p>
            </div>

            @if ($settings['faq_more_title'])
                <div class="surface-deep mt-10 flex flex-col items-start gap-4 rounded-3xl p-6 text-white sm:flex-row sm:items-center sm:p-8">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-accent text-ink"><x-icon name="chat" class="size-6" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="font-display text-lg font-extrabold">{{ $settings['faq_more_title'] }}</p>
                        @if ($settings['faq_more_text'])<p class="mt-1 text-sm text-slate-300">{{ $settings['faq_more_text'] }}</p>@endif
                    </div>
                    <a href="{{ route('contact') }}" class="btn-accent shrink-0">{{ $settings['pages_contact_link'] ?: $settings['faq_more_title'] }}<x-icon name="arrow-right" class="size-4" /></a>
                </div>
            @endif
        </div>
    </div>

    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
</x-layouts.store>
