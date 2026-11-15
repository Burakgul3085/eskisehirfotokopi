<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kısa kategori düzeni (en fazla iki kat: "Okuma Kitapları › Tarih", "Sınav Hazırlık › TYT"):
 *  - products.book_type / subject: kitap türü (Soru Bankası, Deneme…) ve ders (Matematik…) kategori değil, süzgeçtir
 *  - products.topics: "Türü" yolundaki ayrıntılı adlar (Parçacık Fiziği, Nörobilim) konu etiketi olarak
 *  - category_aliases: okunan ad → kategori eşlemesi (kontrolde bir kez seçilen eşleme sonra kendiliğinden kullanılır)
 *  - import_batches.default_category_id: yüklemede seçilen varsayılan kategori (eşlenemeyen kitaplar için)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('book_type', 40)->nullable()->after('category_id')->index();
            $table->string('subject', 60)->nullable()->after('book_type')->index();
            $table->json('topics')->nullable()->after('subject');
        });
        Schema::create('category_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('match_key', 190)->unique();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
        Schema::table('import_batches', function (Blueprint $table) {
            $table->foreignId('default_category_id')->nullable()->constrained('categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_category_id');
        });
        Schema::dropIfExists('category_aliases');
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['book_type', 'subject', 'topics']);
        });
    }
};
