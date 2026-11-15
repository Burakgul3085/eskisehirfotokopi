<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Yöneticinin toplu taşımada kaydettiği arama: bundan sonra yüklenen ve aramaya uyan kitaplar bu kategoriye gider.
        Schema::create('placement_rules', function (Blueprint $table) {
            $table->id();
            $table->json('conditions'); // q, subject, book_type, publisher_id, category_id
            $table->string('label', 500);
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->unsignedInteger('applied_count')->default(0);
            $table->timestamps();
        });

        // Toplu kategori taşımaları: "Geri al" ürünleri eski kategorilerine döndürür.
        Schema::create('category_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('placement_rules')->nullOnDelete();
            $table->unsignedBigInteger('rule_previous_category_id')->nullable(); // var olan kuralın önceki hedefi
            $table->string('target_name', 300);
            $table->json('moves'); // ürün id => eski kategori id
            $table->unsignedInteger('count');
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_moves');
        Schema::dropIfExists('placement_rules');
    }
};
