<?php

namespace App\Domain\Import;

use App\Domain\Import\Ocr\OcrWord;
use App\Support\Text;

/**
 * Bilgi tablosu okuyucu. Ekran görüntüleri aynı şablonda: üstte kitap adı, altında iki sütunlu tablo
 * ("Ürün Bilgisi | Açıklama"; Ürün Kodu, Yazar, Yayınevi, Türü, ISBN / Barkod, Sayfa Sayısı).
 *
 * Kelimeler satırlara gruplanır; değer sütununun başlangıcı başlık satırındaki "Açıklama" kelimesinden veya etiketli
 * satırlardan bulunur. Etiketler bulanık eşleşir; OCR bir harfi yanlış okusa da ("Yazr", "lSBN") satır tanınır.
 * İki satıra taşan değerler (uzun "Türü") birleştirilir.
 */
class InfoSheetParser
{
    /** alan => etiket anahtarları (Text::key biçiminde) */
    public const LABELS = [
        'header' => ['urun bilgisi', 'bilgi', 'ozellik'],
        'code' => ['urun kodu', 'urun kod', 'kod', 'stok kodu'],
        'author' => ['yazar', 'yazari', 'yazarlar', 'yazar adi'],
        'publisher' => ['yayinevi', 'yayin evi', 'yayinci', 'yayinci firma'],
        'category' => ['turu', 'tur', 'kategori', 'tur kategori'],
        'isbn' => ['isbn barkod', 'isbn', 'barkod', 'isbn no'],
        'pages' => ['sayfa sayisi', 'sayfa', 'sayfa adedi'],
        'year' => ['baski yili', 'basim yili', 'yayin yili', 'yil'],
    ];

    private const EMPTY_VALUES = ['henuz dogrulanmadi', 'dogrulanmadi', 'bilinmiyor', 'yok', 'belirtilmemis', 'n a', 'na', 'aciklama'];

    /**
     * @param  list<OcrWord>  $words
     * @return array{title: ?string, fields: array<string, ?string>, boxes: array<string, array{0:int,1:int,2:int,3:int}>, conf: array<string, float>, lines: list<string>}
     */
    public function parse(array $words): array
    {
        $lines = $this->groupLines($words);
        $valueX = $this->valueColumn($lines);

        $fields = [];
        $boxes = [];
        $conf = [];
        $headerIndex = null;
        $firstLabelIndex = null;
        $lastField = null;
        $lastLabelIndex = null;

        foreach ($lines as $i => $line) {
            [$labelWords, $valueWords] = $this->split($line, $valueX);
            $field = $labelWords ? $this->matchLabel($labelWords) : null;

            if ($field === 'header') {
                $headerIndex ??= $i;
                $lastField = null;

                continue;
            }
            if ($field !== null && ! array_key_exists($field, $fields)) {
                $firstLabelIndex ??= $i;
                $fields[$field] = $this->text($valueWords);
                $boxes[$field] = $this->box($valueWords);
                $conf[$field] = $this->confidence($valueWords);
                $lastField = $field;
                $lastLabelIndex = $i;

                continue;
            }
            // Etiketsiz ve yalnız değer sütununda olan satır, hemen önceki alanın devamıdır (taşan "Türü").
            if ($field === null && $lastField !== null && $labelWords === [] && $valueWords !== [] && $i === $lastLabelIndex + 1) {
                $fields[$lastField] = trim($fields[$lastField].' '.$this->text($valueWords));
                $lastLabelIndex = $i;

                continue;
            }
            $lastField = null;
        }

        $tableStart = $headerIndex ?? $firstLabelIndex;
        $title = null;
        if ($tableStart !== null) {
            for ($j = $tableStart - 1; $j >= 0; $j--) {
                $candidate = $this->text($lines[$j]);
                if (preg_match('/\p{L}{2,}/u', $candidate)) {
                    $title = $candidate;
                    break;
                }
            }
        }

        foreach ($fields as $k => $v) {
            $fields[$k] = $this->clean($v);
        }

        return [
            'title' => $title !== null ? $this->clean($title) : null,
            'fields' => $fields,
            'boxes' => $boxes,
            'conf' => $conf,
            'lines' => array_map(fn ($l) => $this->text($l), $lines),
        ];
    }

