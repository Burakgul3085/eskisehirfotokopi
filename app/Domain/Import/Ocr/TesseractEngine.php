<?php

namespace App\Domain\Import\Ocr;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Tesseract 5 komut satırı motoru (yerel, ücretsiz). Görsel her okumada farklı ön işlemden geçer (passes()):
 *  0: gri tonlama + 2× büyütme, 1: gri tonlama + 3× büyütme, 2: renkli + 3× büyütme.
 * Gerçek ekran görüntülerinde her biçim başka harfleri yanlış okuyor ("Boksdriin" / "Boksörün"); okumalar oylanınca
 * hatalı harf azınlıkta kalır. Koordinatlar 0. okumanın görseline göredir; line() de o görseli kullanır.
 * Dil dosyaları: OCR_TESSDATA klasörü (tessdata_best: tur, eng, deu, ita, fra, spa); boşsa Tesseract'ın kendi klasörü.
 */
class TesseractEngine implements OcrEngine
{
    /** okuma => [büyütme, gri tonlama] */
    private const PASSES = [[2, true], [3, true], [3, false]];

    /** Büyütülen görselin uzun kenarı bunu aşmaz (büyük ekran görüntüleri gereksiz yavaşlamasın). */
    private const MAX_SIDE = 4200;

    /** @var array<string, string> özgün yol#okuma => ön işlenmiş geçici dosya */
    private array $prepared = [];

    /** @var array<string, float> özgün yol#okuma => uygulanan büyütme */
    private array $scales = [];

    public function available(): bool
    {
        static $ok = null;

        return $ok ??= Process::timeout(10)->run([config('catalog.tesseract'), '--version'])->successful();
    }

    public function passes(): int
    {
        return count(self::PASSES);
    }

    public function scale(string $imagePath): float
    {
        $this->prepare($imagePath, 0);

        return $this->scales[$imagePath.'#0'];
    }

    public function languages(): array
    {
        static $list = null;
        if ($list === null) {
            $out = $this->available() ? Process::timeout(10)->run([config('catalog.tesseract'), ...$this->dataDir(), '--list-langs'])->output() : '';
            $lines = array_slice(preg_split('/\r?\n/', trim($out)), 1); // ilk satır başlık
            $list = array_values(array_filter(array_map('trim', $lines)));
        }

        return $list;
    }

    public function words(string $imagePath, int $pass = 0, ?string $languages = null): array
    {
        $tsv = $this->run([$this->prepare($imagePath, $pass), 'stdout', '-l', $languages ?? config('catalog.ocr_languages'), '--psm', '6', '--oem', '1',
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
        $src = imagecreatefrompng($this->prepare($imagePath, 0));
        $pad = 6;
        $crop = imagecrop($src, ['x' => max(0, $x - $pad), 'y' => max(0, $y - $pad), 'width' => $w + 2 * $pad, 'height' => $h + 2 * $pad]);
        if ($crop === false) {
            return '';
        }
        $tmp = tempnam(sys_get_temp_dir(), 'ocrl').'.png';
        imagepng($crop, $tmp);
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

    public function text(string $imagePath, ?string $languages = null): string
    {
        // Kapak/künye yazısı büyük ve renkli zeminli: ön işlem yerine özgün görsel, otomatik düzen (--psm 3).
        return $this->run([$imagePath, 'stdout', '-l', $languages ?? config('catalog.ocr_languages'), '--psm', '3', '--oem', '1']);
    }

    public function __destruct()
    {
        foreach ($this->prepared as $file) {
            @unlink($file);
        }
    }

    /** @return list<string> */
    private function dataDir(): array
    {
        $dir = config('catalog.tessdata');

        return $dir && is_dir($dir) ? ['--tessdata-dir', $dir] : [];
    }

    private function run(array $args): string
    {
        $result = Process::timeout(config('catalog.ocr_timeout'))->env(['OMP_THREAD_LIMIT' => '1'])
            ->run([config('catalog.tesseract'), ...$this->dataDir(), ...$args]);
        if (! $result->successful()) {
            throw new RuntimeException('OCR çalışmadı: '.mb_substr(trim($result->errorOutput()), 0, 200));
        }

        return $result->output();
    }

    /** Örneklenen piksellerin ortalama parlaklığı düşükse görsel koyu zeminlidir (gri tonlamalı görsel beklenir). */
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

    /** Büyütme + (gri tonlama) + (koyu zeminde ters çevirme); PNG olarak geçici dosyaya yazılır. */
    private function prepare(string $path, int $pass): string
    {
        $cacheKey = $path.'#'.$pass;
        if (isset($this->prepared[$cacheKey])) {
            return $this->prepared[$cacheKey];
        }
        [$scale, $gray] = self::PASSES[$pass] ?? self::PASSES[0];
        $data = @file_get_contents($path);
        $img = $data !== false ? @imagecreatefromstring($data) : false;
        if ($img === false) {
            throw new RuntimeException('Bilgi görseli açılamadı.');
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $scale = max(1.0, min((float) $scale, self::MAX_SIDE / max($w, $h)));
        [$bw, $bh] = [(int) round($w * $scale), (int) round($h * $scale)];
        $big = imagecreatetruecolor($bw, $bh);
        imagefill($big, 0, 0, imagecolorallocate($big, 255, 255, 255));
        imagecopyresampled($big, $img, 0, 0, 0, 0, $bw, $bh, $w, $h);

        $probe = $big;
        if (! $gray) {
            $probe = imagecreatetruecolor($bw, $bh);
            imagecopy($probe, $big, 0, 0, 0, 0, $bw, $bh);
        }
        imagefilter($probe, IMG_FILTER_GRAYSCALE);
        if ($this->isDark($probe)) {
            imagefilter($big, IMG_FILTER_NEGATE); // koyu temalı ekran görüntüsü: Tesseract açık zeminde koyu yazıyı daha iyi okur
        }
        // Kontrast artırma ve keskinleştirme kullanılmaz: gerçek denemede "ğ, ç, Ü" işaretlerini silip "Bogazici" okutuyordu.
        $tmp = tempnam(sys_get_temp_dir(), 'ocr').'.png';
        imagepng($big, $tmp);
        $this->scales[$cacheKey] = $scale;

        return $this->prepared[$cacheKey] = $tmp;
    }
}
