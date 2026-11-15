<?php

namespace App\Support;

/**
 * "Bilgi yok" anlamındaki değerler: kaynak görsellerde boş alan yerine yazılan "Henüz doğrulanmadı", "Bulunamadı",
 * "Mevcut değil", "-" gibi ifadeler (OCR'nin bozduğu biçimleri dahil: "Henüz dogrulanm vt", "Bulunamad1").
 * Toplu yüklemede bu değerler ürüne yazılmaz; vitrinde de hiçbir zaman gösterilmez.
 */
final class EmptyValue
{
    /** Değerin tamamı bunlardan biriyse boştur (Text::key biçiminde). */
    private const EXACT = [
        'henuz dogrulanmadi', 'dogrulanmadi', 'bilinmiyor', 'yok', 'yoktur', 'belirtilmemis', 'belirtilmedi', 'n a', 'na', 'aciklama',
        'bulunamadi', 'bulunmuyor', 'bulunmamaktadir', 'mevcut degil', 'bilgi yok', 'bilgi bulunamadi', 'veri yok', 'kayit yok',
        'girilmemis', 'tanimsiz', 'okunamadi', 'tespit edilemedi', 'belirsiz', 'bos', 'none', 'unknown', 'not available',
        'not found', 'nicht verfugbar', 'unbekannt', 'non disponibile', 'sconosciuto', 'inconnu', 'non disponible',
        'desconocido', 'no disponible', 'null', 'nan', 'undefined',
    ];

    /** Değerin içinde geçerse boştur ("ISBN bulunamadı", "Bilgi mevcut değil"). */
    private const CONTAINS = [
        'dogrulanmad', 'bulunamad', 'bulunmuyor', 'bulunmamakta', 'mevcut degil', 'bilgi yok', 'belirtilmem', 'bilinmiyor',
        'tespit edilemed', 'okunamad', 'girilmemis', 'not available', 'not found',
    ];

    public static function is(?string $value): bool
    {
        if ($value === null) {
            return true;
        }
        // Yalnız noktalama/çizgi ("-", "—", "?", "...") boştur
        if (! preg_match('/[\p{L}\p{N}]/u', $value)) {
            return true;
        }
        $key = Text::key($value);
        if ($key === '' || in_array($key, self::EXACT, true) || str_starts_with($key, 'henuz ')) {
            return true;
        }
        foreach (self::CONTAINS as $part) {
            if (str_contains($key, $part)) {
                return true;
            }
        }
        // OCR bozulmaları: uzun ifadelere %70 benzerlik ("dagrulanmadi", "bulunamad1")
        foreach (self::EXACT as $empty) {
            if (strlen($empty) >= 8 && 1 - levenshtein($key, $empty) / max(strlen($key), strlen($empty)) >= 0.7) {
                return true;
            }
        }

        return false;
    }
}
