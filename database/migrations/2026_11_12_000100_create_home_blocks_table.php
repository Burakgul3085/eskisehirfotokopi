<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ana sayfa blokları (docs/01 §4). Sıralanabilir, açılıp kapatılabilir; içerik ve ayarlar `settings` (JSON) alanında.
 * Kurulumda örnek bir düzen eklenir; yönetimden değiştirilir veya silinir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->string('title', 160)->nullable();
            $table->string('subtitle', 255)->nullable();
            $table->json('settings')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'position']);
        });

        $now = now();
        $blocks = [
            ['hero', null, null, ['heading' => 'Kitap, soru bankası ve belge baskısı kapınıza gelsin', 'text' => 'Aradığınız kitabı saniyeler içinde bulun, siparişinizi verin; ödemeyi kapıda nakit veya kartla yapın.', 'badge' => 'Eskişehir içi kapıya teslim', 'button_label' => 'Belge yükle', 'button_url' => '/fotokopi']],
            ['categories', 'Kategoriler', 'Aradığınız sınava ve derse göre göz atın', ['category_ids' => [], 'limit' => 12]],
            ['products', 'Yeni eklenenler', null, ['source' => 'newest', 'limit' => 12]],
            ['publishers', 'Yayınevleri', 'Tüm yayınevlerinin kitapları tek yerde', ['limit' => 18]],
            ['print_banner', 'Belgenizi yükleyin, basıp kapınıza getirelim', 'PDF, Word veya görsel; renkli ya da siyah-beyaz, tek veya çift yüz. Fiyat yüklerken hesaplanır.', ['button_label' => 'Belge yükle', 'button_url' => '/fotokopi']],
            ['products', 'Çok satanlar', null, ['source' => 'bestsellers', 'limit' => 12]],
            ['faq', 'Sıkça sorulan sorular', null, ['items' => [
                ['q' => 'Ödemeyi nasıl yapıyorum?', 'a' => 'Siparişiniz teslim edilirken kapıda nakit veya kredi kartıyla ödersiniz.'],
                ['q' => 'Hangi bölgelere teslimat var?', 'a' => 'Eskişehir içinde sipariş sırasında listelenen ilçe ve mahallelere teslim ediyoruz.'],
                ['q' => 'Üye olmadan sipariş verebilir miyim?', 'a' => 'Evet. Sipariş numaranız ve telefonunuzla siparişinizi takip edebilirsiniz.'],
            ]]],
        ];
        foreach ($blocks as $i => [$type, $title, $subtitle, $settings]) {
            DB::table('home_blocks')->insert([
                'type' => $type, 'title' => $title, 'subtitle' => $subtitle, 'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
                'position' => $i + 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('home_blocks');
    }
};
