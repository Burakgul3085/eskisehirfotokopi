<?php

namespace App\Domain\Catalog;

use App\Support\Text;

/**
 * Kitabın sınav kitabı mı okuma kitabı mı olduğuna dair metin ipuçları (ad, alt başlık, klasör adı, kapak yazısı).
 * Her ipucu bir ağırlık ve okunabilir gerekçe taşır; CategoryMapper bunları yayınevi/yazar geçmişi ve benzer
 * kitaplarla toplayıp karar verir.
 */
final class BookSignals
{
    /** düzenli ifade (Text::key biçiminde) => [ağırlık, gerekçe] */
    private const EXAM = [
        '/\bsoru bankasi\b/' => [7, 'soru bankası'], '/\bkonu anlatim/' => [7, 'konu anlatımı'], '/\bkonu ozet/' => [7, 'konu özetli'],
        '/\byaprak test/' => [7, 'yaprak test'], '/\btam tekrar\b/' => [7, 'tam tekrar'], '/\bcikmis soru/' => [7, 'çıkmış sorular'],
        '/\bfasikul/' => [7, 'fasikül'], '/\bfoy(u|ler|leri)?\b/' => [7, 'föy'], '/\bkazanim/' => [6, 'kazanım'],
        '/\b(soru|test) kitabi\b/' => [7, 'test kitabı'], '/\bdeneme sinav/' => [7, 'deneme sınavı'], '/\bsinav(a)? hazirlik/' => [7, 'sınava hazırlık'],
        '/\b(akilli|ozetli) defter/' => [7, 'akıllı/özetli defter'], '/\bvideo (destekli|cozumlu)\b/' => [6, 'video çözümlü'],
        '/\byeni nesil\b/' => [5, 'yeni nesil soru'], '/\b\d+ (adet )?(deneme|test)\b/' => [7, 'deneme sayısı'],
        '/\b(matematik|geometri|kimya|biyoloji|paragraf|problemler|dil bilgisi|dilbilgisi|fen bilimleri|sosyal bilgiler|sayisal|sozel|esit agirlik|genel yetenek|genel kultur|vatandaslik|egitim bilimleri|turkce|inkilap tarihi|din kulturu)\b/' => [2, 'okul dersi'],
        '/\bdenemeleri\b/' => [3, 'denemeler'], '/\b(deneme|defter|test|soru|cozumlu|ozet)\b/' => [2, 'sınav kelimesi'],
    ];

    private const READING = [
        '/\broman(i|lari)?\b/' => [5, 'roman'], '/\b(oyku|oykuler|hikaye|hikayeler|oykusu)\b/' => [5, 'öykü'], '/\bsiir(ler|leri)?\b/' => [5, 'şiir'],
        '/\bdeneme yazilari\b/' => [6, 'deneme yazıları'], '/\bbutun (eserleri|siirleri|oykuleri)\b/' => [6, 'bütün eserleri'],
        '/\b(masal|masallar|masallari)\b/' => [4, 'masal'], '/\b(anilar|anilari|hatirat|otobiyografi|biyografi)\b/' => [4, 'anı/biyografi'],
        '/\bmektuplar(i)?\b/' => [3, 'mektuplar'], '/\bkisa tarihi\b/' => [4, 'kısa tarihi'], '/\bsecme yazilar\b/' => [4, 'seçme yazılar'],
        '/\bgirisi?\b|\bgiris\b/' => [2, 'giriş kitabı'], '/\buzerine\b/' => [2, 'üzerine'],
    ];

    /** @return array{0: float, 1: list<string>} sınav kitabı puanı ve gerekçeler */
    public static function exam(string $text): array
    {
        return self::score($text, self::EXAM);
    }

    /** @return array{0: float, 1: list<string>} okuma kitabı puanı ve gerekçeler */
    public static function reading(string $text): array
    {
        return self::score($text, self::READING);
    }

    /** Aynı ipucu bir kez sayılır; en güçlü üç ipucu toplanır (uzun adda puan şişmesin). */
    private static function score(string $text, array $rules): array
    {
        $key = Text::key($text);
        $hits = [];
        foreach ($rules as $re => [$weight, $why]) {
            if (preg_match($re, $key)) {
                $hits[$why] = max($hits[$why] ?? 0, $weight);
            }
        }
        arsort($hits);
        $hits = array_slice($hits, 0, 3, true);

        return [(float) array_sum($hits), array_keys($hits)];
    }
}
