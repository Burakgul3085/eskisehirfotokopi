<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Arama sonucu vurgusu: metinde aranan kelimelerin geçtiği yerleri <mark> içine alır.
 * Eşleşme Türkçe harf ve büyük/küçük harf farkı gözetmez ("iletisim" → "İletişim"); çıktı HTML olarak kaçışlıdır.
 */
class Highlight
{
    /** @param list<string> $terms Text::key ile sadeleşmiş kelimeler */
    public static function mark(string $text, array $terms): string
    {
        $chars = mb_str_split($text);
        if ($chars === [] || $terms === []) {
            return e($text);
        }
        // Her harfin tek karakterlik sade karşılığı (konumlar birebir kalsın)
        $plain = '';
        foreach ($chars as $c) {
            $k = Str::ascii(strtr(Text::lower($c), ['ı' => 'i', 'ş' => 's', 'ğ' => 'g', 'ü' => 'u', 'ö' => 'o', 'ç' => 'c', 'â' => 'a', 'î' => 'i', 'û' => 'u']));
            $k = strtolower(substr($k, 0, 1));
            $plain .= preg_match('/[a-z0-9]/', $k) ? $k : ' ';
        }
        $on = array_fill(0, count($chars), false);
        foreach ($terms as $t) {
            for ($i = strpos($plain, $t); $i !== false; $i = strpos($plain, $t, $i + 1)) {
                for ($j = $i; $j < $i + strlen($t); $j++) {
                    $on[$j] = true;
                }
            }
        }
        $out = '';
        $open = false;
        foreach ($chars as $i => $c) {
            if ($on[$i] !== $open) {
                $out .= $on[$i] ? '<mark class="search-mark">' : '</mark>';
                $open = $on[$i];
            }
            $out .= e($c);
        }

        return $open ? $out.'</mark>' : $out;
    }
}