    /**
     * @param  list<OcrWord>  $words
     * @return list<list<OcrWord>>
     */
    private function groupLines(array $words): array
    {
        usort($words, fn ($a, $b) => $a->centerY() <=> $b->centerY() ?: $a->left <=> $b->left);
        $lines = [];
        $current = [];
        $center = null;
        $height = null;
        foreach ($words as $w) {
            if ($current !== [] && abs($w->centerY() - $center) > max(6, $height * 0.6)) {
                $lines[] = $current;
                $current = [];
            }
            $current[] = $w;
            $center = array_sum(array_map(fn ($x) => $x->centerY(), $current)) / count($current);
            $height = max(array_map(fn ($x) => $x->height, $current));
        }
        if ($current) {
            $lines[] = $current;
        }

        return array_map(function ($l) {
            usort($l, fn ($a, $b) => $a->left <=> $b->left);

            return $l;
        }, $lines);
    }

    /** Değer sütununun başladığı x: başlık satırındaki ikinci sütun veya etiketli satırlardaki ilk değer kelimelerinin ortancası. */
    private function valueColumn(array $lines): ?int
    {
        $starts = [];
        foreach ($lines as $line) {
            [$label, $value] = $this->splitByGap($line);
            if ($label && $value && $this->matchLabel($label) !== null) {
                $starts[] = $value[0]->left;
            }
        }
        if (! $starts) {
            return null;
        }
        sort($starts);

        return $starts[intdiv(count($starts), 2)];
    }

    /** Satırı etiket ve değer kelimelerine ayırır. */
    private function split(array $line, ?int $valueX): array
    {
        if ($valueX !== null) {
            $tolerance = 12;
            $label = array_values(array_filter($line, fn ($w) => $w->left < $valueX - $tolerance));
            $value = array_values(array_filter($line, fn ($w) => $w->left >= $valueX - $tolerance));
            if ($label !== [] || $value !== []) {
                return [$label, $value];
            }
        }

        return $this->splitByGap($line);
    }

    /** Ardışık iki kelime arasındaki en büyük boşluk (satırdaki harf yüksekliğinin 2 katından büyükse) sütun ayrımıdır. */
    private function splitByGap(array $line): array
    {
        if (count($line) < 2) {
            return [$line, []];
        }
        $best = null;
        $bestGap = 0;
        $h = max(array_map(fn ($w) => $w->height, $line));
        for ($i = 1; $i < count($line); $i++) {
            $gap = $line[$i]->left - $line[$i - 1]->right();
            if ($gap > $bestGap) {
                $bestGap = $gap;
                $best = $i;
            }
        }
        if ($best === null || $bestGap < $h * 2) {
            return [$line, []];
        }

        return [array_slice($line, 0, $best), array_slice($line, $best)];
    }

    private function matchLabel(array $labelWords): ?string
    {
        $key = Text::key($this->text($labelWords));
        if ($key === '') {
            return null;
        }
        $bestField = null;
        $bestScore = 0.0;
        foreach (self::LABELS as $field => $keys) {
            foreach ($keys as $candidate) {
                $score = $this->similarity($key, $candidate);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestField = $field;
                }
            }
        }

        return $bestScore >= 0.75 ? $bestField : null;
    }

    private function similarity(string $a, string $b): float
    {
        $max = max(strlen($a), strlen($b));

        return $max === 0 ? 0.0 : 1 - levenshtein($a, $b) / $max;
    }

    private function text(array $words): string
    {
        return trim(implode(' ', array_map(fn ($w) => $w->text, $words)));
    }

    /** @return array{0:int,1:int,2:int,3:int} */
    private function box(array $words): array
    {
        if ($words === []) {
            return [0, 0, 0, 0];
        }
        $x1 = min(array_map(fn ($w) => $w->left, $words));
        $y1 = min(array_map(fn ($w) => $w->top, $words));
        $x2 = max(array_map(fn ($w) => $w->right(), $words));
        $y2 = max(array_map(fn ($w) => $w->top + $w->height, $words));

        return [$x1, $y1, $x2 - $x1, $y2 - $y1];
    }

    private function confidence(array $words): float
    {
        return $words === [] ? 0.0 : round(min(array_map(fn ($w) => $w->conf, $words)), 1);
    }

    private function clean(?string $value): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value), " \t:|;");
        if ($value === '' || in_array(Text::key($value), self::EMPTY_VALUES, true)) {
            return null;
        }

        return $value;
    }
}
