<section class="container-page py-8 sm:py-10">
    @if ($block->title)<h2 class="mb-5 text-xl font-extrabold sm:text-2xl">{{ $block->title }}</h2>@endif
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($block->setting('items', []) as $item)
            @continue(empty($item['title']))
            <div class="flex items-start gap-4 rounded-2xl border border-line/70 bg-white p-5">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary"><x-icon :name="$item['icon'] ?? 'check'" class="size-5" /></span>
                <div>
                    <p class="font-display font-bold">{{ $item['title'] }}</p>
                    @if (! empty($item['text']))<p class="mt-1 text-sm text-muted">{{ $item['text'] }}</p>@endif
                </div>
            </div>
        @endforeach
    </div>
</section>
