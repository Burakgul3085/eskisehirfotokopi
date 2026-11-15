<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Kitabın alt başlığı ("Beyindeki Hayaletler — İnsan Zihninin Gizemlerine Doğru"); toplu yüklemede görselden okunur. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('subtitle', 255)->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('subtitle');
        });
    }
};
