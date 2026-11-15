<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Ürün formu ve toplu yükleme kontrol ekranındaki sınıflandırma girdisi (admin/products/_classification):
 * kategori (kısa listeden), kitap türü, ders ve konu etiketleri (virgülle ayrılmış).
 */
final class ClassificationInput
{
    public static function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'book_type' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:60'],
            'topics' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array{category_id: ?int, book_type: ?string, subject: ?string, topics: ?list<string>} */
    public static function from(Request $request): array
    {
        $clean = fn ($v) => ($v = trim(preg_replace('/\s+/u', ' ', (string) $v))) !== '' ? $v : null;

        return [
            'category_id' => $request->filled('category_id') ? (int) $request->input('category_id') : null,
            'book_type' => $clean($request->input('book_type')),
            'subject' => $clean($request->input('subject')),
            'topics' => self::topics($request->input('topics')),
        ];
    }

    /** "Parçacık Fiziği, Kozmoloji" → ["Parçacık Fiziği", "Kozmoloji"] (yinelenenler atılır). @return ?list<string> */
    public static function topics(?string $value): ?array
    {
        $out = [];
        foreach (explode(',', (string) $value) as $t) {
            $t = trim(preg_replace('/\s+/u', ' ', $t));
            if ($t !== '' && mb_strlen($t) <= 60 && ! EmptyValue::is($t)) {
                $out[Text::key($t)] ??= $t;
            }
        }

        return $out ? array_values($out) : null;
    }
}
