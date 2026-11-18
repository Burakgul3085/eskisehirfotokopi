<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ürün değerlendirmeleri (yalnız ürünü teslim almış üyeler; yönetici onayıyla yayın), fotoğrafları ve ürün soruları
 * (herkes sorabilir; cevaplanan ve yayınlanması seçilen sorular ürün sayfasında görünür).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            // Müşterinin son gönderdiği hâli
            $table->unsignedTinyInteger('rating');
            $table->string('title', 120)->nullable();
            $table->text('body');
            // pending: onay bekliyor (yayındaki bir değerlendirmenin düzenlemesi de olabilir), approved, rejected
            $table->string('status', 16)->default('pending')->index();
            // Yayındaki hâli: düzenleme onaylanana kadar sayfada eski hâl kalır
            $table->unsignedTinyInteger('published_rating')->nullable();
            $table->string('published_title', 120)->nullable();
            $table->text('published_body')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->string('reject_reason', 300)->nullable();
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->foreignId('reply_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'user_id']);
        });

        Schema::create('review_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('product_reviews')->cascadeOnDelete();
            $table->string('path');        // public diskte, EXIF'siz webp (aynı adın -sm sürümü küçük görsel)
            $table->unsignedSmallInteger('width')->default(0);
            $table->unsignedSmallInteger('height')->default(0);
            $table->boolean('is_approved')->default(false);
            $table->unsignedTinyInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('product_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('email', 190)->index();
            $table->text('body');
            $table->text('answer')->nullable();
            $table->timestamp('answered_at')->nullable()->index();
            $table->foreignId('answered_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->boolean('is_public')->default(false);
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('rating_avg', 2, 1)->nullable();
            $table->unsignedInteger('rating_count')->default(0);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('review_invited_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('review_invited_at'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['rating_avg', 'rating_count']));
        Schema::dropIfExists('product_questions');
        Schema::dropIfExists('review_photos');
        Schema::dropIfExists('product_reviews');
    }
};
