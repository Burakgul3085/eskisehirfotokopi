<?php

namespace Tests;

use App\Domain\Catalog\CategoryTree;
use App\Models\Category;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Kategori ağacının genel davranışını sınayan testler için migration'ın kurduğu başlangıç düzenini kaldırır. */
    protected function withoutStarterCategories(): void
    {
        Category::query()->orderByDesc('depth')->get()->each->delete();
        app(CategoryTree::class)->flush();
    }
}
