<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ürün bilgilerinin müşteriye gösterimi ve standart dışı bilgiler:
 *  - products.spec_visibility: {"isbn": false, "ek:cevirmen": true} — ürüne özel göster/gizle (yoksa genel ayar geçerli)
 *  - products.extra_specs: [{"label": "Çevirmen", "value": "…"}] — bilgi tablosundaki tanınmayan satırlar, elle eklenenler
 *  - import_batches.checked_categories: yüklemede açılan ve yöneticinin "doğru" dediği kategoriler
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('spec_visibility')->nullable()->after('description');
            $table->json('extra_specs')->nullable()->after('spec_visibility');
        });
        Schema::table('import_batches', function (Blueprint $table) {
            $table->json('checked_categories')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['spec_visibility', 'extra_specs']);
        });
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropColumn('checked_categories');
        });
    }
};
