<?php

namespace App\Http\Controllers\Store;

use App\Domain\Delivery\DeliveryRules;
use App\Http\Controllers\Controller;
use App\Models\DeliveryDistrict;
use App\Models\Page;
use App\Models\Product;
use App\Models\Publisher;
use Illuminate\View\View;

/**
 * Kurumsal / yasal sayfa gösterimi (/sayfa/{slug}). Sayfanın türüne göre görünüm: Hakkımızda tanıtımı, SSS akordeonu,
 * yardım sayfası (yan menülü) veya içindekiler listeli yasal metin. Yan menüler aynı türdeki diğer sayfaları listeler.
 */
class PageController extends Controller
{
    public function __invoke(Page $page, DeliveryRules $rules): View
    {
        abort_unless($page->is_active || auth('admin')->check(), 404);

        $siblings = Page::active()->orderBy('position')->orderBy('title')->get(['id', 'key', 'title', 'slug', 'footer_column']);
        $layout = $page->layout();

        $data = [
            'page' => $page,
            'legalLinks' => $siblings->filter(fn (Page $p) => $p->layout() === 'legal')->values(),
            'helpLinks' => $siblings->filter(fn (Page $p) => in_array($p->layout(), ['help', 'faq'], true))->values(),
        ];

        if ($layout === 'about') {
            $data['stats'] = [
                'products' => Product::visible()->count(),
                'publishers' => Publisher::active()->whereHas('products', fn ($q) => $q->visible())->count(),
                'districts' => DeliveryDistrict::where('is_active', true)->count(),
            ];
        }

        // Teslimat sayfası: güncel kurallardan özet kartlar
        if ($page->key === 'delivery') {
            $districts = DeliveryDistrict::where('is_active', true)->get(['fee']);
            $data['delivery'] = [
                'districts' => $districts->count(),
                'fee_min' => $districts->min('fee'),
                'fee_max' => $districts->max('fee'),
                'min_order' => $rules->minOrder(),
                'free_threshold' => $rules->freeThreshold(),
                'slots' => $rules->slots(),
                'payments' => array_values($rules->paymentMethods()),
            ];
        }

        return view('store.pages.'.$layout, $data);
    }
}
