<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\View\View;

/** Kurumsal / yasal sayfa gösterimi (/sayfa/{slug}). */
class PageController extends Controller
{
    public function __invoke(Page $page): View
    {
        abort_unless($page->is_active || auth('admin')->check(), 404);

        return view('store.page', ['page' => $page]);
    }
}
