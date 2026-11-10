@props(['title', 'subtitle' => null])
<x-layouts.store :title="$title">
    <div class="container-page flex justify-center py-12 sm:py-16">
        <div class="w-full max-w-md">
            <div class="card p-7 sm:p-9">
                <h1 class="text-2xl font-extrabold">{{ $title }}</h1>
                @if ($subtitle)<p class="mt-1.5 text-sm text-muted">{{ $subtitle }}</p>@endif
                <x-flash class="mt-5" />
                <div class="mt-6">{{ $slot }}</div>
            </div>
            @isset($below)<div class="mt-5 text-center text-sm text-muted">{{ $below }}</div>@endisset
        </div>
    </div>
</x-layouts.store>
