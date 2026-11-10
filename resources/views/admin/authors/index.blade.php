<x-layouts.admin title="Yazarlar">
    <form method="get" class="mb-5 flex gap-2">
        <input name="q" value="{{ $q }}" placeholder="Yazar ara" class="field-input max-w-xs">
        <button class="btn-ghost">Ara</button>
    </form>
    <p class="mb-4 text-sm text-muted">Adı düzeltip kaydedin. Başka bir yazarla aynı adı yazarsanız iki kayıt birleştirilir.</p>
    <div class="card divide-y divide-line/70">
        @forelse ($authors as $a)
            <form method="post" action="{{ route('admin.authors.update', $a) }}" class="flex items-center gap-3 px-4 py-2.5">
                @csrf @method('put')
                <input name="name" value="{{ $a->name }}" class="field-input max-w-md py-1.5" aria-label="Yazar adı">
                <a href="{{ route('admin.products.index', ['q' => $a->name]) }}" class="text-sm text-muted hover:text-primary">{{ $a->products_count }} ürün</a>
                <button class="btn-ghost btn-sm ml-auto">Kaydet</button>
            </form>
        @empty
            <p class="px-4 py-8 text-center text-muted">Yazar yok.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $authors->links() }}</div>
</x-layouts.admin>
