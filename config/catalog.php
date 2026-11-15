<?php

return [
    // Tesseract OCR (Ubuntu: apt install tesseract-ocr tesseract-ocr-tur). Ücretsiz, yerel; API kullanılmaz (K10).
    'tesseract' => env('OCR_TESSERACT', 'tesseract'),
    'ocr_languages' => env('OCR_LANGUAGES', 'tur+eng'),
    // Kitap başka dildeyse ilk okumadan dil tanınır ve o dil eklenerek yeniden okunur (yalnız kurulu olanlar).
    // Sunucu: apt install tesseract-ocr-deu tesseract-ocr-ita tesseract-ocr-fra tesseract-ocr-spa
    'ocr_extra_languages' => env('OCR_EXTRA_LANGUAGES', 'deu,ita,fra,spa'),
    // En doğru sonuç için tessdata_best dil dosyalarının klasörü (github.com/tesseract-ocr/tessdata_best); boşsa varsayılan
    'tessdata' => env('OCR_TESSDATA'),
    'ocr_timeout' => (int) env('OCR_TIMEOUT', 60),

    // Toplu yükleme
    'import_queue' => env('IMPORT_QUEUE', 'import'),
    'import_max_mb' => (int) env('IMPORT_MAX_MB', 15),          // tek dosya (mockup ≤ 10 MB, K9)
    'import_keep_days' => (int) env('IMPORT_KEEP_DAYS', 30),    // bilgi görselleri kontrol için bu kadar saklanır
];
