<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Bilgi tablosu olmayan klasörler için kapak (yazar, özellikler) ve künye (baskı yılı, ISBN) sayfaları da okunur.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_items', function (Blueprint $table) {
            $table->string('cover_path')->nullable()->after('mockup_path');
            $table->string('imprint_path')->nullable()->after('cover_path');
        });
    }

    public function down(): void
    {
        Schema::table('import_items', function (Blueprint $table) {
            $table->dropColumn(['cover_path', 'imprint_path']);
        });
    }
};
