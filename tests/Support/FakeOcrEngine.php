<?php

namespace Tests\Support;

use App\Domain\Import\Ocr\OcrEngine;
use App\Domain\Import\Ocr\OcrWord;

/**
 * Testlerde Tesseract yerine: bilgi tablosu şablonundaki gibi konumlanmış kelimeler üretir.
 * Satır: [etiket, değer]; etiket x=20'de, değer x=300'de başlar. İlk satır başlıktır.
 */
class FakeOcrEngine implements OcrEngine
{
    public array $lineReads = [];

    /** @param  list<array{0: ?string, 1: ?string}>  $rows */
    public function __construct(public ?string $title, public array $rows, public bool $ready = true, public array $lineAnswers = []) {}

    public static function sample(array $override = [], ?string $title = "Almanya'nın Kısa Tarihi"): self
    {
        $rows = array_replace([
            'header' => ['Ürün Bilgisi', 'Açıklama'],
            'code' => ['Ürün Kodu', 'AKT-583'],
            'author' => ['Yazar', 'Mary Fulbrook'],
            'publisher' => ['Yayınevi', 'Boğaziçi Üniversitesi Yayınevi'],
            'category' => ['Türü', 'Tarih / Avrupa Tarihi / Almanya Tarihi'],
            'isbn' => ['ISBN / Barkod', 'Henüz doğrulanmadı'],
            'pages' => ['Sayfa Sayısı', 'Henüz doğrulanmadı'],
        ], $override);

        return new self($title, array_values(array_filter($rows)));
    }

    public function available(): bool
    {
        return $this->ready;
    }

    public function words(string $imagePath): array
    {
        $words = [];
        $y = 20;
        if ($this->title) {
            $x = 40;
            foreach (explode(' ', $this->title) as $t) {
                $words[] = new OcrWord($t, $x, $y, mb_strlen($t) * 14, 30, 95);
                $x += mb_strlen($t) * 14 + 12;
            }
        }
        $y = 90;
        foreach ($this->rows as [$label, $value]) {
            foreach ([[$label, 20], [$value, 300]] as [$text, $x]) {
                if ($text === null) {
                    continue;
                }
                foreach (explode(' ', $text) as $t) {
                    $words[] = new OcrWord($t, $x, $y + (strlen($t) % 3), mb_strlen($t) * 9, 18, 91);
                    $x += mb_strlen($t) * 9 + 8;
                }
            }
            $y += 40;
        }

        return $words;
    }

    public function line(string $imagePath, array $box, bool $digitsOnly = false): string
    {
        $this->lineReads[] = $box;

        return array_shift($this->lineAnswers) ?? '';
    }
}
