{{-- Mobil menüde iç içe kategori (details/summary: JavaScript gerektirmez). En fazla 3 düzey gösterilir. --}}
@if ($node['children'] && $level < 2)
    <details class="group">
        <summary class="flex cursor-pointer list-none items-center justify-between rounded-lg px-3 py-2.5 font-medium hover:bg-surface [&::-webkit-details-marker]:hidden">
            {{ $node['name'] }}<x-icon name="chevron-down" class="size-4 text-muted transition group-open:rotate-180" />
        </summary>
        <div class="ml-3 border-l border-line pl-2">
            <a href="{{ route('category.show', $node['slug']) }}" class="block rounded-lg px-3 py-2 text-sm font-semibold text-primary hover:bg-surface">Tümü</a>
            @foreach ($node['children'] as $child)
                @include('store.partials.mobile-category', ['node' => $child, 'level' => $level + 1])
            @endforeach
        </div>
    </details>
@else
    <a href="{{ route('category.show', $node['slug']) }}" class="block rounded-lg px-3 py-2.5 {{ $level ? 'text-sm' : 'font-medium' }} hover:bg-surface">{{ $node['name'] }}</a>
@endif
