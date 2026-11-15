<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Http\Request;

/**
 * Ürün formu ve toplu yükleme kontrol ekranındaki "Müşteriye gösterilen bilgiler" bölümünün girdisi
 * (admin/products/_specs): bilgi başına Genel ayar / Göster / Gizle ve ek bilgi satırları.
 */
final class SpecInput
{
    public static function rules(): array
    {
        return [
            'spec_visibility' => ['nullable', 'array'],
            'spec_visibility.*' => ['nullable', 'in:0,1'],
            'extra_specs' => ['nullable', 'array', 'max:40'],
            'extra_specs.*.label' => ['nullable', 'string', 'max:60'],
            'extra_specs.*.value' => ['nullable', 'string', 'max:500'],
            'extra_specs.*.visible' => ['nullable', 'in:0,1'],
        ];
    }

    /** @return array{spec_visibility: ?array<string, bool>, extra_specs: ?list<array{label: string, value: string}>} */
    public static function from(Request $request): array
    {
        $visibility = [];
        foreach ((array) $request->input('spec_visibility', []) as $key => $v) {
            if (isset(Product::SPECS[$key]) && ($v === '0' || $v === '1')) {
                $visibility[$key] = $v === '1';
            }
        }
        $extras = [];
        $seen = [];
        foreach ((array) $request->input('extra_specs', []) as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            $key = Product::extraKey($label);
            if ($label === '' || $value === '' || isset($seen[$key])) {
                continue; // boş satır veya aynı etiketin tekrarı
            }
            $seen[$key] = true;
            $extras[] = ['label' => $label, 'value' => $value];
            $v = $row['visible'] ?? '';
            if ($v === '0' || $v === '1') {
                $visibility[$key] = $v === '1';
            }
        }

        return ['spec_visibility' => $visibility ?: null, 'extra_specs' => $extras ?: null];
    }
}
