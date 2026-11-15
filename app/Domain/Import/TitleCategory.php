<?php

namespace App\Domain\Import;

use App\Domain\Catalog\CategoryTree;
use App\Support\Text;

/**
 * Kitap adından (ve klasör adından) sınav, sınıf düzeyi, kitap türü ve ders:
 *   "345 TYT Matematik Soru Bankası 2024"  → sınav TYT, tür Soru Bankası, ders Matematik
 *   "Matematik AYT Konu Anlatımı"          → sınav AYT (adın neresinde olursa olsun)
 *   "TYT MAT"                              → sınav TYT, ders Matematik (kısaltmalar)
 *   "10. Sınıf Fizik Konu Anlatımı"        → düzey Ara Sınıflar, ders Fizik
 * Karşılaştırma Text::key ile yapılır: büyük/küçük harf, Türkçe karakter ve noktalama farkı yok sayılır.
 */
class TitleCategory
{
    /** düzenli ifade (Text::key biçiminde) => tür adı; adda en önce geçen seçilir */
    private const TYPES = [
        '/\bsoru bankasi\b/' => 'Soru Bankası',
        '/\bkonu anlatim/' => 'Konu Anlatımı',
        '/\btam tekrar\b/' => 'Tam Tekrar',
        '/\byaprak test/' => 'Yaprak Test',
        '/\bcikmis soru/' => 'Çıkmış Sorular',
        '/\bfasikul/' => 'Fasikül',
        '/\bfoy(u|ler|leri)?\b/' => 'Föy',
        '/\bdeneme(si|ler|leri)?\b/' => 'Deneme',
        '/\bdefter(i|ler|leri)?\b/' => 'Defter',
        '/\btest(i|ler|leri)?\b/' => 'Test',
    ];

    /** Okuma kitabı adlarında da geçen türler ("Deneme Yazıları", "Leonardo'nun Defterleri", "Kişilik Testi") */
    public const WEAK_TYPES = ['Deneme', 'Defter', 'Test'];

    /** Text::key biçiminde sınav adı => görünen ad */
    private const EXAMS = [
        'tyt' => 'TYT', 'ayt' => 'AYT', 'yks' => 'YKS', 'ydt' => 'YDT', 'yds' => 'YDS', 'yokdil' => 'YÖKDİL',
        'kpss' => 'KPSS', 'ekpss' => 'EKPSS', 'ales' => 'ALES', 'dgs' => 'DGS', 'lgs' => 'LGS', 'msu' => 'MSÜ',
        'oabt' => 'ÖABT', 'pmyo' => 'PMYO', 'pomem' => 'POMEM', 'tus' => 'TUS', 'dus' => 'DUS', 'yos' => 'YÖS',
    ];

    /** Ders sözlüğü: Text::key biçiminde ifade => ders adı (uzun ifadeler önce denenir) */
    private const SUBJECTS = [
        'turk dili ve edebiyati' => 'Türk Dili ve Edebiyatı', 'inkilap tarihi' => 'İnkılap Tarihi', 'din kulturu' => 'Din Kültürü',
        'fen bilimleri' => 'Fen Bilimleri', 'sosyal bilgiler' => 'Sosyal Bilgiler', 'dil bilgisi' => 'Dil Bilgisi', 'dilbilgisi' => 'Dil Bilgisi',
        'esit agirlik' => 'Eşit Ağırlık', 'genel yetenek' => 'Genel Yetenek', 'genel kultur' => 'Genel Kültür',
        'guncel bilgiler' => 'Güncel Bilgiler', 'egitim bilimleri' => 'Eğitim Bilimleri', 'sayisal mantik' => 'Sayısal Mantık',
        'matematik' => 'Matematik', 'geometri' => 'Geometri', 'fizik' => 'Fizik', 'kimya' => 'Kimya', 'biyoloji' => 'Biyoloji',
        'turkce' => 'Türkçe', 'paragraf' => 'Paragraf', 'problemler' => 'Problemler', 'edebiyat' => 'Edebiyat', 'tarih' => 'Tarih',
        'cografya' => 'Coğrafya', 'felsefe' => 'Felsefe', 'ingilizce' => 'İngilizce', 'almanca' => 'Almanca',
        'sayisal' => 'Sayısal', 'sozel' => 'Sözel', 'vatandaslik' => 'Vatandaşlık', 'mantik' => 'Mantık',
    ];

    /** Kısaltmalar: yalnız sınav/düzey/tür varsa ders sayılır ("Kim Korkar" bir okuma kitabıdır) */
    private const ABBREVIATIONS = [
        'mat' => 'Matematik', 'geo' => 'Geometri', 'fiz' => 'Fizik', 'kim' => 'Kimya', 'biyo' => 'Biyoloji',
        'cog' => 'Coğrafya', 'ing' => 'İngilizce', 'edb' => 'Edebiyat', 'trk' => 'Türkçe',
    ];

    /** Ders adından atılan nitelik kelimeleri (sözlükte olmayan ders için) */
    private const MODIFIERS = ['konu', 'ozetli', 'video', 'destekli', 'cozumlu', 'yeni', 'nesil', 've', 'akilli', 'kazanim', 'odakli'];

