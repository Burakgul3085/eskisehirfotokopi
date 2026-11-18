{{-- Yıldız gösterimi (salt okunur): 0–5 arası değer, kesirli değerde son yıldız kısmen dolar. --}}
@props(['value' => 0, 'size' => 'size-4'])
@php
    $value = max(0, min(5, (float) $value));
    $label = '5 üzerinden '.rtrim(rtrim(number_format($value, 1, ',', ''), '0'), ',');
    $star = 'M12 2.5l2.94 5.96 6.58.96-4.76 4.64 1.12 6.55L12 17.52l-5.88 3.09 1.12-6.55L2.48 9.42l6.58-.96L12 2.5z';
@endphp
<span {{ $attributes->merge(['class' => 'stars']) }} role="img" aria-label="{{ $label }}">
    <span class="stars-row stars-base" aria-hidden="true">@for ($i = 0; $i < 5; $i++)<svg viewBox="0 0 24 24" class="{{ $size }}"><path d="{{ $star }}" /></svg>@endfor</span>
    <span class="stars-row stars-fill" style="width: {{ $value * 20 }}%" aria-hidden="true">@for ($i = 0; $i < 5; $i++)<svg viewBox="0 0 24 24" class="{{ $size }} shrink-0"><path d="{{ $star }}" /></svg>@endfor</span>
</span>
