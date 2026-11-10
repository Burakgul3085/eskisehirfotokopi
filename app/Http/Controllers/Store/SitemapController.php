<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\Publisher;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/** sitemap.xml (6 saat önbellekli) ve robots.txt. 10 bin ürün tek dosyaya sığar (sınır 50 bin adres). */
class SitemapController extends Controller
{
    public function sitemap(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHours(6), function () {
            $urls = [
                [route('home'), null, '1.0'],
                [route('print.index'), null, '0.9'],
                [route('publishers.index'), null, '0.6'],
                [route('contact'), null, '0.4'],
            ];
            foreach (Category::active()->get(['slug', 'updated_at']) as $c) {
                $urls[] = [route('category.show', $c->slug), $c->updated_at, '0.8'];
            }
            foreach (Publisher::active()->get(['slug', 'updated_at']) as $p) {
                $urls[] = [route('publisher.show', $p->slug), $p->updated_at, '0.6'];
            }
            foreach (Page::active()->get(['slug', 'updated_at']) as $p) {
                $urls[] = [route('page.show', $p->slug), $p->updated_at, '0.3'];
            }
            Product::visible()->select(['id', 'slug', 'updated_at'])->orderBy('id')->chunk(2000, function ($products) use (&$urls) {
                foreach ($products as $p) {
                    $urls[] = [route('product.show', $p->slug), $p->updated_at, '0.7'];
                }
            });

            $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
            foreach ($urls as [$loc, $mod, $priority]) {
                $out .= '<url><loc>'.e($loc).'</loc>'.($mod ? '<lastmod>'.$mod->toDateString().'</lastmod>' : '').'<priority>'.$priority.'</priority></url>'."\n";
            }

            return $out.'</urlset>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $lines = ['User-agent: *'];
        foreach (['/yonetim', '/sepet', '/siparis', '/hesabim', '/mesaj/', '/fotokopi/belge', '/ara/oneri'] as $path) {
            $lines[] = 'Disallow: '.$path;
        }
        $lines[] = '';
        $lines[] = 'Sitemap: '.route('sitemap');

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
