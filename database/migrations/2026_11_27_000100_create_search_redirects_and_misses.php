<?php

use App\Support\Text;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Site araması:
 *  - search_redirects: yönetimden tanımlanan "kelime → adres" yönlendirmeleri (ör. kargo → sipariş takibi)
 *  - search_misses: hiçbir sonuç vermeyen aramalar (yönetimde rapor; ne eklenmesi gerektiğini gösterir)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('term', 100);
            $table->string('match_key', 100)->unique(); // Text::key(term)
            $table->string('url', 500);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamps();
        });

        Schema::create('search_misses', function (Blueprint $table) {
            $table->id();
            $table->string('term', 100);
            $table->string('match_key', 100)->unique();
            $table->unsignedInteger('hits')->default(1);
            $table->timestamp('last_searched_at')->nullable()->index();
            $table->timestamps();
        });

        // Başlangıç yönlendirmeleri: sayfa adı olmayan ama sık aranan kelimeler
        $now = now();
        foreach ([
            'kargo' => '/siparis-takip', 'siparişim nerede' => '/siparis-takip',
            'telefon' => '/iletisim', 'adres' => '/iletisim', 'whatsapp' => '/iletisim', 'harita' => '/iletisim',
            'fotokopi' => '/fotokopi', 'çıktı' => '/fotokopi', 'belge' => '/fotokopi',
        ] as $term => $url) {
            DB::table('search_redirects')->insert(['term' => $term, 'match_key' => Text::key($term), 'url' => $url, 'hits' => 0, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('search_misses');
        Schema::dropIfExists('search_redirects');
    }
};
