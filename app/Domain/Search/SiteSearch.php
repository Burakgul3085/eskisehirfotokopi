<?php

namespace App\Domain\Search;

use App\Domain\Catalog\CategoryTree;
use App\Domain\Catalog\ProductListing;
use App\Models\Author;
use App\Models\Category;
use App\Models\HomeBlock;
use App\Models\Page;
use App\Models\Product;
use App\Models\Publisher;
use App\Models\SearchRedirect;
use App\Support\Highlight;
use App\Support\Settings;
use App\Support\Text;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Site geneli arama: ürünler, kategoriler, yayınevleri, yazarlar, bilgi sayfaları, SSS ve site bölümleri.
 *
 * Kurgu (CatalogController@search):
 *  1) direct(): aranan ifade bir şeyin ADIYLA birebir aynıysa doğrudan oraya gidilir — sırasıyla yönetimdeki
 *     yönlendirmeler, site bölümleri (İletişim, Sepet…), bilgi sayfaları, SSS soruları, kategori, yayınevi, ürün adı/ISBN.
 *  2) Değilse yalnız gerçek eşleşmeler listelenir (tüm kelimeler geçmeli; yazım düzeltmesi / öneri yok).
 *     Tek bir sonuç kalırsa yine doğrudan oraya gidilir.
 *  3) Hiçbir eşleşme yoksa 404 sayfası (ve aranan kelime "bulunamayan aramalar" raporuna yazılır).
 *
 * Karşılaştırmalar Text::key ile yapılır: büyük/küçük harf ve ı/i, ş/s, ğ/g… farkı gözetilmez.
 */
class SiteSearch
{
    public function __construct(private ProductListing $listing, private CategoryTree $tree) {}

    /**
     * Ürün sorgusu ve eşleşme durumu.
     *
     * @return array{query: Builder, mode: string, used: string}
     */
    public function products(string $q): array
    {
        if (ProductListing::terms($q) === []) {
            return ['query' => $this->listing->base(), 'mode' => 'all', 'used' => ''];
        }
        $exact = $this->listing->base(null, null, $q);

        return (clone $exact)->exists()
            ? ['query' => $exact, 'mode' => 'exact', 'used' => $q]
            : ['query' => Product::query()->visible()->whereRaw('1 = 0'), 'mode' => 'none', 'used' => $q];
    }

    /**
     * Adı birebir eşleşen hedef (yoksa null).
     *
     * @return array{title: string, url: string, type: string, redirect?: int}|null
     */
    public function direct(string $q): ?array
    {
        $key = Text::key($q);
        if ($key === '') {
            return null;
        }

        if ($r = SearchRedirect::where('match_key', $key)->first()) {
            return ['title' => $r->term, 'url' => $r->url, 'type' => 'Bağlantı', 'redirect' => $r->id];
        }
        foreach ($this->sections() as $s) {
            if (in_array($key, $s['names'], true)) {
                return ['title' => $s['title'], 'url' => $s['url'], 'type' => 'Sayfa'];
            }
        }
        foreach ($this->pageIndex() as $p) {
            if ($p['name'] === $key) {
                return ['title' => $p['title'], 'url' => $p['url'], 'type' => 'Sayfa'];
            }
        }
        foreach ($this->faqIndex() as $f) {
            if ($f['name'] === $key) {
                return ['title' => $f['q'], 'url' => $f['url'], 'type' => 'Sıkça sorulan soru'];
            }
        }

        $categories = Category::active()->whereIn('id', $this->tree->visibleIds())->where('normalized_name', $key)->limit(2)->get();
        if ($categories->count() === 1) {
            return ['title' => $categories[0]->fullName(), 'url' => route('category.show', $categories[0]), 'type' => 'Kategori'];
        }
        // Yayınevi: tam adı ("3D Yayınları") veya kısa anahtarı ("3d" — yayınevi kaydındaki eşleşme anahtarı)
        $short = trim(preg_replace('/ (yayinlari|yayinevi|yayincilik|yayin)$/', '', $key));
        $publishers = Publisher::active()->where(fn ($w) => $w->whereIn('match_key', array_unique([$key, $short]))->orWhere('name', $q))->limit(5)->get()
            ->filter(fn ($p) => in_array($p->match_key, [$key, $short], true) || Text::key($p->name) === $key)->values();
        if ($publishers->count() === 1) {
            return ['title' => $publishers[0]->name, 'url' => route('publisher.show', $publishers[0]), 'type' => 'Yayınevi'];
        }

        // Ürün: ISBN veya adı birebir (aynı adda birden çok ürün varsa liste gösterilir)
        $digits = preg_replace('/\D+/', '', $q);
        if (in_array(strlen($digits), [10, 13], true) && preg_match('/^[\d\s-]+$/', trim($q))) {
            $byIsbn = Product::visible()->where('isbn', $digits)->limit(2)->get();
            if ($byIsbn->count() === 1) {
                return ['title' => $byIsbn[0]->title, 'url' => route('product.show', $byIsbn[0]), 'type' => 'Ürün'];
            }
        }
        $named = $this->listing->base(null, null, $q)->limit(40)->get(['id', 'title', 'slug'])
            ->filter(fn ($p) => Text::key($p->title) === $key)->values();
        if ($named->count() === 1) {
            return ['title' => $named[0]->title, 'url' => route('product.show', $named[0]), 'type' => 'Ürün'];
        }

        return null;
    }

