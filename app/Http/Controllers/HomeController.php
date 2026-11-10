<?php

namespace App\Http\Controllers;

use App\Domain\Storefront\HomePage;
use Illuminate\View\View;

/** Ana sayfa: yönetimden sıralanan bloklar (Yönetim › Vitrin › Ana sayfa). */
class HomeController extends Controller
{
    public function __invoke(HomePage $home): View
    {
        return view('store.home', ['blocks' => $home->blocks()]);
    }
}
