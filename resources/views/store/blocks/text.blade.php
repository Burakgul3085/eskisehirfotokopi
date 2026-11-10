<section class="container-page py-8 sm:py-10">
    <div class="mx-auto max-w-3xl">
        @if ($block->title)<h2 class="text-xl font-extrabold sm:text-2xl">{{ $block->title }}</h2>@endif
        @if ($block->subtitle)<p class="mt-1 text-sm text-muted">{{ $block->subtitle }}</p>@endif
        @if ($block->setting('body'))
            <div class="mt-4 text-[15px] leading-relaxed whitespace-pre-line text-ink/85">{{ $block->setting('body') }}</div>
        @endif
    </div>
</section>
