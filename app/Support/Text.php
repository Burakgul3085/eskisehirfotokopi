<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Türkçe metin yardımcıları: büyük/küçük harf, eşleştirme anahtarı, slug. */
class Text
{
    private const UPPER = ['I', 'İ', 'Ş', 'Ğ', 'Ü', 'Ö', 'Ç'];

    private const LOWER = ['ı', 'i', 'ş', 'ğ', 'ü', 'ö', 'ç'];

    public static function lower(string $s): string
    {
        return mb_strtolower(str_replace(self::UPPER, self::LOWER, $s), 'UTF-8');
    }

    public static function upper(string $s): string
    {
        return mb_strtoupper(str_replace(self::LOWER, self::UPPER, $s), 'UTF-8');
    }

    /** Vitrinde kişi adı gizliliği: "ayşe nur kaya" → "Ayşe K.", tek kelimede "Ayşe". Boşsa "Müşteri". */
    public static function maskName(?string $name): string
    {
        $words = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (! $words) {
            return 'Müşteri';
        }
        $first = self::upper(mb_substr($words[0], 0, 1)).self::lower(mb_substr($words[0], 1));

        return count($words) > 1 ? $first.' '.self::upper(mb_substr(end($words), 0, 1)).'.' : $first;
    }

    /** "BOĞAZİÇİ ÜNİVERSİTESİ" → "Boğaziçi Üniversitesi" (bağlaçlar küçük kalır). */
    public static function title(string $s): string
    {
        $small = ['ve', 'ile', 'veya', 'de', 'da', 'ki', 'için'];
        $words = preg_split('/(\s+)/u', trim(self::lower($s)), -1, PREG_SPLIT_DELIM_CAPTURE);
        $first = true;
        foreach ($words as $i => $w) {
            if (trim($w) === '') {
                continue;
            }
            if (! $first && in_array($w, $small, true)) {
                $first = false;

                continue;
            }
            $words[$i] = self::upper(mb_substr($w, 0, 1)).mb_substr($w, 1);
            $first = false;
        }

        return implode('', $words);
    }

    /**
     * Karşılaştırma anahtarı: küçük harf, Türkçe harfler ASCII'ye, noktalama boşluğa, fazla boşluk tek.
     * "Hız Yayınları", "HIZ YAYINLARI", "hiz  yayinlari" → "hiz yayinlari".
     */
    public static function key(string $s): string
    {
        $s = strtr(self::lower($s), ['ı' => 'i', 'ş' => 's', 'ğ' => 'g', 'ü' => 'u', 'ö' => 'o', 'ç' => 'c', 'â' => 'a', 'î' => 'i', 'û' => 'u']);
        $s = Str::ascii($s);
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);

        return trim(preg_replace('/\s+/', ' ', $s));
    }

    public static function slug(string $s): string
    {
        return Str::slug(self::key($s)) ?: 'urun';
    }
}
