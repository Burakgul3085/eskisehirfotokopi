<x-layouts.admin title="Yayınevleri">
    <x-slot:actions><a href="{{ route('admin.publishers.create') }}" class="btn-primary btn-sm"><x-icon name="plus" class="size-4" />Yayınevi ekle</a></x-slot:actions>

    <form method="get" class="mb-5 flex flex-wrap gap-2">
        <input name="q" value="{{ $q }}" placeholder="Yayınevi ara" class="field-input max-w-xs">
        <select name="filtre" class="field-input max-w-[12rem]" data-autosubmit>
            <option value="">Tümü</option>
            <option value="logosuz" @selected(request('filtre') === 'logosuz')>Logosu olmayanlar</option>
            <option value="one-cikan" @selected(request('filtre') === 'one-cikan')>Öne çıkanlar</option>
        </select>
        <button class="btn-ghost">Ara</button>
    </form>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
        @forelse ($publishers as $p)
            <a href="{{ route('admin.publishers.edit', $p) }}" class="card flex items-center gap-3 p-3 transition hover:shadow-lift">
                <span class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-line bg-white">
                    @if ($p->logoUrl())<img src="{{ $p->logoUrl() }}" alt="" class="max-h-full max-w-full object-contain p-1" loading="lazy">
                    @else<span class="font-display text-sm font-bold text-muted">{{ $p->initials() }}</span>@endif
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold">{{ $p->name }}</span>
                    <span class="text-xs text-muted">{{ $p->products_count }} ürün</span>
                    @if ($p->is_featured)<span class="badge ml-1 bg-accent-soft text-accent-ink">Öne çıkan</span>@endif
                    @if (! $p->is_active)<span class="badge ml-1 bg-slate-100 text-muted">Gizli</span>@endif
                </span>
            </a>
        @empty
            <p class="text-muted">Yayınevi bulunamadı.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $publishers->links() }}</div>
</x-layouts.admin>
