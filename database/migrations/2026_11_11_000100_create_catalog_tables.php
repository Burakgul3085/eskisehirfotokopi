<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Katalog: sınırsız derinlikte kategori ağacı (materialized path), yayınevleri (+ eşleştirme takma adları),
 * yazarlar ve ürünler. Her ürünün siyah-beyaz ve renkli iki fiyatı vardır (K3); fiyatı olmayan ürün satılmaz (K2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('slug', 190)->unique();
            $table->string('path', 255)->index();        // "1/5/9/" — kök→kendisi; alt ağaç sorgusu path LIKE '1/5/%'
            $table->unsignedTinyInteger('depth')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->string('normalized_name', 150)->index(); // içe aktarmada eşleştirme
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('icon', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('show_in_menu')->default(true);
            $table->string('meta_title', 70)->nullable();
            $table->string('meta_description', 170)->nullable();
            $table->timestamps();
            $table->unique(['parent_id', 'normalized_name']);
        });

        Schema::create('publishers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 190)->unique();
            $table->string('match_key', 150)->unique();  // "yayınları/yayınevi" ekleri atılmış normal ad
            $table->string('logo_path')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false); // ana sayfa logo şeridi
            $table->unsignedInteger('position')->default(0);
            $table->string('meta_title', 70)->nullable();
            $table->string('meta_description', 170)->nullable();
            $table->timestamps();
        });

        Schema::create('publisher_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publisher_id')->constrained()->cascadeOnDelete();
            $table->string('match_key', 150)->unique();
            $table->timestamps();
        });

        Schema::create('authors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 190)->unique();
            $table->string('match_key', 150)->unique();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->nullable()->unique();   // bilgi tablosundaki "Ürün Kodu"
            $table->string('isbn', 13)->nullable()->unique();
            $table->string('title', 255);
            $table->string('slug', 255)->unique();
            $table->foreignId('publisher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('page_count')->nullable();
            $table->unsignedSmallInteger('print_year')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price_bw', 10, 2)->nullable();
            $table->decimal('price_color', 10, 2)->nullable();
            $table->decimal('compare_bw', 10, 2)->nullable();    // üstü çizili eski fiyat
            $table->decimal('compare_color', 10, 2)->nullable();
            $table->string('status', 12)->default('draft');      // draft | active | passive
            $table->boolean('is_featured')->default(false);
            $table->string('image_key', 80)->nullable();         // products/{key}/{sm|md|lg}.webp
            $table->unsignedSmallInteger('image_width')->nullable();
            $table->unsignedSmallInteger('image_height')->nullable();
            $table->unsignedInteger('sales_count')->default(0);
            $table->unsignedInteger('view_count')->default(0);
            $table->text('search_text')->nullable();             // normalize edilmiş arama metni
            $table->string('meta_title', 70)->nullable();
            $table->string('meta_description', 170)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['status', 'category_id']);
            $table->index(['status', 'publisher_id']);
            $table->index(['status', 'sales_count']);
            $table->index('title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('authors');
        Schema::dropIfExists('publisher_aliases');
        Schema::dropIfExists('publishers');
        Schema::dropIfExists('categories');
    }
};