    /**
     * Ürün dışı sonuçlar: kategoriler, yayınevleri, yazarlar, sayfalar/bölümler ve SSS (başlıklarında "hl" vurgusu).
     *
     * @return array<string, list<array{title: string, hl: string, url: string, meta?: string|null, icon?: string, image?: string|null}>>
     */
    public function others(string $q, int $limit = 6): array
    {
        $terms = ProductListing::terms($q);
        if ($terms === []) {
            return ['categories' => [], 'publishers' => [], 'authors' => [], 'pages' => [], 'faq' => []];
        }
        $all = fn (string $col) => function (Builder $w) use ($col, $terms) {
            foreach ($terms as $t) {
                $w->where($col, 'like', '%'.$t.'%');
            }
        };
        $hl = fn (string $title) => Highlight::mark($title, $terms);

        $categories = Category::active()->whereIn('id', $this->tree->visibleIds())->where($all('normalized_name'))
            ->withCount(['products' => fn ($p) => $p->visible()])->orderBy('depth')->limit($limit)->get()
            ->map(fn ($c) => ['title' => $c->fullName(), 'hl' => $hl($c->fullName()), 'url' => route('category.show', $c), 'meta' => $c->products_count ? $c->products_count.' ürün' : null, 'icon' => 'layers'])->all();

        $publishers = Publisher::active()->where($all('match_key'))->withCount(['products' => fn ($p) => $p->visible()])->orderBy('name')->limit($limit)->get()
            ->filter(fn ($p) => $p->products_count > 0)
            ->map(fn ($p) => ['title' => $p->name, 'hl' => $hl($p->name), 'url' => route('publisher.show', $p), 'image' => $p->logoUrl(), 'meta' => $p->products_count.' ürün', 'icon' => 'book'])->values()->all();

        $authors = Author::query()->where($all('match_key'))->whereHas('products', fn ($p) => $p->visible())
            ->withCount(['products' => fn ($p) => $p->visible()])->orderByDesc('products_count')->limit($limit)->get()
            ->map(fn ($a) => ['title' => $a->name, 'hl' => $hl($a->name), 'url' => route('search', ['q' => $a->name]), 'meta' => 'Yazar · '.$a->products_count.' ürün', 'icon' => 'user'])->all();

        // Bölümler: adları ve anahtar kelimeleri; bilgi sayfaları: yalnız başlık; SSS: yalnız soru metni
        $pages = [];
        foreach ($this->sections() as $s) {
            if ($this->matches($s['key'], $terms)) {
                $pages[] = ['title' => $s['title'], 'hl' => $hl($s['title']), 'url' => $s['url'], 'meta' => $s['meta'], 'icon' => $s['icon']];
            }
        }
        foreach ($this->pageIndex() as $p) {
            if ($this->matches(' '.$p['name'].' ', $terms)) {
                $pages[] = ['title' => $p['title'], 'hl' => $hl($p['title']), 'url' => $p['url'], 'meta' => $p['excerpt'], 'icon' => 'list'];
            }
        }
        $faq = [];
        foreach ($this->faqIndex() as $f) {
            if ($this->matches(' '.$f['name'].' ', $terms)) {
                $faq[] = ['title' => $f['q'], 'hl' => $hl($f['q']), 'url' => $f['url'], 'meta' => Str::limit($f['a'], 110), 'icon' => 'chat'];
            }
        }

        return [
            'categories' => $categories,
            'publishers' => $publishers,
            'authors' => $authors,
            'pages' => array_slice($pages, 0, $limit),
            'faq' => array_slice($faq, 0, $limit),
        ];
    }

