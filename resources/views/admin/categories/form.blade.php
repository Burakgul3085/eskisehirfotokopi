<x-layouts.admin :title="$category->exists ? 'Kategori: '.$category->name : 'Kategori ekle'">
    <form method="post" enctype="multipart/form-data" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="card max-w-3xl space-y-5 p-6 sm:p-8">
        @csrf
        @if ($category->exists) @method('put') @endif
        <x-field name="name" label="Ad" :value="$category->name" required />
        <div>
            <label class="field-label" for="f_parent_id">Üst kategori</label>
            <select id="f_parent_id" name="parent_id" class="field-input">
                <option value="">— Ana kategori —</option>
                @foreach ($parents as $p)
                    <option value="{{ $p['id'] }}" @selected((int) old('parent_id', $category->parent_id) === $p['id'])>{{ str_repeat('— ', $p['depth']) }}{{ $p['name'] }}</option>
                @endforeach
            </select>
            @error('parent_id')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <x-field name="description" type="textarea" rows="4" label="Açıklama" :value="$category->description" help="Kategori sayfasının üstünde görünür (isteğe bağlı)." />
        <div>
            <span class="field-label">Görsel (ana sayfa kategori kutuları için)</span>
            @if ($category->image_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($category->image_path) }}" alt="" class="mb-2 h-20 rounded-lg border border-line object-contain">
                <label class="mb-2 flex items-center gap-2 text-xs text-muted"><input type="checkbox" name="remove_image" value="1" class="accent-danger"> Görseli kaldır</label>
            @endif
            <input type="file" name="image" accept="image/*" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-primary-soft file:px-3 file:py-1.5 file:font-semibold file:text-primary-dark">
            @error('image')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="flex flex-wrap gap-6">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active)) class="size-4 accent-primary"> Sitede görünsün</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="show_in_menu" value="1" @checked(old('show_in_menu', $category->show_in_menu)) class="size-4 accent-primary"> Kategori menüsünde görünsün</label>
        </div>
        <details class="rounded-xl border border-line p-4">
            <summary class="cursor-pointer text-sm font-semibold">SEO</summary>
            <div class="mt-4 space-y-4">
                <x-field name="meta_title" label="Sayfa başlığı" :value="$category->meta_title" maxlength="70" />
                <x-field name="meta_description" type="textarea" label="Açıklama" :value="$category->meta_description" maxlength="170" />
            </div>
        </details>
        <div class="flex justify-end gap-2">
            <a href="{{ route('admin.categories.index') }}" class="btn-ghost">Vazgeç</a>
            <button class="btn-primary">Kaydet</button>
        </div>
    </form>
</x-layouts.admin>
