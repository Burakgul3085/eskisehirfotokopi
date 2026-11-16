<?php

use App\Support\Settings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Header'daki kategori bağlantıları kalktı (kategoriler yalnız "Tüm kategoriler" panelinde); yerine genel bağlantılar.
 * Menü bağlantıları yönetimde hiç değiştirilmediyse (eski varsayılan) yeni varsayılana geçilir; değiştirildiyse dokunulmaz.
 */
return new class extends Migration
{
    private const OLD = "Yayınevleri|/yayinevleri\nBelge Yükle|/fotokopi\nSipariş Takibi|/siparis-takip";

    public function up(): void
    {
        $row = DB::table('settings')->where('key', 'header_links')->first();
        if ($row && str_replace("\r\n", "\n", trim((string) $row->value)) === self::OLD) {
            DB::table('settings')->where('key', 'header_links')->update(['value' => Settings::DEFAULTS['header_links']]);
        }
        Settings::put([]); // önbelleği temizler
    }

    public function down(): void {}
};
