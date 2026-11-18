<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Üstteki kayan duyuru şeridinden "WhatsApp ile | HIZLI DESTEK" satırı kaldırılır
 * (WhatsApp desteği header'daki butonda; numara girilmezse o da gizlenir).
 */
return new class extends Migration
{
    public function up(): void
    {
        $row = DB::table('settings')->where('key', 'topbar_items')->first();
        if (! $row || $row->value === null) {
            return;
        }
        $lines = array_filter(preg_split('/\R/u', $row->value), fn ($l) => ! str_starts_with(trim($l), 'whatsapp|'));
        DB::table('settings')->where('key', 'topbar_items')->update(['value' => implode("\n", $lines) ?: null]);
        Cache::forget('settings.saved'); // Settings::CACHE_KEY gizli sabit
    }

    public function down(): void
    {
        // Geri alınmaz.
    }
};
