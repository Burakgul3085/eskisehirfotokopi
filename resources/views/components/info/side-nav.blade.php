{{-- Yan menü: aynı türdeki sayfalar (yasal metinler ya da yardım), etkin sayfa koyu. --}}
@props(['title', 'links', 'current', 'extra' => []])
<nav class="rounded-3xl border border-line bg-white p-2" aria-label="{{ $title }}">
    @if ($title)<p class="px-3 pt-3 pb-1 text-xs font-bold tracking-[0.18em] text-muted uppercase">{{ $title }}</p>@endif
    @foreach ($links as $link)
        @php($active = $link->id === $current->id)
        <a href="{{ $link->url() }}" @if ($active) aria-current="page" @endif
           class="info-nav-link {{ $active ? 'is-active' : '' }}">
            <x-icon :name="$link->icon()" class="size-4 shrink-0" /><span class="min-w-0 flex-1">{{ $link->title }}</span>
            @unless ($active)<x-icon name="chevron-right" class="size-3.5 shrink-0 opacity-40" />@endunless
        </a>
    @endforeach
    @foreach ($extra as [$label, $url, $icon])
        <a href="{{ $url }}" class="info-nav-link"><x-icon :name="$icon" class="size-4 shrink-0" /><span class="min-w-0 flex-1">{{ $label }}</span><x-icon name="chevron-right" class="size-3.5 shrink-0 opacity-40" /></a>
    @endforeach
</nav>