    /** Sınıf düzeyi → kategori adı (Sınav Hazırlık altında) */
    public const GRADES = ['Ara Sınıflar' => '9|10|11|12', 'Ortaokul' => '5|6|7|8', 'İlkokul' => '1|2|3|4'];

    /** Tanınan kitap türleri (yönetim formundaki öneri listesi). @return list<string> */
    public static function types(): array
    {
        return array_values(array_unique(self::TYPES));
    }

    /** Eski biçim (Tür › Sınav › Ders). @return list<string> */
    public static function guess(?string $title): array
    {
        $p = self::parse($title);

        return array_values(array_filter([$p['type'], $p['exam'], $p['subject']]));
    }

    /**
     * Kitap adından tür, sınav, sınıf düzeyi ve ders. Sınav ve düzey ek metinde (klasör adı) de aranır.
     * Zayıf türler ("Deneme", "Defter", "Test") yalnız sınav/düzey varsa tür sayılır; sınavsız adda kararı
     * CategoryMapper verir (rawType()).
     *
     * @return array{type: ?string, exam: ?string, grade: ?string, subject: ?string}
     */
    public static function parse(?string $title, ?string $extra = null): array
    {
        $none = ['type' => null, 'exam' => null, 'grade' => null, 'subject' => null];
        $key = Text::key(preg_replace('/\b(19|20)\d{2}(\s*-\s*(19|20)?\d{2})?\b/u', ' ', (string) $title));
        if ($key === '') {
            return $none;
        }
        $both = trim($key.' '.Text::key((string) $extra));
        $exam = self::exam($both);
        $grade = self::grade($both);
        $type = self::rawType($key);
        if (in_array($type, self::WEAK_TYPES, true) && $exam === null && $grade === null) {
            $type = null;
        }
        if ($type === null && $exam === null && $grade === null) {
            return $none;
        }

        return ['type' => $type, 'exam' => $exam, 'grade' => $grade, 'subject' => self::subject((string) $title, true)];
    }

    /** Metinde geçen ilk sınav adı ("TYT-AYT" → TYT). */
    public static function exam(string $key): ?string
    {
        foreach (explode(' ', Text::key($key)) as $w) {
            if (isset(self::EXAMS[$w])) {
                return self::EXAMS[$w];
            }
        }

        return null;
    }

    /** "10. Sınıf", "9.sınıf", "Ara Sınıf" → düzey kategorisi. */
    public static function grade(string $key): ?string
    {
        $key = Text::key($key);
        if (preg_match('/\b(1[0-2]|[1-9]) ?sinif\b/', $key, $m)) {
            foreach (self::GRADES as $name => $list) {
                if (in_array($m[1], explode('|', $list), true)) {
                    return $name;
                }
            }
        }

        return preg_match('/\bara sinif/', $key) ? 'Ara Sınıflar' : null;
    }

    /** Adda en önce geçen tür (zayıf türler dahil). */
    public static function rawType(?string $title): ?string
    {
        $key = Text::key((string) $title);
        $type = null;
        $at = null;
        foreach (self::TYPES as $re => $name) {
            if (preg_match($re, $key, $m, PREG_OFFSET_CAPTURE) && ($at === null || $m[0][1] < $at)) {
                [$type, $at] = [$name, $m[0][1]];
            }
        }

        return $type;
    }

    /**
     * Ders: önce sözlük ("Matematik", "Dil Bilgisi"; $abbrev ile "MAT", "GEO"); yoksa sınav/düzeyden sonra,
     * türden önceki kelimeler ("TYT Mantık Soru Bankası" → Mantık).
     */
    public static function subject(string $title, bool $abbrev = false): ?string
    {
        $key = ' '.Text::key($title).' ';
        $best = null;
        $bestAt = null;
        $dict = $abbrev ? self::SUBJECTS + self::ABBREVIATIONS : self::SUBJECTS;
        foreach ($dict as $needle => $name) {
            $at = strpos($key, ' '.$needle.' ');
            if ($at !== false && ($bestAt === null || $at < $bestAt)) {
                [$best, $bestAt] = [$name, $at];
            }
        }
        if ($best) {
            return $best;
        }

        // Sözlükte yok: sınav adından/sınıf düzeyinden hemen sonra, türden önce gelen kelimeler (özgün yazımla)
        $words = array_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u', $title), fn ($w) => $w !== ''));
        $out = [];
        $started = false;
        foreach ($words as $w) {
            $k = Text::key($w);
            if (! $started) {
                $started = isset(self::EXAMS[$k]) || $k === 'sinif';

                continue;
            }
            if (preg_match('/^\d+$/', $k) || in_array($k, self::MODIFIERS, true)) {
                if ($out) {
                    break;
                }

                continue;
            }
            if (in_array($k, ['soru', 'konu', 'tam', 'yaprak', 'cikmis'], true) || self::rawType($w) !== null) {
                break;
            }
            $out[] = $w;
        }

        return $out && count($out) <= 3 ? CategoryTree::displayName(implode(' ', $out)) : null;
    }
}
