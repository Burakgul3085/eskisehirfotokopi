<?php

namespace App\Domain\Import;

use App\Models\Publisher;
use App\Support\Text;

/**
 * Klasör adlarından bilgi:
 *  - Yayınevi klasörü: "BOĞAZİÇİ ÜNİVERSİTESİ YAYINLARI EKRAN GÖRÜNTÜLERİ" → "BOĞAZİÇİ ÜNİVERSİTESİ YAYINLARI"
 *  - Kitap klasörü:    "YAYINEVİ - KİTAP ADI - YAZAR" (ayraç " - ", " – " veya " — ")
 */
class FolderName
{
    public static function publisher(?string $folder): ?string
    {
        if ($folder === null) {
            return null;
        }
        $folder = self::nfc($folder);
        $name = preg_replace('/\s*(EKRAN\s+G[ÖO]R[ÜU]NT[ÜU]LER[İI]|EKRAN\s+G[ÖO]R[ÜU]NT[ÜU]S[ÜU]|SCREENSHOTS?)\s*$/iu', '', trim($folder));
        $name = trim(preg_replace('/\s+/u', ' ', $name), ' -_');

        return $name !== '' ? $name : null;
    }

    /**
     * Ayraçsız adlar da desteklenir: "3D YAYINLARI AYT BİYOLOJİ SORU BANKASI 2023" → yayınevi öneki atılır,
     * sondaki yıl basım yılı olarak da döner. Klasör adına karışmış uzantı (".pdf") atılır.
     *
     * @return array{publisher: ?string, title: ?string, author: ?string, year: ?int}
     */
    public static function book(string $folder, ?string $publisherFolder = null): array
    {
        $folder = trim(preg_replace('/\.(pdf|docx?|zip|rar)$/iu', '', trim(self::nfc($folder))));
        $parts = array_values(array_filter(array_map('trim', preg_split('/\s+[-–—]\s+/u', $folder)), fn ($p) => $p !== ''));
        $publisher = self::publisher($publisherFolder);

        // Ayraçsız ad: baştaki yayınevi adı (klasördeki veya "… YAYINLARI" biçimindeki) atılır.
        if (count($parts) === 1) {
            [$prefix, $rest] = self::splitPublisherPrefix($parts[0], $publisher);
            if ($prefix !== null && $rest !== '') {
                $publisher ??= $prefix;
                $parts = [$rest];
            }
        }
        $year = null;
        if ($parts && preg_match('/\b((?:19|20)\d{2})$/u', end($parts), $m) && (int) $m[1] <= (int) date('Y') + 1) {
            $year = (int) $m[1];
        }

        return self::splitParts($parts, $publisher) + ['year' => $year];
    }

    /**
     * "3D YAYINLARI AYT FİZİK" → ["3D YAYINLARI", "AYT FİZİK"]; önek bulunamazsa [null, ad].
     *
     * @return array{0: ?string, 1: string}
     */
    private static function splitPublisherPrefix(string $name, ?string $publisher): array
    {
        $words = preg_split('/\s+/u', $name);
        $max = min(count($words) - 1, 8);
        if ($publisher !== null) {
            $key = Publisher::matchKey($publisher);
            for ($k = $max; $k >= 1; $k--) { // en uzun eşleşen önek ("3D YAYINLARI", sonra "3D")
                $prefix = implode(' ', array_slice($words, 0, $k));
                if (Publisher::matchKey($prefix) === $key) {
                    return [$prefix, implode(' ', array_slice($words, $k))];
                }
            }
        }
        for ($k = 1; $k <= $max; $k++) {
            if (preg_match('/^(YAYINLARI|YAYINEV[İI]|YAYINCILIK|KİTABEV[İI])$/iu', $words[$k - 1])) {
                return [implode(' ', array_slice($words, 0, $k)), implode(' ', array_slice($words, $k))];
            }
        }

        return [null, $name];
    }

    /**
     * @param  list<string>  $parts
     * @return array{publisher: ?string, title: ?string, author: ?string}
     */
    private static function splitParts(array $parts, ?string $publisher): array
    {

        // İlk parça yayınevi klasörüyle aynıysa (veya yayınevi adı gibi görünüyorsa) yayınevidir.
        $first = $parts[0] ?? null;
        $firstIsPublisher = $first !== null && count($parts) >= 2 && (
            ($publisher !== null && Publisher::matchKey($first) === Publisher::matchKey($publisher))
            || preg_match('/\b(YAYIN|YAYINLARI|YAYINEV[İI]|YAYINCILIK|KİTABEV[İI])\b/iu', $first)
        );
        if ($firstIsPublisher) {
            $publisher ??= $first;
            array_shift($parts);
        }

        $title = $parts[0] ?? null;
        $author = count($parts) >= 2 ? end($parts) : null;
        if (count($parts) > 2) {
            $title = implode(' - ', array_slice($parts, 0, -1)); // adında tire olan kitap
        }

        return ['publisher' => $publisher, 'title' => $title, 'author' => $author];
    }

    /** macOS/bazı arşivler "İ"yi "I" + birleşik nokta olarak yazar; başlık biçimi bozulmasın diye birleştirilir. */
    public static function nfc(string $s): string
    {
        return class_exists(\Normalizer::class) ? (\Normalizer::normalize($s, \Normalizer::FORM_C) ?: $s) : $s;
    }

    /** İki adın aynı kitabı gösterip göstermediği (kesme işareti, büyük/küçük harf ve Türkçe karakter farkı yok sayılır). */
    public static function similar(?string $a, ?string $b, float $threshold = 0.85): bool
    {
        if ($a === null || $b === null) {
            return false;
        }
        $ka = str_replace(' ', '', Text::key(str_replace(["'", '’', '`'], '', $a)));
        $kb = str_replace(' ', '', Text::key(str_replace(["'", '’', '`'], '', $b)));
        if ($ka === '' || $kb === '') {
            return false;
        }
        if ($ka === $kb) {
            return true;
        }
        $max = max(strlen($ka), strlen($kb));

        return 1 - levenshtein(substr($ka, 0, 255), substr($kb, 0, 255)) / min($max, 255) >= $threshold;
    }
}
