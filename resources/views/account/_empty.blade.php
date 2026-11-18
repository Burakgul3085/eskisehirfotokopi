{{-- Hesabım boş durum kartı: @include('account._empty', ['icon' => 'heart', 'title' => '…', 'text' => '…', 'cta' => ['Etiket', url]]) --}}
<div class="card account-empty px-6 py-14 text-center">
    <span class="account-empty-icon mx-auto flex size-16 items-center justify-center rounded-2xl"><x-icon :name="$icon" class="size-7" /></span>
    <p class="mt-5 font-display text-lg font-extrabold">{{ $title }}</p>
    @if (! empty($text))<p class="mx-auto mt-1.5 max-w-md text-sm text-muted">{{ $text }}</p>@endif
    @if (! empty($cta))<a href="{{ $cta[1] }}" class="btn-primary mt-6">{{ $cta[0] }}<x-icon name="arrow-right" class="size-4" /></a>@endif
</div>
