{{-- Yasal metin (Mesafeli Satış, KVKK, Üyelik Koşulları, Çerez Politikası…): içindekiler, yasal metinler menüsü,
     yardım kartı; metin beyaz kartta. Yazdırınca yalnız metin çıkar (PDF olarak kaydedilebilir). --}}
@php($doc = $page->document())
<x-layouts.store :title="$page->title" :description="$page->meta_description ?: \Illuminate\Support\Str::limit(trim(strip_tags($doc['html'])), 160)">
    <x-info.hero :page="$page" />

    <div class="container-page grid gap-8 py-10 lg:grid-cols-[17rem_minmax(0,1fr)] print:block print:py-4">
        <aside class="order-last min-w-0 space-y-5 lg:sticky lg:top-32 lg:order-first lg:self-start print:hidden">
            @if (count($doc['toc']) > 1)
                <nav class="hidden rounded-3xl border border-line bg-white p-5 lg:block" aria-label="{{ $settings['pages_toc_title'] }}">
                    @if ($settings['pages_toc_title'])<p class="text-xs font-bold tracking-[0.18em] text-muted uppercase">{{ $settings['pages_toc_title'] }}</p>@endif
                    <ol class="mt-3 space-y-1 text-sm">
                        @foreach ($doc['toc'] as $i => $item)
                            <li><a href="#{{ $item['id'] }}" class="info-toc-link"><span class="info-toc-num">{{ $i + 1 }}</span><span class="min-w-0">{{ preg_replace('/^\d+[.)]\s*/u', '', $item['title']) }}</span></a></li>
                        @endforeach
                    </ol>
                </nav>
            @endif
            @if ($legalLinks->count() > 1)
                <x-info.side-nav :title="$settings['pages_legal_title']" :links="$legalLinks" :current="$page" />
            @endif
            @include('store.pages._help-card')
        </aside>

        <article class="min-w-0 rounded-3xl border border-line bg-white p-6 sm:p-10 print:rounded-none print:border-0 print:p-0">
            @if (count($doc['toc']) > 1)
                {{-- Telefonda içindekiler açılır listede --}}
                <details class="mb-6 rounded-2xl bg-surface lg:hidden print:hidden">
                    <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 text-sm font-bold [&::-webkit-details-marker]:hidden">{{ $settings['pages_toc_title'] }}<x-icon name="chevron-down" class="size-4" /></summary>
                    <ol class="space-y-1 px-2 pb-3 text-sm">
                        @foreach ($doc['toc'] as $i => $item)
                            <li><a href="#{{ $item['id'] }}" class="info-toc-link"><span class="info-toc-num">{{ $i + 1 }}</span><span class="min-w-0">{{ preg_replace('/^\d+[.)]\s*/u', '', $item['title']) }}</span></a></li>
                        @endforeach
                    </ol>
                </details>
            @endif
            <div class="prose-page prose-legal">{{ $doc['html'] }}</div>
            @if ($settings['pages_print'])
                <div class="mt-10 flex flex-wrap items-center gap-3 border-t border-line pt-6 print:hidden">
                    <button type="button" x-data="printPage" x-on:click="print" class="btn-ghost"><x-icon name="printer" class="size-4" />{{ $settings['pages_print'] }}</button>
                </div>
            @endif
        </article>
    </div>
</x-layouts.store>
