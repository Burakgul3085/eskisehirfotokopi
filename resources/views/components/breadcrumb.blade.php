@props(['items' => []])
{{-- items: [[ad, url|null], …]; ilk öğe her zaman ana sayfa. schema.org BreadcrumbList ile. --}}
@php($all = array_merge([['Ana sayfa', route('home')]], $items))
<nav aria-label="Konum" {{ $attributes->merge(['class' => 'text-xs text-muted sm:text-sm']) }}>
    <ol class="flex flex-wrap items-center gap-x-1.5 gap-y-1">
        @foreach ($all as $i => [$label, $url])
            <li class="flex items-center gap-1.5">
                @if ($i > 0)<x-icon name="chevron-right" class="size-3.5 text-slate-300" />@endif
                @if ($url && $i < count($all) - 1)
                    <a href="{{ $url }}" class="hover:text-primary">{{ $label }}</a>
                @else
                    <span class="font-medium text-ink" aria-current="page">{{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => collect($all)->values()->map(fn ($c, $i) => array_filter(['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1]]))->all()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
