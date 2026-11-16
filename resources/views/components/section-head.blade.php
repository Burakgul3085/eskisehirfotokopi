@props(['title', 'subtitle' => null])
{{-- Ana sayfa bölüm başlığı: renk geçişli dikey çizgi, başlık, alt metin; sağda isteğe bağlı bağlantı/düğmeler ($slot). --}}
<div {{ $attributes->merge(['class' => 'mb-6 flex items-end justify-between gap-4']) }}>
    <div class="flex min-w-0 items-stretch gap-3.5">
        <span class="w-1.5 shrink-0 rounded-full bg-gradient-to-b from-primary to-accent" aria-hidden="true"></span>
        <div class="min-w-0 py-0.5">
            <h2 class="font-display text-2xl leading-tight font-extrabold tracking-tight text-ink sm:text-[1.75rem]">{{ $title }}</h2>
            @if ($subtitle)<p class="mt-1 text-sm text-muted sm:text-[15px]">{{ $subtitle }}</p>@endif
        </div>
    </div>
    @if ($slot->isNotEmpty())<div class="flex shrink-0 items-center gap-2">{{ $slot }}</div>@endif
</div>
