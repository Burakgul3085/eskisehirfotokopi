{{-- Sıkça Sorulan Sorular: "# Grup" ve "## Soru" satırlarından akordeon. Üst bantta büyük arama kutusu (filterList
     bileşeni, anlık), soru ve konu sayısı; konular simgeli başlıklar ve sayılı çiplerle; arama motorları için FAQPage verisi. --}}
@php
    use App\Support\Text;
    $groups = $page->faqGroups();
    $total = collect($groups)->sum(fn ($g) => count($g['items']));
    $schema = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => collect($groups)->flatMap(fn ($g) => $g['items'])
        ->map(fn ($i) => ['@type' => 'Question', 'name' => $i['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $i['plain']]])->values()->all()];
    // Konu başlığına göre simge (eşleşme yoksa soru işareti)
    $iconFor = function (?string $title): string {
        $k = Text::key((string) $title);
        foreach (['siparis' => 'package', 'teslim' => 'truck', 'kargo' => 'truck', 'odeme' => 'cash', 'baski' => 'printer', 'fotokopi' => 'printer',
            'belge' => 'printer', 'iade' => 'refresh', 'cayma' => 'refresh', 'uyelik' => 'user', 'gizlilik' => 'lock', 'hesap' => 'user', 'urun' => 'book'] as $needle => $icon) {
            if (str_contains($k, $needle)) {
                return $icon;
            }
        }

        return 'help';
    };
    $meta = fn (string $key, int $n) => $settings[$key] ? str_replace(':n', (string) $n, $settings[$key]) : null;
    $n = 0;
@endphp
<x-layouts.store :title="$page->title" :description="$page->meta_description ?: \Illuminate\Support\Str::limit(collect($groups)->flatMap(fn ($g) => $g['items'])->pluck('q')->implode(' · '), 160)">
    <div x-data="filterList">
        <x-info.hero :page="$page" :kicker="$settings['faq_kicker']" :lead="$settings['faq_lead']"
            :facts="[['help', $meta('faq_meta_questions', $total)], ['layers', count($groups) > 1 ? $meta('faq_meta_groups', count($groups)) : null]]">
            <label class="faq-search mt-8 print:hidden">
                <span class="sr-only">{{ $settings['faq_search_placeholder'] }}</span>
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-5 size-5 -translate-y-1/2 text-muted" />
                <input type="search" x-model="term" placeholder="{{ $settings['faq_search_placeholder'] }}" class="faq-search-input">
            </label>
        </x-info.hero>

        <div class="container-page grid gap-8 py-10 lg:grid-cols-[17.5rem_minmax(0,1fr)] lg:py-14 print:block print:py-4">
            @include('store.pages._help-aside')

            <div class="min-w-0">
                @if (count($groups) > 1)
                    <nav class="faq-chips print:hidden" aria-label="Soru grupları">
                        @foreach ($groups as $g)
                            <a href="#{{ $g['id'] }}" class="info-chip inline-flex items-center gap-2"><x-icon :name="$iconFor($g['title'])" class="size-4 text-primary" />{{ $g['title'] }}<span class="faq-chip-count">{{ count($g['items']) }}</span></a>
                        @endforeach
                    </nav>
                @endif

                <div class="space-y-10">
                    @foreach ($groups as $g)
                        <section id="{{ $g['id'] }}" x-show="groupMatches" class="scroll-mt-36">
                            @if ($g['title'])
                                <div class="mb-4 flex items-center gap-3">
                                    <span class="faq-group-icon print:hidden"><x-icon :name="$iconFor($g['title'])" class="size-5" /></span>
                                    <h2 class="font-display text-xl font-extrabold sm:text-2xl">{{ $g['title'] }}</h2>
                                    <span class="faq-group-line" aria-hidden="true"></span>
                                    <span class="text-xs font-bold text-muted tabular-nums">{{ count($g['items']) }}</span>
                                </div>
                            @endif
                            <div class="space-y-3">
                                @foreach ($g['items'] as $item)
                                    @php($n++)
                                    <details id="soru-{{ $n }}" class="info-faq group scroll-mt-36" x-show="matches" data-name="{{ Text::key($item['q'].' '.$item['plain']) }}" @if ($n === 1) open @endif>
                                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-semibold sm:px-6 [&::-webkit-details-marker]:hidden">
                                            <span class="flex items-center gap-3.5"><span class="info-faq-num">{{ str_pad((string) $n, 2, '0', STR_PAD_LEFT) }}</span>{{ $item['q'] }}</span>
                                            <span class="info-faq-toggle"><x-icon name="plus" class="size-4" /></span>
                                        </summary>
                                        <div class="info-faq-answer prose-page">{{ $item['a'] }}</div>
                                    </details>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                    <div x-show="noMatch" x-cloak class="rounded-3xl border border-dashed border-line px-6 py-12 text-center">
                        <span class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-surface text-muted"><x-icon name="search" class="size-5" /></span>
                        <p class="mt-3 text-muted">{{ $settings['faq_empty'] }}</p>
                    </div>
                </div>
                <x-info.print-note />

                @if ($settings['faq_more_title'])
                    <div class="surface-deep relative isolate mt-12 flex flex-col items-start gap-4 overflow-hidden rounded-3xl p-6 text-white sm:flex-row sm:items-center sm:p-8 print:hidden">
                        <div class="info-hero-grid pointer-events-none absolute inset-0 -z-10" aria-hidden="true"></div>
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
    </div>

    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
</x-layouts.store>
