<?php

use App\Domain\Catalog\CategoryPlan;
use App\Domain\Catalog\CategoryTree;
use Illuminate\Database\Migrations\Migration;

/** Üçüncü ana grup: Diğer Kitaplar (Sözlük, Atlas, Tıp, Hobi, Kırtasiye…). install() yalnız eksikleri ekler. */
return new class extends Migration
{
    public function up(): void
    {
        CategoryPlan::install(app(CategoryTree::class));
    }

    public function down(): void {}
};
