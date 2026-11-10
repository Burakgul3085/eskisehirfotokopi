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


    <div x-data="bulkSelect">
    @if ($publishers->count())
        <form method="post" action="{{ route('admin.publishers.bulk-destroy', request()->only('q', 'filtre')) }}" id="publisher-bulk"
              data-confirm="Seçilen yayınevleri silinsin mi? Ürünü olanlar silinmez." class="card mb-3 flex flex-wrap items-center gap-3 px-4 py-2.5">
            @csrf
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-on:change="toggleAll" class="size-4 accent-primary" aria-label="Tümünü seç"> Tümünü seç</label>
            <span class="text-sm text-muted"><span x-text="count">0</span> seçili</span>
            <button class="btn-danger btn-sm ml-auto"><x-icon name="trash" class="size-4" />Seçilenleri sil</button>
            @error('ids')<span class="field-error">{{ $message }}</span>@enderror
        </form>
    @endif
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
        @forelse ($publishers as $p)
            <div class="card flex items-center gap-3 p-3 transition hover:shadow-lift">
            <input type="checkbox" name="ids[]" value="{{ $p->id }}" form="publisher-bulk" x-on:change="recount" class="size-4 shrink-0 accent-primary" aria-label="{{ $p->name }} seç">
            <a href="{{ route('admin.publishers.edit', $p) }}" class="flex min-w-0 flex-1 items-center gap-3">
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
            </div>
        @empty
            <p class="text-muted">Yayınevi bulunamadı.</p>
        @endforelse
    </div>
    </div>
    <div class="mt-6">{{ $publishers->links() }}</div>
</x-layouts.admin>
