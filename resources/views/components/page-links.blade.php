@props(['keys'])
{{-- Sistem sayfalarına (sözleşme, KVKK) bağlantılar; yeni sekmede açılır. Gizli sayfa listelenmez. --}}
@php($links = array_filter(array_map(fn ($k) => \App\Models\Page::linkFor($k), $keys)))
@if ($links)
    <p {{ $attributes->merge(['class' => 'flex flex-wrap gap-x-3 gap-y-1 text-xs']) }}>
        @foreach ($links as $link)
            <a href="{{ $link['url'] }}" target="_blank" class="font-semibold text-primary hover:underline">{{ $link['title'] }}</a>
        @endforeach
    </p>
@endif
