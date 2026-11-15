<?php

namespace Tests\Support;

use App\Domain\Import\Ocr\OcrEngine;
use App\Domain\Import\Ocr\OcrWord;

/**
 * Testlerde Tesseract yerine: bilgi tablosu şablonundaki gibi konumlanmış kelimeler üretir.
 * Satır: [etiket, değer]; etiket x=20'de, değer x=300'de başlar. İlk satır başlıktır.
 * $alternates: diğer okumalarda (1., 2. …) dönecek [başlık, satırlar] — oylama testleri için.
 */
class FakeOcrEngine implements OcrEngine
{
    public array $lineReads = [];

    /** @var list<array{0: ?string, 1: array}> */
    public array $alternates = [];

    /** @var list<array{0: int, 1: ?string}> words() çağrıları: [okuma, diller] */
    public array $wordCalls = [];

    /** @var array<string, string> rol (dosya adı: cover, imprint) => sayfa metni */
    public array $pageTexts = [];

    /** @param  list<array{0: ?string, 1: ?string}>  $rows */
    public function __construct(public ?string $title, public array $rows, public bool $ready = true, public array $lineAnswers = []) {}

    public static function sample(array $override = [], ?string $title = "Almanya'nın Kısa Tarihi"): self
    {
        return new self($title, self::rows($override));
    }

    public static function rows(array $override = []): array
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

        return array_values(array_filter($rows));
    }

    public function available(): bool
    {
        return $this->ready;
    }

    public function passes(): int
    {
        return 1 + count($this->alternates);
    }

    public function scale(string $imagePath): float
    {
        return 1.0;
    }

    public function languages(): array
    {
        return ['eng', 'tur', 'deu', 'ita'];
    }

    public function words(string $imagePath, int $pass = 0, ?string $languages = null): array
    {
        $this->wordCalls[] = [$pass, $languages];
        [$title, $rows] = $pass > 0 && isset($this->alternates[$pass - 1]) ? $this->alternates[$pass - 1] : [$this->title, $this->rows];

        $words = [];
        $y = 20;
        if ($title) {
            $x = 40;
            foreach (explode(' ', $title) as $t) {
                $words[] = new OcrWord($t, $x, $y, mb_strlen($t) * 14, 30, 95);
                $x += mb_strlen($t) * 14 + 12;
            }
        }
        $y = 90;
        foreach ($rows as [$label, $value]) {
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

    public function text(string $imagePath, ?string $languages = null): string
    {
        return $this->pageTexts[pathinfo($imagePath, PATHINFO_FILENAME)] ?? '';
    }

    public function line(string $imagePath, array $box, bool $digitsOnly = false): string
    {
        $this->lineReads[] = $box;

        return array_shift($this->lineAnswers) ?? '';
    }
}
