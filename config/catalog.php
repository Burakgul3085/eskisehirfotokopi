<?php

return [
    // Tesseract OCR (Ubuntu: apt install tesseract-ocr tesseract-ocr-tur). Ücretsiz, yerel; API kullanılmaz (K10).
    'tesseract' => env('OCR_TESSERACT', 'tesseract'),
    'ocr_languages' => env('OCR_LANGUAGES', 'tur+eng'),
    'ocr_timeout' => (int) env('OCR_TIMEOUT', 60),

    // Toplu yükleme
    'import_queue' => env('IMPORT_QUEUE', 'import'),
    'import_max_mb' => (int) env('IMPORT_MAX_MB', 15),          // tek dosya (mockup ≤ 10 MB, K9)
    'import_keep_days' => (int) env('IMPORT_KEEP_DAYS', 30),    // bilgi görselleri kontrol için bu kadar saklanır
];
