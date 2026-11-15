<?php

namespace App\Domain\Import;

/**
 * Kitabın Türkçe dışındaki dilini ilk OCR metninden (ve klasör adından) tahmin eder: Almanca, İtalyanca, Fransızca,
 * İspanyolca, İngilizce. Sık geçen kısa kelimeler ve dile özgü harfler (ß, ä, ñ, è…) sayılır. Bilgi tablosunun
 * etiketleri Türkçe olduğundan Türkçe her zaman okunur; burada yalnız eklenecek ikinci dil aranır.
 */
final class LanguageGuess
{
    // Türkçede de kelime olan ya da OCR artığı olabilen kısa kelimeler (il, en, el, al, de, y…) bilerek yok.
    private const WORDS = [
        'deu' => ['der', 'die', 'das', 'und', 'ist', 'nicht', 'mit', 'von', 'zu', 'den', 'für', 'ein', 'eine', 'auf', 'im', 'des', 'dem', 'sich', 'auch', 'über', 'aus', 'wie', 'bei', 'geschichte'],
        'ita' => ['lo', 'gli', 'di', 'che', 'per', 'non', 'una', 'con', 'del', 'della', 'delle', 'dei', 'nel', 'nella', 'alla', 'sono', 'come', 'più', 'storia'],
        'fra' => ['les', 'des', 'et', 'est', 'une', 'pour', 'dans', 'du', 'que', 'qui', 'pas', 'sur', 'aux', 'avec', 'cette', 'histoire'],
        'spa' => ['los', 'las', 'que', 'del', 'por', 'una', 'con', 'para', 'como', 'más', 'sus', 'historia'],
        'eng' => ['the', 'and', 'of', 'to', 'is', 'for', 'with', 'on', 'from', 'by', 'history', 'how', 'what', 'why', 'introduction'],
    ];

    /** Dile özgü harfler (Türkçede olmayan; â î û Türkçede de var): her biri 2 puan */
    private const LETTERS = [
        'deu' => '/[ßäÄ]/u',
        'ita' => '/[àèìòùÀÈÌÒÙ]/u',
        'fra' => '/[éêëèàïœÉÊÈŒ]/u',
        'spa' => '/[ñÑ¿¡áéíóú]/u',
        'eng' => '/(?!)/',
    ];

    /** @return ?string dil kodu (deu, ita, fra, spa, eng) veya belirgin değilse null */
    public static function guess(string $text): ?string
    {
        $words = preg_split('/[^\p{L}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        $scores = [];
        foreach (self::WORDS as $lang => $list) {
            $hits = array_filter($words, fn ($w) => in_array($w, $list, true));
            // Tek bir kelimenin tekrarı (OCR gürültüsü) yetmez: en az iki farklı kelime gerekir
            $scores[$lang] = (count(array_unique($hits)) >= 2 ? count($hits) : 0) + 2 * preg_match_all(self::LETTERS[$lang], $text);
        }
        arsort($scores);
        $best = array_key_first($scores);
        $second = array_values($scores)[1] ?? 0;

        // En az 3 puan ve ikinciden belirgin fark: tek bir "de", "il" yüzünden dil değişmez
        return $scores[$best] >= 3 && $scores[$best] >= $second + 2 ? $best : null;
    }
}
