<?php

use App\Domain\Catalog\CategoryPlan;
use App\Domain\Catalog\CategoryTree;
use Illuminate\Database\Migrations\Migration;

/** Kısa düzene eklenen takma adlar (dini kitaplar, boyama, masal…): install() yalnız eksikleri ekler. */
return new class extends Migration
{
    public function up(): void
    {
        CategoryPlan::install(app(CategoryTree::class));
    }

    public function down(): void {}
};
