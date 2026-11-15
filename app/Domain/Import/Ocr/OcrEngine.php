<?php

namespace App\Domain\Import\Ocr;

/** OCR motoru. Üretimde TesseractEngine; testlerde sahte motor bağlanır. */
interface OcrEngine
{
    public function available(): bool;

    /** Aynı görselin kaç farklı ön işlemle okunabildiği (okumalar alan alan oylanır). */
    public function passes(): int;

    /** 0. okumanın koordinatlarının özgün görsele oranı (kontrol ekranındaki alan kırpıntıları için). */
    public function scale(string $imagePath): float;

    /**
     * Kurulu dil kodları (tur, eng, deu…).
     *
     * @return list<string>
     */
    public function languages(): array;

    /**
     * Görseldeki kelimeler (konumlarıyla).
     *
     * @param  int  $pass  ön işlem biçimi (0 … passes()-1)
     * @param  ?string  $languages  "tur+deu+eng"; boşsa varsayılan diller
     * @return list<OcrWord>
     */
    public function words(string $imagePath, int $pass = 0, ?string $languages = null): array;

    /**
     * Görselin bir bölgesini tek satır olarak okur (ISBN, sayfa sayısı gibi alanların ikinci okuması).
     *
     * @param  array{0:int,1:int,2:int,3:int}  $box  x, y, genişlik, yükseklik (0. okumanın koordinatlarında)
     */
    public function line(string $imagePath, array $box, bool $digitsOnly = false): string;

    /** Sayfanın düz metni (kapak, künye): otomatik sayfa düzeni, satır satır. */
    public function text(string $imagePath, ?string $languages = null): string;
}
