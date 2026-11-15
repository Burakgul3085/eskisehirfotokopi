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

    private const WEAK_TYPES = ['Deneme', 'Defter'];

    private const EXAMS = ['TYT', 'AYT', 'YKS', 'YDT', 'KPSS', 'EKPSS', 'ALES', 'DGS', 'LGS', 'YDS', 'YÖKDİL', 'MSÜ', 'ÖABT', 'TUS', 'DUS', 'PMYO', 'POMEM'];

    /** Ders adından atılan nitelik kelimeleri */
    private const MODIFIERS = ['konu', 'özetli', 'video', 'destekli', 'çözümlü', 'yeni', 'nesil', 've'];

    /** Sınıf düzeyi → kategori adı (Sınav Hazırlık altında) */
    public const GRADES = ['Ara Sınıflar' => '9|10|11|12', 'Ortaokul' => '5|6|7|8', 'İlkokul' => '1|2|3|4'];

    /** Tanınan kitap türleri (yönetim formundaki öneri listesi). @return list<string> */
    public static function types(): array
    {
        return array_values(self::TYPES);
    }

    /** Eski biçim (Tür › Sınav › Ders); yeni düzende parse() kullanılır. @return list<string> */
    public static function guess(?string $title): array
    {
        $p = self::parse($title);

        return array_values(array_filter([$p['type'], $p['exam'], $p['subject']]));
    }

    /**
     * Kitap adından tür, sınav, sınıf düzeyi ve ders:
     *   "345 TYT Matematik Soru Bankası 2024" → tür Soru Bankası, sınav TYT, ders Matematik
     *   "10. Sınıf Fizik Konu Anlatımı"       → tür Konu Anlatımı, düzey Ara Sınıflar, ders Fizik
     * "Deneme" yalnız sınav/düzey varsa tür sayılır (okuma kitaplarında "deneme" edebî türdür).
     *
     * @return array{type: ?string, exam: ?string, grade: ?string, subject: ?string}
     */
    public static function parse(?string $title): array
    {
        $none = ['type' => null, 'exam' => null, 'grade' => null, 'subject' => null];
        $title = trim(preg_replace('/\s+/u', ' ', (string) $title));
        $title = trim(preg_replace('/\b(19|20)\d{2}(\s*-\s*(19|20)?\d{2})?$/u', '', $title));
        if ($title === '') {
            return $none;
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

        // Sınav adı ilk üç kelimeden birinde olabilir ("345 TYT Matematik", "Palme Yayınları AYT Fizik")
        $words = explode(' ', $title);
        $exam = null;
        $examEnd = 0;
        foreach (array_slice($words, 0, 4) as $i => $w) {
            $up = Text::upper(trim($w, ',.:;-'));
            if (in_array($up, self::EXAMS, true)) {
                $exam = $up;
                $examEnd = mb_strlen(implode(' ', array_slice($words, 0, $i + 1))) + 1;
                break;
            }
        }
        $grade = null;
        if (preg_match('/\b('.implode('|', array_unique(explode('|', implode('|', self::GRADES)))).')\s*\.?\s*sınıf\b/u', $lower, $m, PREG_OFFSET_CAPTURE)) {
            foreach (self::GRADES as $name => $list) {
                if (in_array($m[1][0], explode('|', $list), true)) {
                    $grade = $name;
                }
            }
            $examEnd = max($examEnd, mb_strlen(substr($lower, 0, $m[0][1] + strlen($m[0][0]))) + 1);
        }
        // "Deneme" ve "Defter" okuma kitabı adlarında da geçer ("Deneme Yazıları", "Leonardo'nun Defterleri"):
        // yalnız sınav/düzey varsa tür sayılır.
        if (in_array($type, self::WEAK_TYPES, true) && $exam === null && $grade === null) {
            $type = null;
        }
        if ($type === null && $exam === null && $grade === null) {
            return $none;
        }

        // Ders: sınavdan/düzeyden sonra, türden önceki kelimeler (nitelikler ve sayılar atılır).
        $middle = mb_substr($title, $examEnd, $typeAt !== null ? max(0, $typeAt - $examEnd) : null);
        $subject = implode(' ', array_filter(explode(' ', trim($middle)),
            fn ($w) => $w !== '' && ! preg_match('/^\d+\.?$/', $w) && ! in_array(Text::lower($w), self::MODIFIERS, true)));
        $subject = $subject !== '' && mb_strlen($subject) <= 60 ? CategoryTree::displayName($subject) : null;

        return ['type' => $type, 'exam' => $exam, 'grade' => $grade, 'subject' => $subject];
    }
}
