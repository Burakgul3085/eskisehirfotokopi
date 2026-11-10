<?php

namespace App\Domain\Import;

use App\Support\Text;

/**
 * Bilgi tablosu olmayan klasörler için kapak ve künye (BASIM) sayfasının düz OCR metninden bilgi çıkarır.
 *  - Kapak: yazar (kitap türü satırının altındaki ad, ya da "Yazar:/Editör:" satırı) ve özellik maddeleri
 *    ("> Kavramlarla Konu Özeti") → ürün açıklaması.
 *  - Künye: baskı tarihi → basım yılı; ISBN (kontrol hanesi tutuyorsa).
 * Yalnız biçimi güvenilir olan değerler alınır; emin olunamayan alan boş kalır (kontrol ekranında doldurulur).
 */
class PageReader
{
    // Kalıplar Text::lower() metne uygulanır: PCRE'nin /i bayrağı Türkçe "İ"yi "i" saymaz ("BASKI TARİHİ" kaçardı).
    private const TYPE_LINE = '/soru\s*bankas|defter|deneme|konu\s*anlat|tam\s*tekrar|fasik[üu]l|yaprak\s*test|konu\s*[öo]zetl/u';

    /** Ad olamayacak kelimeler (kapaktaki slogan ve rozetler) */
    private const NOT_NAME = ['konu', 'özetli', 'soru', 'bankası', 'video', 'destekli', 'detaylı', 'çözümlü', 'yayınları', 'yayınevi',
        'kavramlarla', 'defter', 'deneme', 'test', 'testi', 'yeni', 'nesil', 'orijinal', 'shorts', 'mobil', 'hoca', 'tyt', 'ayt'];

    /** @return array{author: ?string, features: list<string>, isbn: ?string} */
    public static function cover(string $text): array
    {
        $lines = self::lines($text);

        return ['author' => self::author($lines), 'features' => self::features($lines), 'isbn' => self::isbn($text)];
    }

    /** @return array{year: ?int, isbn: ?string} */
    public static function imprint(string $text): array
    {
        $flat = preg_replace('/\s+/u', ' ', $text);
        $year = null;
        $low = Text::lower($flat);
        $months = 'ocak|şubat|mart|nisan|mayıs|haziran|temmuz|ağustos|eylül|ekim|kasım|aralık';
        // 1) "Baskı Tarihi … 2022" (etiket bulanık: kutu içindeki beyaz yazı "CeaskıTarini" okunabiliyor)
        // 2) Etiket okunamadıysa künyedeki "Mart 2022" gibi ay + yıl
        if (preg_match('/ask[ıi]\s*tar[iı][hn]\D{0,40}?((?:19|20)\d{2})/u', $low, $m)
            || preg_match('/\b(?:'.$months.')\s*[,.]?\s*((?:19|20)\d{2})\b/u', $low, $m)) {
            $y = (int) $m[1];
            $year = $y >= 1950 && $y <= (int) date('Y') + 1 ? $y : null; // OCR "2023"ü "2028" okuyabilir
        }

        return ['year' => $year, 'isbn' => self::isbn($text)];
    }

    /** Metindeki geçerli ilk ISBN-13 (978/979 ile başlayan, kontrol hanesi tutan). */
    public static function isbn(string $text): ?string
    {
        preg_match_all('/97[89][\d\s\-]{10,16}/u', $text, $m);
        foreach ($m[0] as $candidate) {
            $digits = preg_replace('/\D/', '', $candidate);
            if ($isbn = Isbn::normalize(substr($digits, 0, 13))) {
                return $isbn;
            }
        }

        return null;
    }

    /** @return list<string> */
    private static function lines(string $text): array
    {
        return array_values(array_filter(array_map(fn ($l) => trim(preg_replace('/\s+/u', ' ', $l)), preg_split('/\r?\n/', $text)),
            fn ($l) => $l !== ''));
    }

    /** @param  list<string>  $lines */
    private static function author(array $lines): ?string
    {
        // 1) Etiketli satır: "Yazar: …" önce, sonra "Editör: …"
        foreach (['yazar|yazan|hazırlayan', 'editör|editor'] as $labels) {
            foreach ($lines as $line) {
                // Text::lower harf sayısını korur: eşleşen etiketin uzunluğu kadar özgün satırdan kesilir.
                if (preg_match('/^(?:'.$labels.')\s*[:.]?\s*/u', Text::lower($line), $m) && $name = self::name(mb_substr($line, mb_strlen($m[0])))) {
                    return $name;
                }
            }
        }
        // 2) Kitap türü satırının ("SORU BANKASI") hemen altındaki ad(lar):
        //    "Ali Rıza Özata - Fatih Kazova - Şerif Karataş Editör: Özgür Balcı" → yazarlar virgülle; editör atılır.
        foreach ($lines as $i => $line) {
            if (preg_match(self::TYPE_LINE, Text::lower($line))) {
                foreach (array_slice($lines, $i + 1, 2) as $next) {
                    if (preg_match('/\s*(?:edit[öo]r|yay[ıi]n y[öo]netmeni)\s*:/u', Text::lower($next), $m, PREG_OFFSET_CAPTURE)) {
                        $next = trim(mb_substr($next, 0, mb_strlen(substr(Text::lower($next), 0, $m[0][1]))));
                    }
                    $names = array_map(fn ($p) => self::name($p), preg_split('/\s+[-–]\s+|\s*,\s*/u', $next));
                    if ($names && ! in_array(null, $names, true)) {
                        return implode(', ', $names);
                    }
                }
            }
        }

        return null;
    }

    /** "Kerim-Korkmaz" → "Kerim Korkmaz"; 2–4 kelimelik, her kelimesi büyük harfle başlayan ad değilse null. */
    private static function name(string $s): ?string
    {
        $s = trim(preg_replace('/\s+/u', ' ', str_replace(['-', '_', '•', '|'], ' ', $s)), ' .,;:');
        if (! preg_match('/^\p{Lu}\p{Ll}+(?: \p{Lu}\p{Ll}+){1,3}$/u', $s)) {
            return null;
        }
        foreach (explode(' ', $s) as $w) {
            if (in_array(Text::lower($w), self::NOT_NAME, true)) {
                return null;
            }
        }

        return $s;
    }

    /**
     * Kapaktaki madde işaretli özellikler ("> Kavramlarla Konu Özeti").
     *
     * @param  list<string>  $lines
     * @return list<string>
     */
    private static function features(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            if (preg_match('/^[>›»•▪►✓✔]\s*(\p{L}[\p{L}\s\'’\-]{4,70})$/u', $line, $m)) {
                $f = trim($m[1]);
                if (count(explode(' ', $f)) >= 2 && ! in_array($f, $out, true)) {
                    $out[] = $f;
                }
            }
        }

        return array_slice($out, 0, 8);
    }
}
