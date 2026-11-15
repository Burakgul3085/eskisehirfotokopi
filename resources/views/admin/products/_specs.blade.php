{{-- Müşteriye gösterilen bilgiler ve ek bilgiler. $product: ?Product. Ürün formunda ve toplu yükleme kontrol ekranında kullanılır. --}}
@php
    $hiddenByDefault = \App\Support\Settings::hiddenSpecs();
    $choiceValue = fn (string $key) => old('spec_visibility.'.$key, ($c = $product?->specChoice($key)) === null ? '' : ($c ? '1' : '0'));
    $extraRows = old('extra_specs', collect($product?->extra_specs ?? [])->map(fn ($r) => $r + [
        'visible' => ($c = $product?->specChoice(\App\Models\Product::extraKey($r['label'] ?? ''))) === null ? '' : ($c ? '1' : '0'),
    ])->all());
    $extraRows = array_values($extraRows);
    for ($i = count($extraRows), $n = count($extraRows) + 2; $i < $n; $i++) {
        $extraRows[] = ['label' => '', 'value' => '', 'visible' => ''];
    }
@endphp
<div class="space-y-5">
    <div>
        <h3 class="font-semibold">Müşteriye gösterilen bilgiler</h3>
        <p class="mt-1 text-sm text-muted">“Genel ayar” seçili bilgi, <a href="{{ route('admin.settings.edit', 'urun-bilgileri') }}" class="text-primary hover:underline">Ayarlar › Ürün bilgileri</a> sayfasındaki seçime uyar. Gizlenen bilgi silinmez, yalnız ürün sayfasında gösterilmez; ISBN ve ürün kodu gizli olsa da aramada bulunur.</p>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
            @foreach (\App\Models\Product::SPECS as $key => $label)
                <label class="flex items-center justify-between gap-3 rounded-xl border border-line px-3 py-2 text-sm">
                    <span>{{ $label }}</span>
                    <select name="spec_visibility[{{ $key }}]" class="field-input w-auto py-1 text-sm">
                        <option value="" @selected($choiceValue($key) === '')>Genel ayar ({{ in_array($key, $hiddenByDefault, true) ? 'gizli' : 'göster' }})</option>
                        <option value="1" @selected($choiceValue($key) === '1')>Göster</option>
                        <option value="0" @selected($choiceValue($key) === '0')>Gizle</option>
                    </select>
                </label>
            @endforeach
        </div>
    </div>
    <div>
        <h3 class="font-semibold">Ek bilgiler</h3>
        <p class="mt-1 text-sm text-muted">Çevirmen, dil, kapak türü gibi standart dışı bilgiler. Toplu yüklemede görseldeki tablodan okunur; görselde olmayan bilgiyi buraya kendiniz ekleyebilirsiniz. Satırı silmek için değerini boşaltın.</p>
        <div class="mt-3 space-y-2">
            @foreach ($extraRows as $i => $row)
                <div class="grid gap-2 sm:grid-cols-[11rem_1fr_auto]">
                    <input name="extra_specs[{{ $i }}][label]" value="{{ $row['label'] ?? '' }}" placeholder="Bilgi adı (ör. Çevirmen)" class="field-input py-2 text-sm" maxlength="60">
                    <input name="extra_specs[{{ $i }}][value]" value="{{ $row['value'] ?? '' }}" placeholder="Değer" class="field-input py-2 text-sm" maxlength="500">
                    <select name="extra_specs[{{ $i }}][visible]" class="field-input w-auto py-2 text-sm" aria-label="Gösterim">
                        <option value="" @selected(($row['visible'] ?? '') === '')>Ek bilgiler ayarı</option>
                        <option value="1" @selected(($row['visible'] ?? '') === '1')>Göster</option>
                        <option value="0" @selected(($row['visible'] ?? '') === '0')>Gizle</option>
                    </select>
                </div>
            @endforeach
        </div>
    </div>
</div>
