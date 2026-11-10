<?php

namespace App\Domain\Import\Ocr;

/** Tesseract TSV çıktısındaki bir kelime: konum (piksel) ve güven (0–100). */
final class OcrWord
{
    public function __construct(
        public readonly string $text,
        public readonly int $left,
        public readonly int $top,
        public readonly int $width,
        public readonly int $height,
        public readonly float $conf,
    ) {}

    public function right(): int
    {
        return $this->left + $this->width;
    }

    public function centerY(): float
    {
        return $this->top + $this->height / 2;
    }
}
