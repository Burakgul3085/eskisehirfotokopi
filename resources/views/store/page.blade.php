<x-layouts.store :title="$page->title" :description="$page->meta_description ?: \Illuminate\Support\Str::limit(strip_tags($page->html()), 160)">
    <div class="container-page max-w-3xl py-8 sm:py-12">
        <x-breadcrumb :items="[[$page->title, null]]" />
        <h1 class="mt-4 text-3xl font-extrabold">{{ $page->title }}</h1>
        @unless ($page->is_active)<p class="mt-3 rounded-xl bg-accent-soft px-4 py-2 text-sm text-accent-ink">Bu sayfa gizli; yalnız yöneticiler görüyor.</p>@endunless
        <article class="prose-page card mt-6 p-6 sm:p-8">{{ $page->html() }}</article>
        <p class="mt-4 text-xs text-muted">Son güncelleme: {{ $page->updated_at->format('d.m.Y') }}</p>
    </div>
</x-layouts.store>
