<x-layouts.admin title="Ayarlar · Görünüm">
    @include('admin.settings._layout')
    <form method="post" action="{{ route('admin.settings.update', $group) }}" enctype="multipart/form-data" class="card max-w-3xl space-y-6 p-6 sm:p-8">
        @csrf @method('put')

        <div class="grid gap-6 sm:grid-cols-2">
            @foreach (['logo' => ['Logo', 'logo_path', 'PNG, SVG veya WebP; yatay logo önerilir. En fazla 2 MB.'], 'favicon' => ['Site simgesi (favicon)', 'favicon_path', 'Kare PNG, ICO veya SVG. En fazla 512 KB.']] as $field => [$label, $key, $help])
                <div x-data="imagePreview" data-current="{{ \App\Support\Settings::fileUrl($key) }}">
                    <span class="field-label">{{ $label }}</span>
                    <div class="flex h-28 items-center justify-center rounded-xl border border-dashed border-line bg-surface p-3">
                        <img x-show="hasImage" x-bind:src="url" alt="" class="max-h-full max-w-full object-contain">
                        <span x-show="noImage" class="text-xs text-muted">Görsel yok</span>
                    </div>
                    <input type="file" name="{{ $field }}" accept="image/*" x-on:change="pick" class="mt-3 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-primary-soft file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-primary-dark">
                    @if ($s[$key])
                        <label class="mt-2 flex items-center gap-2 text-xs text-muted"><input type="checkbox" name="remove_{{ $field }}" value="1" class="accent-danger"> Mevcut görseli kaldır</label>
                    @endif
                    @error($field)<p class="field-error">{{ $message }}</p>@enderror
                    <p class="field-help">{{ $help }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-6 border-t border-line pt-6 sm:grid-cols-2">
            @foreach (['color_primary' => ['Ana renk', 'Düğmeler, bağlantılar ve vurgu alanları.'], 'color_accent' => ['Vurgu rengi', 'Rozetler, öne çıkan düğmeler.']] as $key => [$label, $help])
                <div x-data="colorField" data-value="{{ old($key, $s[$key]) }}">
                    <label class="field-label" for="f_{{ $key }}">{{ $label }}</label>
                    <div class="flex items-center gap-3">
                        <input type="color" x-bind:value="value" x-on:input="sync" class="h-11 w-14 cursor-pointer rounded-lg border border-line bg-white p-1" aria-label="{{ $label }} seç">
                        <input id="f_{{ $key }}" name="{{ $key }}" x-model="value" class="field-input font-mono" maxlength="7">
                    </div>
                    @error($key)<p class="field-error">{{ $message }}</p>@enderror
                    <p class="field-help">{{ $help }}</p>
                </div>
            @endforeach
        </div>

        <div class="flex justify-end"><button class="btn-primary">Kaydet</button></div>
    </form>
</x-layouts.admin>
