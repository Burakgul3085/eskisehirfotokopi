<section class="container-page py-8 sm:py-10">
    <div class="relative overflow-hidden rounded-3xl bg-ink px-6 py-10 text-white sm:px-12 sm:py-14">
        <div class="pointer-events-none absolute -top-20 right-10 size-72 rounded-full bg-primary/40 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-24 -left-10 size-72 rounded-full bg-accent/25 blur-3xl"></div>
        <div class="relative grid items-center gap-8 md:grid-cols-[1.4fr_1fr]">
            <div>
                <span class="flex size-12 items-center justify-center rounded-2xl bg-white/10 text-accent"><x-icon name="printer" class="size-6" /></span>
                <h2 class="mt-5 text-2xl font-extrabold sm:text-3xl">{{ $block->title }}</h2>
                @if ($block->subtitle)<p class="mt-3 max-w-xl text-white/75">{{ $block->subtitle }}</p>@endif
            </div>
            @if ($block->setting('button_label') && $block->setting('button_url'))
                <div class="md:text-right">
                    <a href="{{ $block->setting('button_url') }}" class="btn-accent px-6 py-3 text-base"><x-icon name="upload" class="size-5" />{{ $block->setting('button_label') }}</a>
                </div>
            @endif
        </div>
    </div>
</section>
