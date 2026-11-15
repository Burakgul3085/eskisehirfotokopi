<?php

use App\Domain\Catalog\CategoryPlan;
use App\Domain\Catalog\CategoryTree;
use Illuminate\Database\Migrations\Migration;

/**
 * Kısa kategori düzenini (Okuma Kitapları / Sınav Hazırlık ve altları) kurar; var olanlara dokunmaz.
 * Eski derin ağaçtaki ürünler ayrıca `php artisan kategori:sadelestir` ile taşınır.
 */
return new class extends Migration
{
    public function up(): void
    {
        CategoryPlan::install(app(CategoryTree::class));
    }

    public function down(): void
    {
        // Kategoriler ürünlere bağlanmış olabilir; geri alınmaz.
    }
};
