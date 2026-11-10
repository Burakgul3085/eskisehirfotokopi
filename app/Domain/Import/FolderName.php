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
        $name = preg_replace('/\s*(EKRAN\s+G[ÖO]R[ÜU]NT[ÜU]LER[İI]|EKRAN\s+G[ÖO]R[ÜU]NT[ÜU]S[ÜU]|SCREENSHOTS?)\s*$/iu', '', trim($folder));
        $name = trim(preg_replace('/\s+/u', ' ', $name), ' -_');

        return $name !== '' ? $name : null;
    }

    /**
     * @return array{publisher: ?string, title: ?string, author: ?string}
     */
    public static function book(string $folder, ?string $publisherFolder = null): array
    {
        $parts = array_values(array_filter(array_map('trim', preg_split('/\s+[-–—]\s+/u', trim($folder))), fn ($p) => $p !== ''));
        $publisher = self::publisher($publisherFolder);

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
