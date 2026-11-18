<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\SearchMiss;
use App\Models\SearchRedirect;
use App\Support\Text;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Yönetim › Site araması: "kelime → adres" yönlendirmeleri ve hiçbir sonuç vermeyen aramaların raporu.
 * Rapordaki bir kelimeye tek tıkla yönlendirme eklenebilir.
 */
class SearchController extends Controller
{
    public function index(Request $request): View
    {
        // Adres alanına önerilecek site sayfaları
        $targets = collect([
            ['Ana sayfa', route('home', absolute: false)], ['Tüm kitaplar', route('products.index', absolute: false)],
            ['Yayınevleri', route('publishers.index', absolute: false)], ['Fotokopi', route('print.index', absolute: false)],
            ['Sepet', route('cart.index', absolute: false)], ['Sipariş takibi', route('orders.track', absolute: false)],
            ['İletişim', route('contact', absolute: false)], ['Sıkça sorulan sorular', route('home', absolute: false).'#sss'],
        ])->merge(Page::active()->orderBy('title')->get()->map(fn ($p) => [$p->title, parse_url($p->url(), PHP_URL_PATH)]));

        return view('admin.search.index', [
            'redirects' => SearchRedirect::orderBy('term')->get(),
            'misses' => SearchMiss::orderByDesc('hits')->orderByDesc('last_searched_at')->paginate(30)->withQueryString(),
            'missTotal' => SearchMiss::sum('hits'),
            'targets' => $targets,
            'prefill' => (string) $request->query('kelime'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['match_key' => Text::key((string) $request->input('term'))]);
        $data = $request->validate([
            'term' => ['required', 'string', 'max:100'],
            'match_key' => ['required', 'string', Rule::unique('search_redirects', 'match_key')],
            'url' => ['required', 'string', 'max:500', 'regex:#^(/|https?://)#i'],
        ], [
            'match_key.unique' => 'Bu kelime için zaten bir yönlendirme var.',
            'match_key.required' => 'Kelime harf veya rakam içermeli.',
            'url.regex' => 'Adres "/" ile (site içi) ya da http(s):// ile başlamalı.',
        ]);
        SearchRedirect::create(['term' => trim($data['term']), 'url' => trim($data['url'])]);
        // Yönlendirilen kelime artık "bulunamayan" sayılmaz
        SearchMiss::where('match_key', $data['match_key'])->delete();

        return redirect()->route('admin.search.index')->with('status', '“'.trim($data['term']).'” yönlendirmesi eklendi.');
    }

    public function destroy(SearchRedirect $redirect): RedirectResponse
    {
        $redirect->delete();

        return back()->with('status', 'Yönlendirme silindi.');
    }

    public function destroyMiss(SearchMiss $miss): RedirectResponse
    {
        $miss->delete();

        return back()->with('status', 'Kayıt silindi.');
    }

    public function clearMisses(): RedirectResponse
    {
        SearchMiss::query()->delete();

        return back()->with('status', 'Bulunamayan aramalar listesi temizlendi.');
    }
}
