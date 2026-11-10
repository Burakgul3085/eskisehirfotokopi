<x-layouts.admin :title="$publisher->exists ? $publisher->name : 'Yayınevi ekle'">
    <div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
        <form method="post" enctype="multipart/form-data" action="{{ $publisher->exists ? route('admin.publishers.update', $publisher) : route('admin.publishers.store') }}" class="card space-y-5 p-6 sm:p-8">
            @csrf
            @if ($publisher->exists) @method('put') @endif
            <x-field name="name" label="Ad" :value="$publisher->name" required />
            <div x-data="imagePreview" data-current="{{ $publisher->logoUrl() }}">
                <span class="field-label">Logo</span>
                <div class="flex h-28 w-56 items-center justify-center rounded-xl border border-dashed border-line bg-white p-3">
                    <img x-show="hasImage" x-bind:src="url" alt="" class="max-h-full max-w-full object-contain">
                    <span x-show="noImage" class="text-xs text-muted">Logo yok</span>
                </div>
                <input type="file" name="logo" accept="image/*" x-on:change="pick" class="mt-3 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-primary-soft file:px-3 file:py-1.5 file:font-semibold file:text-primary-dark">
                @if ($publisher->logo_path)<label class="mt-2 flex items-center gap-2 text-xs text-muted"><input type="checkbox" name="remove_logo" value="1" class="accent-danger"> Logoyu kaldır</label>@endif
                @error('logo')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <x-field name="description" type="textarea" rows="4" label="Açıklama" :value="$publisher->description" />
            <x-field name="aliases" type="textarea" rows="3" label="Takma adlar" :value="$publisher->exists ? $publisher->aliases->pluck('match_key')->implode(PHP_EOL) : ''"
                help="Toplu yüklemede bu adlarla gelen klasörler de bu yayınevine eşleşir. Her satıra bir ad (ör. Hız ve Renk)." />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="position" type="number" label="Sıra" :value="$publisher->position" help="Küçük sayı önde gösterilir." />
            </div>
            <div class="flex flex-wrap gap-6">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $publisher->is_active)) class="size-4 accent-primary"> Sitede görünsün</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $publisher->is_featured)) class="size-4 accent-primary"> Ana sayfada öne çıkar</label>
            </div>
            <details class="rounded-xl border border-line p-4">
                <summary class="cursor-pointer text-sm font-semibold">SEO</summary>
                <div class="mt-4 space-y-4">
                    <x-field name="meta_title" label="Sayfa başlığı" :value="$publisher->meta_title" maxlength="70" />
                    <x-field name="meta_description" type="textarea" label="Açıklama" :value="$publisher->meta_description" maxlength="170" />
                </div>
            </details>
            <div class="flex justify-end gap-2">
                <a href="{{ route('admin.publishers.index') }}" class="btn-ghost">Vazgeç</a>
                <button class="btn-primary">Kaydet</button>
            </div>
        </form>

        @if ($publisher->exists)
            <aside class="space-y-4">
                <div class="card p-5">
                    <p class="text-sm text-muted">Ürün sayısı</p>
                    <p class="font-display text-2xl font-bold">{{ $publisher->products()->count() }}</p>
                    <a href="{{ route('admin.products.index', ['yayinevi' => $publisher->id]) }}" class="mt-2 inline-block text-sm font-semibold text-primary hover:underline">Ürünleri gör →</a>
                </div>
                <form method="post" action="{{ route('admin.publishers.merge', $publisher) }}" class="card space-y-3 p-5" data-confirm="Bu yayınevi seçilen yayınevine birleştirilecek ve silinecek. Emin misiniz?">
                    @csrf
                    <p class="font-semibold">Başka yayınevine birleştir</p>
                    <p class="text-xs text-muted">Aynı yayınevi iki farklı adla oluştuysa kullanın. Ürünler taşınır, bu ad takma ad olarak kalır.</p>
                    <select name="target_id" class="field-input" required>
                        <option value="">Hedef yayınevi seçin</option>
                        @foreach ($others as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach
                    </select>
                    @error('target_id')<p class="field-error">{{ $message }}</p>@enderror
                    <button class="btn-ghost w-full">Birleştir</button>
                </form>
                <form method="post" action="{{ route('admin.publishers.destroy', $publisher) }}" data-confirm="Yayınevi silinsin mi?">
                    @csrf @method('delete')
                    <button class="btn-ghost w-full text-danger"><x-icon name="trash" class="size-4" />Yayınevini sil</button>
                </form>
            </aside>
        @endif
    </div>
</x-layouts.admin>
