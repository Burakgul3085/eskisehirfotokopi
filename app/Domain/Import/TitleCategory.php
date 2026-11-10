<?php

namespace App\Domain\Import;

use App\Domain\Catalog\CategoryTree;
use App\Support\Text;

/**
 * Bilgi tablosunda "Türü" yoksa kategori kitap adından tahmin edilir: Tür › Sınav › Ders.
 *   "AYT Biyoloji Soru Bankası 2023"              → Soru Bankası › AYT › Biyoloji
 *   "TYT Matematik Konu Özetli Soru Bankası 2023" → Soru Bankası › TYT › Matematik
 *   "TYT Kimya Video Destekli Defter 2024"        → Defter › TYT › Kimya
 *   "Dil Bilgisi Soru Bankası 2023"               → Soru Bankası › Dil Bilgisi
 * Ne tür ne sınav tanınırsa tahmin yapılmaz (yanlış kategori açmaktansa kategorisiz kalır).
 */
class TitleCategory
{
    /** küçük harfli arama ifadesi => kategori adı; adda en önce geçen tür seçilir */
    private const TYPES = [
        'soru bankas' => 'Soru Bankası',
        'konu anlat' => 'Konu Anlatımı',
        'tam tekrar' => 'Tam Tekrar',
        'yaprak test' => 'Yaprak Test',
        'deneme' => 'Deneme',
        'defter' => 'Defter',
        'fasikül' => 'Fasikül',
    ];

    private const EXAMS = ['TYT', 'AYT', 'YKS', 'YDT', 'KPSS', 'EKPSS', 'ALES', 'DGS', 'LGS', 'YDS', 'YÖKDİL', 'MSÜ', 'ÖABT', 'TUS', 'DUS', 'PMYO', 'POMEM'];

    /** Ders adından atılan nitelik kelimeleri */
    private const MODIFIERS = ['konu', 'özetli', 'video', 'destekli', 'çözümlü', 'yeni', 'nesil', 've'];

    /** @return list<string> */
    public static function guess(?string $title): array
    {
        $title = trim(preg_replace('/\s+/u', ' ', (string) $title));
        $title = trim(preg_replace('/\b(19|20)\d{2}$/u', '', $title));
        if ($title === '') {
            return [];
        }
        $lower = Text::lower($title);

        $type = null;
        $typeAt = null;
        foreach (self::TYPES as $needle => $name) {
            $at = mb_strpos($lower, $needle);
            if ($at !== false && ($typeAt === null || $at < $typeAt)) {
                [$type, $typeAt] = [$name, $at];
            }
        }

        $words = explode(' ', $title);
        $exam = in_array(Text::upper($words[0]), self::EXAMS, true) ? Text::upper($words[0]) : null;
        if ($type === null && $exam === null) {
            return [];
        }

        // Ders: sınavdan sonra, türden önceki kelimeler (nitelikler ve sayılar atılır).
        $start = $exam !== null ? mb_strlen($words[0]) + 1 : 0;
        $middle = mb_substr($title, $start, $typeAt !== null ? max(0, $typeAt - $start) : null);
        $subject = implode(' ', array_filter(explode(' ', trim($middle)),
            fn ($w) => $w !== '' && ! preg_match('/^\d+$/', $w) && ! in_array(Text::lower($w), self::MODIFIERS, true)));
        $subject = $subject !== '' && mb_strlen($subject) <= 60 ? CategoryTree::displayName($subject) : null;

        return array_values(array_filter([$type, $exam, $subject]));
    }
}
