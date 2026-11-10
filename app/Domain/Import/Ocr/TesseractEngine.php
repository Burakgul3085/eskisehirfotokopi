<?php

namespace App\Domain\Import\Ocr;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Tesseract 5 komut satırı motoru (yerel, ücretsiz). Görsel önce ön işlemden geçer: gri tonlama, 2× büyütme ve kontrast.
 * Ekran görüntülerindeki küçük yazılarda bu adım hata oranını belirgin düşürür. Koordinatlar ön işlenmiş görsele göredir;
 * line() de aynı ön işlenmiş görseli kullanır (önbellek: aynı yol için bir kez üretilir).
 */
class TesseractEngine implements OcrEngine
{
    private const SCALE = 2;

    /** @var array<string, string> özgün yol => ön işlenmiş geçici dosya */
    private array $prepared = [];

    public function available(): bool
    {
        static $ok = null;

        return $ok ??= Process::timeout(10)->run([config('catalog.tesseract'), '--version'])->successful();
    }

    public function words(string $imagePath): array
    {
        $tsv = $this->run([$this->prepare($imagePath), 'stdout', '-l', config('catalog.ocr_languages'), '--psm', '6', '--oem', '1',
            '-c', 'preserve_interword_spaces=1', 'tsv']);

        $words = [];
        foreach (preg_split('/\r?\n/', trim($tsv)) as $i => $line) {
            $cols = explode("\t", $line);
            if ($i === 0 || count($cols) < 12 || (int) $cols[0] !== 5) {
                continue; // başlık satırı veya kelime olmayan düzey
            }
            $text = trim($cols[11]);
            if ($text === '') {
                continue;
            }
            $words[] = new OcrWord($text, (int) $cols[6], (int) $cols[7], (int) $cols[8], (int) $cols[9], (float) $cols[10]);
        }

        return $words;
    }

    public function line(string $imagePath, array $box, bool $digitsOnly = false): string
    {
        [$x, $y, $w, $h] = $box;
        $src = imagecreatefrompng($this->prepare($imagePath));
        $pad = 6;
        $crop = imagecrop($src, ['x' => max(0, $x - $pad), 'y' => max(0, $y - $pad), 'width' => $w + 2 * $pad, 'height' => $h + 2 * $pad]);
        imagedestroy($src);
        if ($crop === false) {
            return '';
        }
        $tmp = tempnam(sys_get_temp_dir(), 'ocrl').'.png';
        imagepng($crop, $tmp);
        imagedestroy($crop);
        try {
            $args = [$tmp, 'stdout', '-l', $digitsOnly ? 'eng' : config('catalog.ocr_languages'), '--psm', '7', '--oem', '1'];
            if ($digitsOnly) {
                array_push($args, '-c', 'tessedit_char_whitelist=0123456789X-');
            }

            return trim($this->run($args));
        } finally {
            @unlink($tmp);
        }
    }

    public function __destruct()
    {
        foreach ($this->prepared as $file) {
            @unlink($file);
        }
    }

    private function run(array $args): string
    {
        $result = Process::timeout(config('catalog.ocr_timeout'))->env(['OMP_THREAD_LIMIT' => '1'])
            ->run([config('catalog.tesseract'), ...$args]);
        if (! $result->successful()) {
            throw new RuntimeException('OCR çalışmadı: '.mb_substr(trim($result->errorOutput()), 0, 200));
        }

        return $result->output();
    }

    /** Örneklenen piksellerin ortalama parlaklığı düşükse görsel koyu zeminlidir. */
    private function isDark(\GdImage $img): bool
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $sum = 0;
        $n = 0;
        for ($y = 0; $y < $h; $y += max(1, intdiv($h, 40))) {
            for ($x = 0; $x < $w; $x += max(1, intdiv($w, 40))) {
                $sum += imagecolorat($img, $x, $y) & 0xFF; // gri tonlamada kanallar eşit
                $n++;
            }
        }

        return $n > 0 && $sum / $n < 110;
    }

    /** Gri tonlama + 2× büyütme + (koyu zeminde ters çevirme) + kontrast; PNG olarak geçici dosyaya yazılır. */
    private function prepare(string $path): string
    {
        if (isset($this->prepared[$path])) {
            return $this->prepared[$path];
        }
        $data = @file_get_contents($path);
        $img = $data !== false ? @imagecreatefromstring($data) : false;
        if ($img === false) {
            throw new RuntimeException('Bilgi görseli açılamadı.');
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $big = imagecreatetruecolor($w * self::SCALE, $h * self::SCALE);
        imagefill($big, 0, 0, imagecolorallocate($big, 255, 255, 255));
        imagecopyresampled($big, $img, 0, 0, 0, 0, $w * self::SCALE, $h * self::SCALE, $w, $h);
        imagedestroy($img);
        imagefilter($big, IMG_FILTER_GRAYSCALE);
        if ($this->isDark($big)) {
            imagefilter($big, IMG_FILTER_NEGATE); // koyu temalı ekran görüntüsü: Tesseract açık zeminde koyu yazıyı daha iyi okur
        }
        imagefilter($big, IMG_FILTER_CONTRAST, -25);
        $tmp = tempnam(sys_get_temp_dir(), 'ocr').'.png';
        imagepng($big, $tmp);
        imagedestroy($big);

        return $this->prepared[$path] = $tmp;
    }
}
