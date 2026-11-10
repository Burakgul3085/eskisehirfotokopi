<?php

/*
| Fotokopi modülü: sunucudaki belge araçları. Ubuntu'da: apt install libreoffice-writer libreoffice-calc
| libreoffice-impress poppler-utils. Windows'ta yol .env'den verilir (ör. PRINT_SOFFICE="C:\Program Files\LibreOffice\program\soffice.exe").
*/
return [
    'soffice' => env('PRINT_SOFFICE', 'soffice'),
    'pdfinfo' => env('PRINT_PDFINFO', 'pdfinfo'),
    'pdftoppm' => env('PRINT_PDFTOPPM', 'pdftoppm'),
    'convert_timeout' => (int) env('PRINT_CONVERT_TIMEOUT', 300),
    'chunk_bytes' => 8 * 1024 * 1024, // tarayıcı dosyayı bu boyutta parçalarla yükler
];
