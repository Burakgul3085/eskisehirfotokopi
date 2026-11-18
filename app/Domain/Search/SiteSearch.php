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
use App\Support\Settings;
use App\Support\Text;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Site geneli arama: ürünler, kategoriler, yayınevleri, yazarlar, bilgi sayfaları, SSS ve site bölümleri
 * (sepet, fotokopi, sipariş takibi, iletişim…). Yalnız gerçek eşleşme gösterilir: tüm kelimeleri içeren ürün varsa
 * "exact", yoksa "none"; ürün dışı sonuç da yoksa arama 404 sayfasını gösterir (CatalogController@search).
 */
class SiteSearch
{
    public function __construct(private ProductListing $listing, private CategoryTree $tree) {}

    /**
     * Ürün sorgusu ve hangi yöntemle bulunduğu.
     *
     * @return array{query: Builder, mode: string, used: string}
     */
    public function products(string $q): array
    {
        $terms = ProductListing::terms($q);
        if ($terms === []) {
            return ['query' => $this->listing->base(), 'mode' => 'all', 'used' => ''];
        }

        $exact = $this->listing->base(null, null, $q);
        if ((clone $exact)->exists()) {
            return ['query' => $exact, 'mode' => 'exact', 'used' => $q];
        }

        // Yalnız gerçek eşleşme: yazım düzeltmesi ve "kelimelerden biri" eşleşmesi yapılmaz
        return ['query' => Product::query()->visible(), 'mode' => 'none', 'used' => $q];
    }

    /**
     * Ürün dışı sonuçlar: kategoriler, yayınevleri, yazarlar, sayfalar/bölümler ve SSS.
     *
     * @return array<string, list<array{title: string, url: string, meta?: string|null, icon?: string, image?: string|null}>>
     */
    public function others(string $q, int $limit = 6): array
    {
        $terms = ProductListing::terms($q);
        if ($terms === []) {
            return ['categories' => [], 'publishers' => [], 'authors' => [], 'pages' => [], 'faq' => []];
        }

        return $this->collectOthers($terms, $limit);
    }

    /** Arama kutusu boşken gösterilen hızlı erişim: site bölümleri ve en dolu kategoriler. */
    public function quick(): array
    {
        return Cache::remember('search.quick', 600, function () {
            $sections = array_slice(array_map(fn ($s) => ['title' => $s['title'], 'url' => $s['url'], 'icon' => $s['icon']], $this->sections()), 0, 6);
            $visible = $this->tree->visibleIds();
            $categories = Category::active()->whereIn('id', $visible)->where('depth', '<=', 1)
                ->withCount(['products' => fn ($p) => $p->visible()])->orderByDesc('products_count')->limit(8)->get()
                ->filter(fn ($c) => $c->products_count > 0)
                ->map(fn ($c) => ['title' => $c->name, 'url' => route('category.show', $c)])->values()->all();

            return ['sections' => $sections, 'categories' => $categories];
        });
    }

