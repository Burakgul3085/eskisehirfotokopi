<?php

namespace App\Domain\Import\Ocr;

/** OCR motoru. Üretimde TesseractEngine; testlerde sahte motor bağlanır. */
interface OcrEngine
{
    public function available(): bool;

    /**
     * Görseldeki kelimeler (konumlarıyla).
     *
     * @return list<OcrWord>
     */
    public function words(string $imagePath): array;

    /**
     * Görselin bir bölgesini tek satır olarak okur (ISBN, sayfa sayısı gibi alanların ikinci okuması).
     *
     * @param  array{0:int,1:int,2:int,3:int}  $box  x, y, genişlik, yükseklik (words() koordinatlarında)
     */
    public function line(string $imagePath, array $box, bool $digitsOnly = false): string;

    /** Sayfanın düz metni (kapak, künye): otomatik sayfa düzeni, satır satır. */
    public function text(string $imagePath): string;
}
