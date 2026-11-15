<?php

use App\Domain\Catalog\CategoryPlan;
use App\Domain\Catalog\CategoryTree;
use Illuminate\Database\Migrations\Migration;

/** Sınav Hazırlık altına Deneme Kitapları ve Konu Özetli Defterler; yeni eşleme adları (dram, deneme yazıları…). */
return new class extends Migration
{
    public function up(): void
    {
        CategoryPlan::install(app(CategoryTree::class));
    }

    public function down(): void {}
};