    private function collectOthers(array $terms, int $limit): array
    {
        $all = function (string $col) use ($terms) {
            return function (Builder $w) use ($col, $terms) {
                foreach ($terms as $t) {
                    $w->where($col, 'like', '%'.$t.'%');
                }
            };
        };

        $categories = Category::active()->whereIn('id', $this->tree->visibleIds())->where($all('normalized_name'))
            ->withCount(['products' => fn ($p) => $p->visible()])->orderBy('depth')->limit($limit)->get()
            ->map(fn ($c) => ['title' => $c->fullName(), 'url' => route('category.show', $c), 'meta' => $c->products_count ? $c->products_count.' ürün' : null, 'icon' => 'layers'])->all();

        $publishers = Publisher::active()->where($all('match_key'))->withCount(['products' => fn ($p) => $p->visible()])->orderBy('name')->limit($limit)->get()
            ->map(fn ($p) => ['title' => $p->name, 'url' => route('publisher.show', $p), 'image' => $p->logoUrl(), 'meta' => $p->products_count ? $p->products_count.' ürün' : null, 'icon' => 'book'])->all();

        $authors = Author::query()->where($all('match_key'))->whereHas('products', fn ($p) => $p->visible())
            ->withCount(['products' => fn ($p) => $p->visible()])->orderByDesc('products_count')->limit($limit)->get()
            ->map(fn ($a) => ['title' => $a->name, 'url' => route('search', ['q' => $a->name]), 'meta' => 'Yazar · '.$a->products_count.' ürün', 'icon' => 'user'])->all();

        $pages = [];
        foreach ($this->sections() as $s) {
            if ($this->matches($s['key'], $terms)) {
                $pages[] = ['title' => $s['title'], 'url' => $s['url'], 'meta' => $s['meta'], 'icon' => $s['icon']];
            }
        }
        foreach ($this->pageIndex() as $p) {
            if ($this->matches($p['key'], $terms)) {
                $pages[] = ['title' => $p['title'], 'url' => $p['url'], 'meta' => $p['excerpt'], 'icon' => 'list'];
            }
        }

        $faq = [];
        foreach ($this->faqIndex() as $f) {
            if ($this->matches($f['key'], $terms)) {
                $faq[] = ['title' => $f['q'], 'url' => $f['url'], 'meta' => Str::limit($f['a'], 110), 'icon' => 'chat'];
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

    /** Tüm kelimeler metinde geçiyor mu (kelime başı eşleşmesi de sayılır). */
    private function matches(string $key, array $terms): bool
    {
        foreach ($terms as $t) {
            if (! str_contains($key, $t)) {
                return false;
            }
        }

        return true;
    }

    /** Site bölümleri: anahtar kelimelerle (ör. "kargo" → Sipariş takibi, "yazdır" → Fotokopi). */
    private function sections(): array
    {
        $list = [
            ['Tüm kitaplar', 'products.index', [], 'book', 'Tüm ürünler ve kategoriler', 'tum kitaplar urunler urun katalog magaza alisveris kitap kitaplar soru bankasi deneme'],
            ['Yayınevleri', 'publishers.index', [], 'layers', 'Bütün yayınevleri', 'yayinevleri yayinevi yayin yayinlari marka markalar'],
            ['Fotokopi ve belge baskısı', 'print.index', [], 'printer', 'Belgenizi yükleyin, biz basalım', 'fotokopi belge baski baskisi bastir yazdir yazdirma cikti print pdf word excel powerpoint dosya yukle yukleme renkli siyah beyaz cilt ciltleme spiral tez odev sunum'],
            ['Sepetim', 'cart.index', [], 'cart', 'Sepetteki ürünler', 'sepet sepetim alisveris sepete odeme'],
            ['Sipariş ver', 'checkout.show', [], 'card', 'Teslimat ve ödeme', 'siparis ver satin al odeme kapida nakit kart kredi'],
            ['Sipariş takibi', 'orders.track', [], 'truck', 'Siparişiniz nerede?', 'siparis takip takibi kargo teslimat teslim nerede durum gonderi'],
            ['İletişim', 'contact', [], 'phone', 'Telefon, adres, WhatsApp ve harita', 'iletisim telefon numara adres mail eposta e posta whatsapp harita konum yol tarifi ulasim bize ulasin mesaj destek yardim musteri hizmetleri'],
            ['Giriş yap', 'login', [], 'user', 'Hesabınıza girin', 'giris yap oturum ac login hesap'],
            ['Üye ol', 'register', [], 'user', 'Ücretsiz hesap oluşturun', 'uye ol uyelik kayit kaydol hesap olustur register'],
            ['Şifremi unuttum', 'password.request', [], 'shield', 'Parolanızı sıfırlayın', 'sifre sifremi parola unuttum sifirla yenile'],
            ['Hesabım', 'account.index', [], 'user', 'Bilgileriniz ve adresleriniz', 'hesabim hesap profil bilgilerim adres adreslerim'],
            ['Siparişlerim', 'account.orders', [], 'package', 'Geçmiş siparişleriniz', 'siparislerim siparis gecmis eski siparisler tekrar'],
            ['Favorilerim', 'account.favorites', [], 'heart', 'Beğendiğiniz ürünler', 'favori favoriler favorilerim begendiklerim begen'],
        ];
        $out = [];
        foreach ($list as [$title, $route, $params, $icon, $meta, $keywords]) {
            if (Route::has($route)) {
                $out[] = ['title' => $title, 'url' => route($route, $params), 'icon' => $icon, 'meta' => $meta, 'key' => ' '.Text::key($title.' '.$keywords).' '];
            }
        }
        if ($this->faqIndex() !== []) {
            $out[] = ['title' => 'Sıkça sorulan sorular', 'url' => route('home').'#sss', 'icon' => 'chat', 'meta' => 'Merak edilenler', 'key' => ' sikca sik sorulan sorular sss yardim soru cevap '];
        }

        return $out;
    }

    /** Yayındaki bilgi sayfaları (başlık + içerik). */
    private function pageIndex(): array
    {
        return Cache::remember('search.pages', 600, fn () => Page::active()->get()->map(function ($p) {
            // Markdown/HTML işaretleri atılır: özet düz metin olsun
            $body = strip_tags((string) $p->body);
            $body = preg_replace('/\[([^\]]*)\]\([^)]*\)/u', '$1', $body);
            $body = preg_replace('/[#*_>`~|]+/u', ' ', $body);
            $body = trim(preg_replace('/\s+/u', ' ', $body));

            return ['title' => $p->title, 'url' => $p->url(), 'excerpt' => Str::limit($body, 110), 'key' => ' '.Text::key($p->title.' '.$body).' '];
        })->all());
    }

    /** Ana sayfadaki SSS blokları. */
    private function faqIndex(): array
    {
        return Cache::remember('search.faq', 600, function () {
            $out = [];
            foreach (HomeBlock::query()->where('type', 'faq')->where('is_active', true)->get() as $b) {
                foreach ((array) $b->setting('items', []) as $item) {
                    if (! empty($item['q']) && ! empty($item['a'])) {
                        $out[] = ['q' => $item['q'], 'a' => $item['a'], 'url' => route('home').'#sss', 'key' => ' '.Text::key($item['q'].' '.$item['a']).' '];
                    }
                }
            }

            return $out;
        });
    }

    /** Sonuçlarda gösterilecek arama kutusu yazısı (yönetimden). */
    public static function placeholder(): string
    {
        return Settings::get('search_placeholder') ?: 'Kitap, yayınevi, yazar, kategori veya sayfa ara…';
    }
}
