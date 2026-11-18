<?php

namespace App\Domain\Reviews;

use RuntimeException;

/** Değerlendirme fotoğrafı okunamadı ya da dönüştürülemedi. */
class PhotoProcessingFailed extends RuntimeException {}
