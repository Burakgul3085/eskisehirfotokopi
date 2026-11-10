<?php

namespace App\Domain\Import;

use App\Support\Text;

/**
 * Kitap klasöründeki dosyaların rolü: bilgi tablosu (OCR) ve ürün görseli ("mockup" rolü). Aynı kural tarayıcıda da
 * uygulanır (resources/js/folder-import.js) ki gereksiz dosya yüklenmesin.
 *
 * Ürün görseli önceliği: mockup ("MOCKUP", "ChatGPT Görseli") → kapak ("… KAPAK", "… KAP.") → adı tanınmayan tek görsel.
 * Adı yalnız rakam olan dosya ("1.jpg") kullanılmaz (K5). Künye ("BASIM") ve içindekiler ("İÇİN", "İÇİNDEKİLER")
 * sayfaları ürün görseli olamaz; yok sayılır.
 */
class BookFiles
{
    public const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp'];

    public const INFO_PATTERN = '/ekran\s*g[öo]r[üu]nt|screenshot|ekran al[ıi]nt|(^|[^\p{L}])bilgi([^\p{L}]|$)/u';

    public const MOCKUP_PATTERN = '/chatgpt|mock\s*-?\s*up|mockup/u';

    public const COVER_PATTERN = '/(^|[^\p{L}])kap(ak)?([^\p{L}]|$)/u';

    public const IGNORE_PATTERN = '/bas[ıi]m|[iı]?çindekiler|(^|[^\p{L}])[iı]?çin([^\p{L}]|$)|k[üu]nye/u';

    /**
     * @param  list<string>  $names  klasördeki dosya adları
     * @return array{info: ?string, mockup: ?string}
     */
    public static function classify(array $names): array
    {
        $info = null;
        $mockup = null;
        $cover = null;
        $others = [];
        foreach ($names as $n) {
            if (! self::isImage($n)) {
                continue;
            }
            $base = Text::lower(pathinfo($n, PATHINFO_FILENAME));
            if (preg_match('/^\d+$/', $base)) {
                continue; // K5
            }
            if ($info === null && preg_match(self::INFO_PATTERN, $base)) {
                $info = $n;
            } elseif ($mockup === null && preg_match(self::MOCKUP_PATTERN, $base)) {
                $mockup = $n;
            } elseif (preg_match(self::IGNORE_PATTERN, $base)) {
                continue;
            } elseif ($cover === null && preg_match(self::COVER_PATTERN, $base)) {
                $cover = $n;
            } else {
                $others[] = $n;
            }
        }
        // Mockup yoksa kapak; o da yoksa adı tanınmayan tek görsel ürün görseli sayılır.
        $mockup ??= $cover ?? (count($others) === 1 ? $others[0] : null);

        return ['info' => $info, 'mockup' => $mockup];
    }

    public static function isImage(string $name): bool
    {
        return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, true);
    }
}
