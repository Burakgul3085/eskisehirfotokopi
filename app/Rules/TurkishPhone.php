<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Türkiye telefon numarası (cep veya sabit, 10 hane). Kayıtta 05xxxxxxxxx biçimine çevrilir. */
class TurkishPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (self::normalize((string) $value) === null) {
            $fail('Geçerli bir telefon numarası girin (ör. 0532 123 45 67).');
        }
    }

    public static function normalize(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if (str_starts_with($digits, '90') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return preg_match('/^[2-5]\d{9}$/', $digits) ? '0'.$digits : null;
    }

    /** 0532 123 45 67 */
    public static function format(?string $phone): string
    {
        $p = self::normalize($phone);

        return $p ? substr($p, 0, 4).' '.substr($p, 4, 3).' '.substr($p, 7, 2).' '.substr($p, 9, 2) : (string) $phone;
    }
}
