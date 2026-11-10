<?php

namespace App\Support;

/** Tutar biçimleme ve ayrıştırma (TL, 2 hane, bcmath). */
class Money
{
    /** "1.234,50 ₺" */
    public static function format(string|int|float|null $amount, bool $symbol = true): string
    {
        $value = number_format((float) $amount, 2, ',', '.');

        return $symbol ? $value.' ₺' : $value;
    }

    /**
     * Kullanıcı girişini ayrıştırır: "1.234,50", "1234.5", "1234,5", "₺ 99", 99.9 → "1234.50" / null (geçersiz).
     * Hem nokta hem virgül varsa sondaki ondalık ayırıcı kabul edilir.
     */
    public static function parse(mixed $input): ?string
    {
        if (is_int($input) || is_float($input)) {
            return $input < 0 ? null : number_format((float) $input, 2, '.', '');
        }
        $s = preg_replace('/[^\d,.\-]/', '', (string) $input);
        if ($s === '' || str_contains($s, '-')) {
            return null;
        }
        $lastComma = strrpos($s, ',');
        $lastDot = strrpos($s, '.');
        if ($lastComma !== false && $lastDot !== false) {
            $decimal = $lastComma > $lastDot ? ',' : '.';
            $s = str_replace($decimal === ',' ? '.' : ',', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif ($lastComma !== false) {
            $s = str_replace(',', '.', $s);
        } elseif ($lastDot !== false && substr_count($s, '.') > 1) {
            $s = str_replace('.', '', $s); // 1.234.567 binlik
        } elseif ($lastDot !== false && strlen($s) - $lastDot - 1 === 3 && $lastDot > 0) {
            $s = str_replace('.', '', $s); // "1.250" → binlik ayırıcı (fiyatlarda 3 ondalık olmaz)
        }
        if (! is_numeric($s) || substr_count($s, '.') > 1) {
            return null;
        }

        return bcadd($s, '0', 2);
    }
}
