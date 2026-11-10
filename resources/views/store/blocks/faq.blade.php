@php($items = collect($block->setting('items', []))->filter(fn ($i) => ! empty($i['q']) && ! empty($i['a']))->values())
<section class="container-page py-8 sm:py-12">
    <div class="mx-auto max-w-3xl">
        @if ($block->title)<h2 class="mb-6 text-center text-xl font-extrabold sm:text-2xl">{{ $block->title }}</h2>@endif
        <div class="divide-y divide-line overflow-hidden rounded-2xl border border-line/70 bg-white">
            @foreach ($items as $item)
                <details class="group">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-semibold hover:bg-surface/60 [&::-webkit-details-marker]:hidden">
                        {{ $item['q'] }}<x-icon name="chevron-down" class="size-5 shrink-0 text-muted transition group-open:rotate-180" />
                    </summary>
                    <p class="px-5 pb-5 text-[15px] leading-relaxed whitespace-pre-line text-muted">{{ $item['a'] }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items->map(fn ($i) => ['@type' => 'Question', 'name' => $i['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $i['a']]])->all()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
