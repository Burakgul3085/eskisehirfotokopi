<x-layouts.admin :title="$product->exists ? 'Ürün düzenle' : 'Ürün ekle'">
    @if ($product->exists)
        <x-slot:actions>
            @if ($product->status === 'active')<a href="{{ url('/kitap/'.$product->slug) }}" target="_blank" class="btn-ghost btn-sm"><x-icon name="external" class="size-4" />Sitede gör</a>@endif
        </x-slot:actions>
    @endif
    @php($money = fn ($v) => $v !== null ? number_format((float) $v, 2, ',', '') : '')

    <form method="post" enctype="multipart/form-data" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}" class="grid gap-6 xl:grid-cols-[1fr_22rem]">
        @csrf
        @if ($product->exists) @method('put') @endif

        <div class="space-y-6">
            <section class="card space-y-5 p-6 sm:p-8">
                <x-field name="title" label="Kitap adı" :value="$product->title" required />
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field name="author" label="Yazar" :value="$product->author?->name" help="Yeni ad yazılırsa yazar oluşturulur." />
                    <div>
                        <label class="field-label" for="f_publisher_id">Yayınevi</label>
                        <select id="f_publisher_id" name="publisher_id" class="field-input">
                            <option value="">—</option>
                            @foreach ($publishers as $p)<option value="{{ $p->id }}" @selected((int) old('publisher_id', $product->publisher_id) === $p->id)>{{ $p->name }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="field-label" for="f_category_id">Kategori</label>
                    <select id="f_category_id" name="category_id" class="field-input">
                        <option value="">—</option>
                        @foreach ($categories as $c)<option value="{{ $c['id'] }}" @selected((int) old('category_id', $product->category_id) === $c['id'])>{{ str_repeat('— ', $c['depth']) }}{{ $c['name'] }}</option>@endforeach
                    </select>
                </div>
                <div class="grid gap-5 sm:grid-cols-4">
                    <x-field name="isbn" label="ISBN" :value="$product->isbn" class="sm:col-span-2" />
                    <x-field name="code" label="Ürün kodu" :value="$product->code" />
                    <x-field name="page_count" type="number" label="Sayfa" :value="$product->page_count" />
                </div>
                <x-field name="print_year" type="number" label="Baskı yılı" :value="$product->print_year" class="max-w-[10rem]" />
                <x-field name="description" type="textarea" rows="6" label="Açıklama" :value="$product->description" />
            </section>

            <section class="card p-6 sm:p-8">
                <h2 class="font-bold">Fiyatlar</h2>
                <p class="mt-1 text-sm text-muted">Fiyat elle girilmez: <strong>Sayfa</strong> alanındaki PDF sayfa sayısından, fotokopi ayarlarındaki yaprak fiyat kademeleriyle otomatik hesaplanır (1'e 1: sayfa ÷ 2 yaprak, 2'ye 2: sayfa ÷ 4 yaprak). Fotokopi fiyatlarını değiştirince tüm ürünler güncellenir.</p>
                @if ($product->page_count)
                    <dl class="mt-5 grid gap-3 sm:grid-cols-2">
                        @foreach (\App\Models\Product::PRINT_TYPES as $t => $label)
                            <div class="flex items-center justify-between rounded-xl border border-line bg-surface px-4 py-3">
                                <dt class="text-sm text-muted">{{ $label }}</dt>
                                <dd class="font-display font-bold">{{ $product->price($t) !== null ? \App\Support\Money::format($product->price($t)) : '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @else
                    <p class="mt-4 rounded-xl bg-warning-soft px-4 py-3 text-sm text-warning">Sayfa sayısı girilmedi; ürünün fiyatı yok ve yayına alınamaz.{{ $product->price_bw !== null ? ' (Eski elle girilmiş fiyat: '.\App\Support\Money::format($product->price_bw).')' : '' }}</p>
                @endif
                <p class="mt-5 text-sm text-muted">Eski fiyat girilirse 1'e 1 fiyatının üstü çizili gösterilir ve indirim oranı hesaplanır.</p>
                <div class="mt-3 grid gap-5 sm:grid-cols-2">
                    <x-field name="compare_bw" label="Siyah-beyaz eski fiyat" :value="$money($product->compare_bw)" inputmode="decimal" />
                    <x-field name="compare_color" label="Renkli eski fiyat" :value="$money($product->compare_color)" inputmode="decimal" />
                </div>
            </section>

            <details class="card p-6">
                <summary class="cursor-pointer font-semibold">SEO</summary>
                <div class="mt-4 space-y-4">
                    <x-field name="meta_title" label="Sayfa başlığı" :value="$product->meta_title" maxlength="70" help="Boşsa kitap adı kullanılır." />
                    <x-field name="meta_description" type="textarea" label="Açıklama" :value="$product->meta_description" maxlength="170" />
                </div>
            </details>
        </div>

        <aside class="space-y-6">
            <section class="card space-y-4 p-5">
                <div>
                    <label class="field-label" for="f_status">Durum</label>
                    <select id="f_status" name="status" class="field-input">
                        @foreach (\App\Models\Product::STATUSES as $k => $l)<option value="{{ $k }}" @selected(old('status', $product->status) === $k)>{{ $l }}</option>@endforeach
                    </select>
                    @error('status')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured)) class="size-4 accent-primary"> Öne çıkan ürün</label>
                <button class="btn-primary w-full">Kaydet</button>
            </section>

            <section class="card p-5" x-data="imagePreview" data-current="{{ $product->imageUrl('md') }}">
                <span class="field-label">Mockup görseli</span>
                <div class="flex aspect-[3/4] items-center justify-center overflow-hidden rounded-xl border border-dashed border-line bg-surface">
                    <img x-show="hasImage" x-bind:src="url" alt="" class="max-h-full max-w-full object-contain">
                    <span x-show="noImage" class="text-xs text-muted">Görsel yok</span>
                </div>
                <input type="file" name="image" accept="image/*" x-on:change="pick" class="mt-3 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-primary-soft file:px-3 file:py-1.5 file:font-semibold file:text-primary-dark">
                @if ($product->image_key)<label class="mt-2 flex items-center gap-2 text-xs text-muted"><input type="checkbox" name="remove_image" value="1" class="accent-danger"> Görseli kaldır</label>@endif
                @error('image')<p class="field-error">{{ $message }}</p>@enderror
                <p class="field-help">En fazla 15 MB. Otomatik olarak WebP'ye çevrilir ve 3 boyutta saklanır.</p>
            </section>

            @if ($product->exists)
                <section class="card space-y-1 p-5 text-sm text-muted">
                    <p>Satış: <span class="font-semibold text-ink">{{ $product->sales_count }}</span> · Görüntülenme: <span class="font-semibold text-ink">{{ $product->view_count }}</span></p>
                    <p>Eklenme: {{ $product->created_at->format('d.m.Y H:i') }}</p>
                </section>
            @endif
        </aside>
    </form>

    @if ($product->exists)
        <form method="post" action="{{ route('admin.products.destroy', $product) }}" class="mt-6" data-confirm="Ürün kalıcı olarak silinsin mi?">
            @csrf @method('delete')
            <button class="btn-ghost text-danger"><x-icon name="trash" class="size-4" />Ürünü sil</button>
        </form>
    @endif
</x-layouts.admin>
