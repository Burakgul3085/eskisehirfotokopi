@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Sayfalar" class="flex flex-wrap items-center justify-center gap-1.5">
        @if ($paginator->onFirstPage())
            <span class="btn-ghost btn-sm opacity-50">Önceki</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn-ghost btn-sm">Önceki</a>
        @endif
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-2 text-sm text-muted">…</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page" class="btn btn-sm bg-primary text-white">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="btn-ghost btn-sm">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn-ghost btn-sm">Sonraki</a>
        @else
            <span class="btn-ghost btn-sm opacity-50">Sonraki</span>
        @endif
    </nav>
@endif
