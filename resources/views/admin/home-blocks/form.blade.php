@php
    $C = \App\Http\Controllers\Admin\HomeBlockController::class;
    $isNew = ! $block->exists;
    $s = fn ($key, $default = null) => old($key, $block->setting($key, $default));
@endphp
<x-layouts.admin :title="($isNew ? 'Yeni blok · ' : 'Blok · ').$block->typeLabel()">
    <x-slot:actions><a href="{{ route('admin.home-blocks.index') }}" class="btn-ghost btn-sm">Listeye dön</a></x-slot:actions>

    <form method="post" enctype="multipart/form-data" action="{{ $isNew ? route('admin.home-blocks.store') : route('admin.home-blocks.update', $block) }}" class="card max-w-4xl space-y-6 p-6 sm:p-8">
        @csrf
        @unless ($isNew) @method('put') @endunless
        <input type="hidden" name="type" value="{{ $block->type }}">

        @if ($block->type !== 'hero')
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="title" label="Başlık" :value="$block->title" />
                <x-field name="subtitle" label="Alt başlık" :value="$block->subtitle" />
            </div>
        @endif

        @switch($block->type)
            @case('hero')
                <x-field name="heading" label="Büyük başlık" :value="$s('heading')" help="Boşsa sitenin sloganı kullanılır." />
                <x-field name="text" type="textarea" label="Açıklama" :value="$s('text')" />
                <div class="grid gap-5 sm:grid-cols-3">
                    <x-field name="badge" label="Üst etiket" :value="$s('badge')" />
                    <x-field name="button_label" label="Bağlantı metni" :value="$s('button_label')" />
                    <x-field name="button_url" label="Bağlantı adresi" :value="$s('button_url')" placeholder="/fotokopi" />
                </div>
                <p class="field-help">Sağdaki kutular Ayarlar › Genel › Güven şeridi'nden gelir.</p>
                @break

            @case('print_banner')
                <p class="field-help -mt-3">Başlık ve alt başlık bantta görünür.</p>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field name="button_label" label="Düğme metni" :value="$s('button_label')" />
                    <x-field name="button_url" label="Düğme adresi" :value="$s('button_url')" placeholder="/fotokopi" />
                </div>
                @break

            @case('categories')
                <div>
                    <label class="field-label" for="f_category_ids">Gösterilecek kategoriler</label>
                    <select id="f_category_ids" name="category_ids[]" multiple size="12" class="field-input">
                        @foreach ($categories as $c)
                            <option value="{{ $c['id'] }}" @selected(in_array($c['id'], (array) $s('category_ids', [])))>{{ str_repeat('— ', $c['depth']) }}{{ $c['name'] }}</option>
                        @endforeach
                    </select>
                    <p class="field-help">Ctrl (Mac'te Cmd) ile birden çok seçin. Hiçbiri seçilmezse ana kategoriler gösterilir.</p>
                </div>
                <x-field name="limit" type="number" label="En fazla" :value="$s('limit', 12)" min="2" max="24" />
                @break

            @case('publishers')
                <x-field name="limit" type="number" label="En fazla logo" :value="$s('limit', 18)" min="3" max="48"
                         help="Öne çıkan yayınevleri (Yayınevleri › Öne çıkar) sırasıyla gösterilir; hiç yoksa en çok ürünü olanlar." />
                @break

            @case('products')
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="field-label" for="f_source">Ürünler</label>
                        <select id="f_source" name="source" class="field-input">
                            @foreach (\App\Models\HomeBlock::PRODUCT_SOURCES as $k => $l)<option value="{{ $k }}" @selected($s('source', 'newest') === $k)>{{ $l }}</option>@endforeach
                        </select>
                        @error('source')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <x-field name="limit" type="number" label="En fazla" :value="$s('limit', 12)" min="2" max="24" />
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="field-label" for="f_category_id">Kategori <span class="font-normal text-muted">(“Seçilen kategori” için)</span></label>
                        <select id="f_category_id" name="category_id" class="field-input">
                            <option value="">—</option>
                            @foreach ($categories as $c)<option value="{{ $c['id'] }}" @selected((int) $s('category_id') === $c['id'])>{{ str_repeat('— ', $c['depth']) }}{{ $c['name'] }}</option>@endforeach
                        </select>
                        @error('category_id')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="field-label" for="f_publisher_id">Yayınevi <span class="font-normal text-muted">(“Seçilen yayınevi” için)</span></label>
                        <select id="f_publisher_id" name="publisher_id" class="field-input">
                            <option value="">—</option>
                            @foreach ($publishers as $p)<option value="{{ $p->id }}" @selected((int) $s('publisher_id') === $p->id)>{{ $p->name }}</option>@endforeach
                        </select>
                        @error('publisher_id')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <x-field name="product_list" type="textarea" rows="4" label="Ürünler (“El ile seçilen” için)"
                         :value="old('product_list', $manualProducts->map(fn ($p) => $p->code ?: $p->id)->implode(PHP_EOL))"
                         help="Her satıra bir ürün kodu, ISBN veya ürün numarası. Bu sırayla gösterilir." />
                @if ($manualProducts->isNotEmpty())
                    <ul class="-mt-3 text-xs text-muted">@foreach ($manualProducts as $p)<li>• {{ $p->title }}</li>@endforeach</ul>
                @endif
                @break

            @case('slider')
                <p class="field-help -mt-3">Önerilen görsel boyutu 1600×530 piksel (masaüstü), en fazla 4 MB. En fazla {{ $C::MAX_SLIDES }} slayt.</p>
                @php($slides = array_values((array) $block->setting('slides', [])))
                @for ($i = 0; $i < $C::MAX_SLIDES; $i++)
                    @php($sl = $slides[$i] ?? [])
                    <fieldset class="rounded-xl border border-line p-4">
                        <legend class="px-1 text-sm font-semibold">{{ $i + 1 }}. slayt</legend>
                        <div class="grid gap-4 sm:grid-cols-[160px_1fr]">
                            <div x-data="imagePreview" data-current="{{ ! empty($sl['image']) ? \Illuminate\Support\Facades\Storage::disk('public')->url($sl['image']) : '' }}">
                                <div class="flex aspect-[3/1] items-center justify-center overflow-hidden rounded-lg border border-dashed border-line bg-surface">
                                    <img x-show="hasImage" x-bind:src="url" alt="" class="size-full object-cover">
                                    <span x-show="noImage" class="text-xs text-muted">Görsel yok</span>
                                </div>
                                <input type="file" name="slides[{{ $i }}][file]" accept="image/*" x-on:change="pick" class="mt-2 block w-full text-xs file:mr-2 file:rounded-md file:border-0 file:bg-primary-soft file:px-2 file:py-1 file:text-xs file:font-semibold file:text-primary-dark">
                                @if (! empty($sl['image']))<label class="mt-1 flex items-center gap-1.5 text-xs text-muted"><input type="checkbox" name="slides[{{ $i }}][remove]" value="1" class="accent-danger"> Kaldır</label>@endif
                                @error("slides.$i.file")<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-field name="slides[{{ $i }}][title]" label="Başlık" :value="$sl['title'] ?? ''" />
                                <x-field name="slides[{{ $i }}][url]" label="Bağlantı" :value="$sl['url'] ?? ''" placeholder="/kategori/…" />
                                <x-field name="slides[{{ $i }}][text]" label="Metin" :value="$sl['text'] ?? ''" />
                                <x-field name="slides[{{ $i }}][button_label]" label="Düğme metni" :value="$sl['button_label'] ?? ''" />
                            </div>
                        </div>
                    </fieldset>
                @endfor
                @break

            @case('features')
                @php($items = array_values((array) $s('items', [])))
                @for ($i = 0; $i < $C::MAX_FEATURES; $i++)
                    <div class="grid gap-3 sm:grid-cols-[140px_1fr_2fr]">
                        <select name="items[{{ $i }}][icon]" class="field-input" aria-label="Simge">
                            @foreach ($C::ICONS as $icon)<option value="{{ $icon }}" @selected(($items[$i]['icon'] ?? 'check') === $icon)>{{ $icon }}</option>@endforeach
                        </select>
                        <input name="items[{{ $i }}][title]" value="{{ $items[$i]['title'] ?? '' }}" placeholder="Başlık" class="field-input" aria-label="Başlık">
                        <input name="items[{{ $i }}][text]" value="{{ $items[$i]['text'] ?? '' }}" placeholder="Kısa açıklama" class="field-input" aria-label="Açıklama">
                    </div>
                @endfor
                <p class="field-help">Başlığı boş satırlar kaydedilmez.</p>
                @break

            @case('faq')
                @php($items = array_values((array) $s('items', [])))
                @for ($i = 0; $i < $C::MAX_FAQ; $i++)
                    <div class="grid gap-2 rounded-xl border border-line p-3">
                        <input name="items[{{ $i }}][q]" value="{{ $items[$i]['q'] ?? '' }}" placeholder="{{ $i + 1 }}. soru" class="field-input font-semibold" aria-label="Soru">
                        <textarea name="items[{{ $i }}][a]" rows="2" placeholder="Cevap" class="field-input" aria-label="Cevap">{{ $items[$i]['a'] ?? '' }}</textarea>
                    </div>
                @endfor
                @break

            @case('text')
                <x-field name="body" type="textarea" rows="8" label="Metin" :value="$s('body')" />
                @break
        @endswitch

        <div class="flex items-center justify-between gap-4 border-t border-line pt-5">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $block->is_active)) class="accent-primary"> Ana sayfada göster</label>
            <button class="btn-primary">Kaydet</button>
        </div>
    </form>
</x-layouts.admin>