    /** Tüm kelimeler metinde geçiyor mu. */
    private function matches(string $key, array $terms): bool
    {
        foreach ($terms as $t) {
            if (! str_contains($key, $t)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Site bölümleri. names: birebir yazılınca doğrudan açan adlar; keywords: listede eşleşmesi için ek kelimeler.
     *
     * @return list<array{title: string, url: string, icon: string, meta: string, names: list<string>, key: string}>
     */
    private function sections(): array
    {
        $list = [
            ['Tüm kitaplar', 'products.index', [], 'book', 'Tüm ürünler ve kategoriler', 'tum kitaplar|kitaplar|tum urunler|urunler|katalog|magaza', 'kitap urun alisveris'],
            ['Yayınevleri', 'publishers.index', [], 'layers', 'Bütün yayınevleri', 'yayinevleri|yayinevi|tum yayinevleri|markalar', 'yayin yayinlari marka'],
            ['Fotokopi ve belge baskısı', 'print.index', [], 'printer', 'Belgenizi yükleyin, biz basalım', 'fotokopi ve belge baskisi|belge yukle|belge baskisi|baski|yazdir|ciktı al|cikti al', 'fotokopi belge baski yazdir cikti pdf dosya cilt spiral'],
            ['Sepetim', 'cart.index', [], 'cart', 'Sepetteki ürünler', 'sepet|sepetim|alisveris sepeti', 'sepete'],
            ['Sipariş ver', 'checkout.show', [], 'card', 'Teslimat ve ödeme', 'siparis ver|odeme|satin al', 'kapida nakit kart'],
            ['Sipariş takibi', 'orders.track', [], 'truck', 'Siparişiniz nerede?', 'siparis takibi|siparis takip|siparis sorgula|takip', 'kargo teslimat gonderi'],
            ['İletişim', 'contact', [], 'phone', 'Telefon, adres, WhatsApp ve harita', 'iletisim|bize ulasin|bize yazin|ulasim|musteri hizmetleri', 'telefon adres whatsapp harita konum destek'],
            ['Giriş yap', 'login', [], 'user', 'Hesabınıza girin', 'giris|giris yap|oturum ac|uye girisi', 'login'],
            ['Üye ol', 'register', [], 'user', 'Ücretsiz hesap oluşturun', 'uye ol|kayit ol|kaydol|uyelik|hesap olustur', 'register'],
            ['Şifremi unuttum', 'password.request', [], 'shield', 'Parolanızı sıfırlayın', 'sifremi unuttum|parolami unuttum|sifre sifirla', 'sifre parola'],
            ['Hesabım', 'account.index', [], 'user', 'Bilgileriniz ve adresleriniz', 'hesabim|hesap|profil|profilim', 'bilgilerim'],
            ['Siparişlerim', 'account.orders', [], 'package', 'Geçmiş siparişleriniz', 'siparislerim|gecmis siparisler', 'siparis'],
            ['Favorilerim', 'account.favorites', [], 'heart', 'Beğendiğiniz ürünler', 'favorilerim|favoriler|favori', 'begendiklerim'],
            ['Adreslerim', 'account.addresses.index', [], 'pin', 'Kayıtlı adresleriniz', 'adreslerim', 'adres'],
            ['Mesajlarım', 'account.messages', [], 'chat', 'Bize yazdığınız mesajlar', 'mesajlarim|mesajlar', 'mesaj'],
        ];
        $out = [];
        foreach ($list as [$title, $route, $params, $icon, $meta, $names, $keywords]) {
            if (Route::has($route)) {
                $all = array_map(fn ($n) => Text::key($n), [$title, ...explode('|', $names)]);
                $out[] = ['title' => $title, 'url' => route($route, $params), 'icon' => $icon, 'meta' => $meta,
                    'names' => array_values(array_unique($all)), 'key' => ' '.Text::key($title.' '.str_replace('|', ' ', $names).' '.$keywords).' '];
            }
        }
        if ($this->faqIndex() !== []) {
            $out[] = ['title' => 'Sıkça sorulan sorular', 'url' => route('home').'#sss', 'icon' => 'chat', 'meta' => 'Merak edilenler',
                'names' => ['sikca sorulan sorular', 'sikca sorulanlar', 'sik sorulan sorular', 'sss', 'sorular', 'yardim'], 'key' => ' sikca sik sorulan sorular sss yardim soru cevap '];
        }

        return $out;
    }

    /** Yayındaki bilgi sayfaları: aranan yalnız başlık (name); özet gösterim içindir. */
    private function pageIndex(): array
    {
        return Cache::remember('search.pages2', 600, fn () => Page::active()->get()->map(function ($p) {
            // Markdown/HTML işaretleri atılır: özet düz metin olsun
            $body = strip_tags((string) $p->body);
            $body = preg_replace('/\[([^\]]*)\]\([^)]*\)/u', '$1', $body);
            $body = preg_replace('/\{[a-z_ ]+\}/u', '', $body); // {site adi} gibi yer tutucular
            $body = preg_replace('/[#*_>`~|]+/u', ' ', $body);
            $body = trim(preg_replace('/\s+/u', ' ', $body));

            return ['title' => $p->title, 'url' => $p->url(), 'excerpt' => Str::limit($p->meta_description ?: $body, 110), 'name' => Text::key($p->title)];
        })->all());
    }

    /** Ana sayfadaki SSS soruları; her biri kendi bağlantısıyla (#sss-1 … açılmış olarak gelir). */
    private function faqIndex(): array
    {
        return Cache::remember('search.faq2', 600, function () {
            $out = [];
            foreach (HomeBlock::query()->where('type', 'faq')->where('is_active', true)->orderBy('position')->orderBy('id')->get() as $b) {
                // Ana sayfadaki numaralamayla aynı: boş sorular atılır, sıra korunur (store/blocks/faq.blade.php)
                $items = collect((array) $b->setting('items', []))->filter(fn ($i) => ! empty($i['q']) && ! empty($i['a']))->values();
                foreach ($items as $i => $item) {
                    $out[] = ['q' => $item['q'], 'a' => $item['a'], 'url' => route('home').'#sss-'.($i + 1), 'name' => Text::key($item['q'])];
                }
                break; // ana sayfada tek SSS bloğu numaralanır
            }

            return $out;
        });
    }

    /** Arama kutusu yazısı (yönetimden). */
    public static function placeholder(): string
    {
        return Settings::get('search_placeholder') ?: 'Kitap, yayınevi, yazar, kategori veya sayfa ara…';
    }
}
