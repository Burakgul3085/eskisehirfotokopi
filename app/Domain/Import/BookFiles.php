<?php

namespace App\Domain\Import;

/**
 * Kitap klasöründeki dosyaların rolü: bilgi tablosu (OCR) ve mockup. "1.jpg" gibi adı yalnız rakam olan dosya
 * kullanılmaz (K5). Aynı kural tarayıcıda da uygulanır (resources/js/folder-import.js) ki gereksiz dosya yüklenmesin.
 */
class BookFiles
{
    public const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp'];

    public const INFO_PATTERN = '/ekran\s*g[öo]r[üu]nt|screenshot|ekran al[ıi]nt|bilgi/u';

    public const MOCKUP_PATTERN = '/chatgpt|mock\s*-?\s*up|mockup/u';

    /**
     * @param  list<string>  $names  klasördeki dosya adları
     * @return array{info: ?string, mockup: ?string}
     */
    public static function classify(array $names): array
    {
        $info = null;
        $mockup = null;
        $others = [];
        foreach ($names as $n) {
            if (! in_array(strtolower(pathinfo($n, PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, true)) {
                continue;
            }
            $base = mb_strtolower(pathinfo($n, PATHINFO_FILENAME));
            if (preg_match('/^\d+$/', $base)) {
                continue; // K5
            }
            if ($info === null && preg_match(self::INFO_PATTERN, $base)) {
                $info = $n;
            } elseif ($mockup === null && preg_match(self::MOCKUP_PATTERN, $base)) {
                $mockup = $n;
            } else {
                $others[] = $n;
            }
        }
        // Adı tanınmayan tek görsel kaldıysa mockup sayılır.
        if ($mockup === null && count($others) === 1) {
            $mockup = $others[0];
        }

        return ['info' => $info, 'mockup' => $mockup];
    }
}
