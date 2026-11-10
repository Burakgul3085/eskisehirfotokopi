<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Önlü arkalı 2'ye 2 (yüze 2 sayfa) fiyatları; price_bw / price_color 1'e 1 fiyatıdır. Hepsi sayfa sayısından hesaplanır. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price_bw_2up', 10, 2)->nullable()->after('price_color');
            $table->decimal('price_color_2up', 10, 2)->nullable()->after('price_bw_2up');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['price_bw_2up', 'price_color_2up']);
        });
    }
};
