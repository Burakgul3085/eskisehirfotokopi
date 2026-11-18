{{-- Yasal metin (Mesafeli Satış, KVKK, Üyelik Koşulları, Çerez Politikası…): üst bantta bölüm sayısı, okuma süresi ve
     yazdır / bağlantı kopyala; solda okuma ilerlemesini ve bulunulan bölümü gösteren içindekiler, yasal metinler menüsü
     ve yardım kartı; metin beyaz belge kartında, altında önceki / sonraki belge. Yazdırınca yalnız belge çıkar. --}}
@php
    $doc = $page->document();
    $sections = count($doc['toc']);
    $index = $legalLinks->search(fn ($p) => $p->id === $page->id);
    $prev = $index !== false && $index > 0 ? $legalLinks[$index - 1] : null;
    $next = $index !== false ? $legalLinks->get($index + 1) : null;
    $meta = fn (string $key, int $n) => $settings[$key] ? str_replace(':n', (string) $n, $settings[$key]) : null;
@endphp
<x-layouts.store :title="$page->title" :description="$page->meta_description ?: \Illuminate\Support\Str::limit(trim(strip_tags($doc['html'])), 160)">
    <x-info.hero :page="$page" :kicker="$settings['pages_doc_kicker']"
        :facts="[['list', $sections > 1 ? $meta('pages_meta_sections', $sections) : null], ['book', $meta('pages_meta_reading', $doc['minutes'])]]" />

    <div class="container-page grid gap-8 py-10 lg:grid-cols-[17.5rem_minmax(0,1fr)] lg:py-14 print:block print:py-4">
        <aside class="order-last min-w-0 space-y-5 lg:sticky lg:top-36 lg:order-first lg:self-start print:hidden">
            @if ($sections > 1)
                <nav class="info-toc hidden lg:block" aria-label="{{ $settings['pages_toc_title'] }}" data-toc>
                    <div class="flex items-center justify-between gap-3">
                        @if ($settings['pages_toc_title'])<p class="text-xs font-bold tracking-[0.18em] text-muted uppercase">{{ $settings['pages_toc_title'] }}</p>@endif
                        <span class="text-xs font-bold text-primary tabular-nums" data-toc-percent>0%</span>
                    </div>
                    <div class="info-toc-progress" aria-hidden="true"><span data-toc-bar></span></div>
                    <ol class="info-toc-list mt-3 space-y-0.5 text-sm">
                        @foreach ($doc['toc'] as $i => $item)
                            <li><a href="#{{ $item['id'] }}" class="info-toc-link" data-toc-link><span class="info-toc-num">{{ $i + 1 }}</span><span class="min-w-0">{{ preg_replace('/^\d+[.)]\s*/u', '', $item['title']) }}</span></a></li>
                        @endforeach
                    </ol>
                </nav>
            @endif
            @if ($legalLinks->count() > 1)
                <x-info.side-nav :title="$settings['pages_legal_title']" :links="$legalLinks" :current="$page" />
            @endif
            @include('store.pages._help-card')
        </aside>

        <div class="min-w-0">
            <article class="legal-doc print:rounded-none print:border-0 print:p-0 print:shadow-none">
                @if ($sections > 1)
                    {{-- Telefonda içindekiler açılır listede --}}
                    <details class="mb-8 rounded-2xl bg-surface lg:hidden print:hidden">
                        <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 text-sm font-bold [&::-webkit-details-marker]:hidden">
                            <span class="flex items-center gap-2"><x-icon name="list" class="size-4 text-primary" />{{ $settings['pages_toc_title'] }}</span><x-icon name="chevron-down" class="size-4" />
                        </summary>
                        <ol class="space-y-1 px-2 pb-3 text-sm">
                            @foreach ($doc['toc'] as $i => $item)
                                <li><a href="#{{ $item['id'] }}" class="info-toc-link"><span class="info-toc-num">{{ $i + 1 }}</span><span class="min-w-0">{{ preg_replace('/^\d+[.)]\s*/u', '', $item['title']) }}</span></a></li>
                            @endforeach
                        </ol>
                    </details>
                @endif
                <div class="prose-page prose-legal" data-toc-content>{{ $doc['html'] }}</div>
                <x-info.print-note />
            </article>

            @if ($prev || $next)
                <nav class="mt-6 grid gap-4 sm:grid-cols-2 print:hidden" aria-label="{{ $settings['pages_legal_title'] }}">
                    @if ($prev)
                        <a href="{{ $prev->url() }}" class="legal-pager group">
                            <span class="legal-pager-icon"><x-icon name="arrow-right" class="size-4 rotate-180" /></span>
                            <span class="min-w-0"><span class="block text-xs font-semibold text-muted">{{ $settings['pages_prev'] }}</span><span class="block truncate font-display font-extrabold">{{ $prev->title }}</span></span>
                        </a>
                    @else
                        <span class="hidden sm:block"></span>
                    @endif
                    @if ($next)
                        <a href="{{ $next->url() }}" class="legal-pager group sm:flex-row-reverse sm:text-right">
                            <span class="legal-pager-icon"><x-icon name="arrow-right" class="size-4" /></span>
                            <span class="min-w-0"><span class="block text-xs font-semibold text-muted">{{ $settings['pages_next'] }}</span><span class="block truncate font-display font-extrabold">{{ $next->title }}</span></span>
                        </a>
                    @endif
                </nav>
            @endif
        </div>
    </div>
</x-layouts.store>
