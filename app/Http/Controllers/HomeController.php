<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/** Ana sayfa. Yönetimden düzenlenen bloklar Aşama 4'te eklenir; şimdilik vitrin iskeleti gösterilir. */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('store.home');
    }
}
