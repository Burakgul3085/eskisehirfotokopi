<?php

namespace App\Domain\Import;

/** ISBN-10/13 doğrulama ve 13 haneye çevirme. */
class Isbn
{
    /** Geçerliyse 13 haneli ISBN, değilse null. OCR'ın sık karıştırdığı O→0, I/l→1 düzeltilir. */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $s = strtoupper(strtr($raw, ['O' => '0', 'o' => '0', 'I' => '1', 'l' => '1', '|' => '1']));
        $s = preg_replace('/[^0-9X]/', '', $s);
        if (strlen($s) === 13 && ctype_digit($s) && self::valid13($s)) {
            return $s;
        }
        if (strlen($s) === 10 && self::valid10($s)) {
            $base = '978'.substr($s, 0, 9);

            return $base.self::check13($base);
        }
        // Metin içinde 978/979 ile başlayan 13 hane aranır (etiket veya fazladan karakter yapışmışsa).
        if (preg_match_all('/97[89]\d{10}/', $s, $m)) {
            foreach ($m[0] as $candidate) {
                if (self::valid13($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    public static function valid13(string $s): bool
    {
        return strlen($s) === 13 && ctype_digit($s) && self::check13(substr($s, 0, 12)) === $s[12];
    }

    private static function check13(string $twelve): string
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $twelve[$i] * ($i % 2 ? 3 : 1);
        }

        return (string) ((10 - $sum % 10) % 10);
    }

    private static function valid10(string $s): bool
    {
        if (! preg_match('/^\d{9}[\dX]$/', $s)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += ($s[$i] === 'X' ? 10 : (int) $s[$i]) * (10 - $i);
        }

        return $sum % 11 === 0;
    }
}
