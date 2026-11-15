<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\CategoryLearner;
use App\Domain\Catalog\CategoryTree;
use App\Domain\Catalog\PriceSheet;
use App\Domain\Catalog\ProductImages;
use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Category;
use App\Models\CategoryMove;
use App\Models\PlacementRule;
use App\Models\Product;
use App\Models\Publisher;
use App\Support\Activity;
use App\Support\ClassificationInput;
use App\Support\Money;
use App\Support\SpecInput;
use App\Support\Text;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Ürün yönetimi: filtreli liste, toplu işlemler, düzenleme, görsel ve Excel fiyat şablonu. */
class ProductController extends Controller
{
    /** Ürün listesi süzgeçleri (toplu işlemde "aramaya uyan tümü" aynılarını f[...] olarak gönderir). */
    public const FILTERS = ['q', 'yayinevi', 'kategori', 'durum', 'fiyat', 'gorsel', 'ders', 'tur'];

    public function __construct(private CategoryTree $tree, private ProductImages $images) {}

    public function index(Request $request): View
    {
        $filters = array_filter($request->only(self::FILTERS), fn ($v) => is_string($v) && $v !== '');
        $products = $this->filtered($filters)->with(['publisher:id,name', 'category:id,name', 'author:id,name'])
            ->orderByDesc('id')->paginate((int) $request->query('adet', 50) === 200 ? 200 : 50)->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'categories' => $this->tree->flat(),
            'specOptions' => Product::specOptions(),
            'publishers' => Publisher::orderBy('name')->get(['id', 'name']),
            'subjects' => Product::whereNotNull('subject')->where('subject', '!=', '')->distinct()->orderBy('subject')->pluck('subject'),
            'bookTypes' => Product::whereNotNull('book_type')->where('book_type', '!=', '')->distinct()->orderBy('book_type')->pluck('book_type'),
            'groups' => Category::whereNull('parent_id')->orderBy('position')->get(['id', 'name']),
            'filters' => $filters,
            // Yeni kategori için varsayılan ana kategori: aramaya uyan ürünlerin çoğunun bulunduğu grup
            'defaultGroup' => (int) explode('/', (string) Category::whereKey($this->filtered($filters)->whereNotNull('category_id')
                ->groupBy('category_id')->orderByRaw('count(*) desc')->limit(1)->value('category_id'))->value('path'))[0],
            'ruleConditions' => PlacementRule::conditionsFrom($filters),
            'lastMove' => CategoryMove::lastUndoable(),
            'stats' => [
                'total' => Product::count(),
                'active' => Product::where('status', 'active')->count(),
                'draft' => Product::where('status', 'draft')->count(),
                'missing_price' => Product::missingPrice()->count(),
                'missing_image' => Product::whereNull('image_key')->count(),
            ],
        ]);
    }

    /** Listedeki filtreler (Excel dışa aktarma da aynısını kullanır). */
    private function filtered(array $f): Builder
    {
        $q = trim((string) ($f['q'] ?? ''));
        $has = fn ($k) => isset($f[$k]) && $f[$k] !== '';

        return Product::query()
            ->when($q !== '', function ($w) use ($q) {
                $digits = preg_replace('/\D/', '', $q);
                $w->where(function ($x) use ($q, $digits) {
                    foreach (explode(' ', Text::key($q)) as $term) {
                        $x->where('search_text', 'like', '%'.$term.'%');
                    }
                    if (strlen($digits) >= 10) {
                        $x->orWhere('isbn', $digits);
                    }
                });
            })
            ->when($has('yayinevi'), fn ($w) => $w->where('publisher_id', (int) $f['yayinevi']))
            ->when($has('kategori') && (string) $f['kategori'] !== '0', function ($w) use ($f) {
                $cat = Category::find((int) $f['kategori']);
                $w->whereIn('category_id', $cat ? $cat->subtreeIds() : [0]);
            })
            ->when(isset(Product::STATUSES[$f['durum'] ?? '']), fn ($w) => $w->where('status', $f['durum']))
            ->when(($f['fiyat'] ?? null) === 'eksik', fn ($w) => $w->missingPrice())
            ->when(($f['fiyat'] ?? null) === 'var', fn ($w) => $w->priced())
            ->when(($f['gorsel'] ?? null) === 'eksik', fn ($w) => $w->whereNull('image_key'))
            ->when($has('ders'), fn ($w) => $w->where('subject', $f['ders']))
            ->when($has('tur'), fn ($w) => $w->where('book_type', $f['tur']))
            ->when((string) ($f['kategori'] ?? '') === '0', fn ($w) => $w->whereNull('category_id'));
    }

    public function create(): View
    {
        return $this->form(new Product(['status' => 'draft']));
    }

    public function edit(Product $product): View
    {
        return $this->form($product->load(['author', 'publisher', 'category']));
    }

    private function form(Product $product): View
    {
        return view('admin.products.form', [
            'product' => $product,
            'categories' => $this->tree->flat(),
            'publishers' => Publisher::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        $product = Product::create($data + ['slug' => Product::uniqueSlug($data['title'])]);
        $this->saveImage($request, $product);
        Activity::log('product.created', $product, ['title' => $product->title]);

        return redirect()->route('admin.products.edit', $product)->with('status', 'Ürün eklendi.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request, $product);
        $product->update($data);
        $this->saveImage($request, $product);
        Activity::log('product.updated', $product, collect($product->getChanges())->except(['updated_at', 'search_text'])->all());

        return redirect()->route('admin.products.edit', $product)->with('status', 'Ürün kaydedildi.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->images->delete($product);
        Activity::log('product.deleted', null, ['title' => $product->title, 'isbn' => $product->isbn]);
        $product->delete();

        return redirect()->route('admin.products.index')->with('status', 'Ürün silindi.');
    }

    /**
     * Toplu işlem: seçilen ürünlerde (ya da scope=all ise f[...] süzgeçlerine uyan tüm ürünlerde) durum, kategori,
     * öne çıkarma, bilgi gösterimi veya silme. "expected" onayda gösterilen sayıdır; arada sonuç değiştiyse işlem yapılmaz.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'scope' => ['nullable', Rule::in(['selected', 'all'])],
            'ids' => ['exclude_if:scope,all', 'required', 'array', 'min:1', 'max:1000'],
            'ids.*' => ['integer'],
            'f' => ['nullable', 'array'],
            'f.*' => ['nullable', 'string', 'max:190'],
            'expected' => ['nullable', 'integer', 'min:0'],
            'action' => ['required', Rule::in(['active', 'passive', 'draft', 'feature', 'unfeature', 'category', 'delete', 'spec_show', 'spec_hide', 'spec_default'])],
            'category_id' => ['nullable', 'required_if:action,category', function ($attr, $value, $fail) {
                if ($value !== 'new' && ! Category::whereKey((int) $value)->exists()) {
                    $fail('Taşınacak kategori bulunamadı.');
                }
            }],
            'new_category_name' => ['nullable', 'exclude_unless:category_id,new', 'required', 'string', 'max:100'],
            'new_category_parent' => ['nullable', 'exclude_unless:category_id,new', 'required', 'integer', Rule::exists('categories', 'id')->whereNull('parent_id')],
            'save_rule' => ['nullable', 'boolean'],
            'spec' => ['nullable', 'required_if:action,spec_show,spec_hide,spec_default', 'string', 'max:80', 'regex:/^('.implode('|', array_keys(Product::SPECS)).'|ek:[a-z0-9 ]+)$/'],
        ], [
            'ids.required' => 'Önce ürün seçin.',
            'category_id.required_if' => 'Taşınacak kategoriyi seçin.',
            'new_category_name.required' => 'Yeni kategorinin adını yazın.',
            'new_category_parent.required' => 'Yeni kategorinin hangi ana kategorinin altına açılacağını seçin.',
            'new_category_parent.exists' => 'Yeni kategori yalnız bir ana kategorinin altına açılabilir (en fazla iki kat).',
            'spec.required_if' => 'Gösterilecek/gizlenecek bilgiyi seçin.',
        ]);
        $all = ($data['scope'] ?? null) === 'all';
        $filters = array_intersect_key(array_filter($data['f'] ?? [], fn ($v) => $v !== null && $v !== ''), array_flip(self::FILTERS));
        $query = $all ? $this->filtered($filters) : Product::whereIn('id', $data['ids']);
        $total = $query->count();
        if ($total === 0) {
            return back()->withErrors(['ids' => $all ? 'Aramaya uyan ürün yok.' : 'Seçilen ürünler bulunamadı.']);
        }
        // Onaydan sonra liste değiştiyse (başka sekmede ürün eklendi/silindi) onaylanmamış sayıda ürüne dokunulmaz
        if ($all && isset($data['expected']) && (int) $data['expected'] !== $total) {
            return back()->withErrors(['ids' => "Aramaya uyan ürün sayısı değişti ({$data['expected']} → {$total}). Listeyi yenileyip yeniden onaylayın."]);
        }
        $n = 0;
        switch ($data['action']) {
            case 'active':
                // Fiyatı olmayan ürün yayına alınmaz (K2).
                $n = $query->clone()->priced()->get()->each(fn ($p) => $p->update(['status' => 'active']))->count();
                $skipped = $total - $n;
                break;
            case 'passive':
            case 'draft':
                $n = $query->update(['status' => $data['action']]);
                break;
            case 'feature':
            case 'unfeature':
                $n = $query->update(['is_featured' => $data['action'] === 'feature']);
                break;
            case 'category':
                return $this->moveToCategory($query, $data, $filters, $request->boolean('save_rule'));
            case 'spec_show':
            case 'spec_hide':
            case 'spec_default':
                $n = Product::setSpecVisibility($query->pluck('id')->all(), $data['spec'], ['spec_show' => true, 'spec_hide' => false, 'spec_default' => null][$data['action']]);
                break;
            case 'delete':
                $query->get()->each(function ($p) use (&$n) {
                    $this->images->delete($p);
                    $p->delete();
                    $n++;
                });
                break;
        }
        Activity::log('products.bulk', null, ['action' => $data['action'], 'count' => $n, 'scope' => $all ? 'all' : 'selected', 'filters' => $all ? $filters : null]);
        $msg = $data['action'] === 'delete' ? "{$n} ürün silindi." : "{$n} ürün güncellendi.";
        if (! empty($skipped)) {
            $msg .= " {$skipped} ürün fiyatı olmadığı için yayına alınmadı.";
        }

        return back()->with('status', $msg);
    }

    /**
     * Kategoriye taşıma: gerekirse yeni kategori açar (ana kategorinin altına), isteğe bağlı yerleştirme kuralı kaydeder,
     * eski kategorileri "Geri al" için saklar.
     */
    private function moveToCategory(Builder $query, array $data, array $filters, bool $saveRule): RedirectResponse
    {
        $conditions = $saveRule ? PlacementRule::conditionsFrom($filters) : [];
        if ($saveRule && $conditions === []) {
            return back()->withErrors(['save_rule' => 'Kural için arama metni ya da Ders, Kitap türü, Yayınevi, Kategori süzgeçlerinden biri gerekir; durum/fiyat/görsel süzgeçleri yeni gelen kitaplarda anlamlı değildir.']);
        }

        $result = DB::transaction(function () use ($query, $data, $conditions, $saveRule) {
            $created = null;
            if ($data['category_id'] === 'new') {
                $parent = Category::findOrFail((int) $data['new_category_parent']);
                $name = trim(preg_replace('/\s+/u', ' ', $data['new_category_name']));
                if (Text::key($name) === '') {
                    throw ValidationException::withMessages(['new_category_name' => 'Yeni kategorinin adını yazın.']);
                }
                // Aynı ana kategoride aynı adlı kategori varsa yenisi açılmaz, ona taşınır
                $target = Category::where('parent_id', $parent->id)->where('normalized_name', Text::key($name))->first();
                if (! $target) {
                    $target = $created = $this->tree->create(['name' => $name], $parent);
                    Activity::log('category.created', $target, ['name' => $target->name, 'via' => 'products.bulk']);
                }
            } else {
                $target = Category::findOrFail((int) $data['category_id']);
            }
            if (isset($conditions['category_id']) && (int) $conditions['category_id'] === $target->id) {
                throw ValidationException::withMessages(['save_rule' => 'Kural, kitapları zaten bulundukları kategoriye taşırdı; Kategori süzgecini kaldırın ya da başka bir hedef seçin.']);
            }

            // Ürün id => eski kategori; zaten hedefte olanlar değişmez
            $moves = [];
            $already = 0;
            $query->clone()->chunkById(200, function ($products) use ($target, &$moves, &$already) {
                foreach ($products as $p) {
                    if ($p->category_id === $target->id) {
                        $already++;

                        continue;
                    }
                    $moves[$p->id] = $p->category_id;
                    $p->update(['category_id' => $target->id]);
                }
            });

            $rule = null;
            $previousTarget = null;
            if ($saveRule) {
                // Aynı koşullu kural varsa yenisi eklenmez, hedefi güncellenir
                $rule = PlacementRule::all()->first(fn ($r) => $r->conditions == $conditions);
                $rule ??= new PlacementRule(['conditions' => $conditions, 'admin_id' => auth('admin')->id()]);
                $previousTarget = $rule->exists ? $rule->category_id : null;
                $rule->fill(['category_id' => $target->id, 'label' => PlacementRule::describe($conditions)])->save();
                Activity::log('placement_rule.saved', $rule, ['label' => $rule->label, 'category' => $target->fullName()]);
            }

            $move = $moves || $created || $rule ? CategoryMove::create([
                'admin_id' => auth('admin')->id(), 'category_id' => $target->id, 'created_category_id' => $created?->id,
                'rule_id' => $rule?->id, 'rule_previous_category_id' => $previousTarget, 'target_name' => $target->fullName(),
                'moves' => $moves, 'count' => count($moves),
            ]) : null;

            return [$target, count($moves), $already, $created, $rule, $move];
        });
        [$target, $moved, $already, $created, $rule, $move] = $result;
        CategoryLearner::forget();
        Activity::log('products.bulk', null, ['action' => 'category', 'count' => $moved, 'category' => $target->fullName(), 'move' => $move?->id]);

        $msg = "{$moved} ürün “{$target->fullName()}” kategorisine taşındı.";
        if ($already) {
            $msg .= " {$already} ürün zaten oradaydı.";
        }
        if ($created) {
            $msg .= ' Yeni kategori açıldı.';
        }
        if ($rule) {
            $msg .= ' Kural kaydedildi ('.$rule->label.'): bundan sonra yüklenen ve bu koşula uyan kitaplar da buraya gidecek; Kategoriler sayfasından silinebilir.';
        }

        return back()->with('status', $msg);
    }

    /** Toplu taşımayı geri alır: hâlâ hedefte duran ürünler eski kategorilerine döner; bu taşımanın kuralı ve açtığı boş kategori silinir. */
    public function undoMove(CategoryMove $move): RedirectResponse
    {
        if ($move->undone_at) {
            return back()->withErrors(['ids' => 'Bu taşıma zaten geri alındı.']);
        }
        [$restored, $skipped, $deletedCategory, $deletedRule] = DB::transaction(function () use ($move) {
            $restored = 0;
            $skipped = 0;
            $moves = $move->moves ?? [];
            $existing = Category::whereIn('id', array_filter(array_unique(array_values($moves))))->pluck('id')->all();
            foreach (array_chunk($moves, 200, true) as $chunk) {
                foreach (Product::whereIn('id', array_keys($chunk))->get() as $p) {
                    $old = $chunk[$p->id] ?? null;
                    // Sonradan başka yere taşınan ya da eski kategorisi silinmiş ürüne dokunulmaz
                    if ($move->category_id === null || $p->category_id !== $move->category_id || ($old !== null && ! in_array($old, $existing, true))) {
                        $skipped++;

                        continue;
                    }
                    $p->update(['category_id' => $old]);
                    $restored++;
                }
            }
            $skipped += count($moves) - $restored - $skipped; // silinmiş ürünler

            // Bu taşımada açılan kural silinir; var olan kuralın hedefi değiştiyse eski hedefine döner
            $deletedRule = false;
            if ($rule = $move->rule) {
                if ($move->rule_previous_category_id && Category::whereKey($move->rule_previous_category_id)->exists()) {
                    $rule->update(['category_id' => $move->rule_previous_category_id]);
                } else {
                    $rule->delete();
                    $deletedRule = true;
                }
            }
            $deletedCategory = null;
            $created = $move->created_category_id ? Category::find($move->created_category_id) : null;
            if ($created && ! $created->children()->exists() && ! $created->products()->exists()) {
                $deletedCategory = $created->fullName();
                $this->tree->delete($created);
            }
            $move->update(['undone_at' => now()]);

            return [$restored, $skipped, $deletedCategory, $deletedRule];
        });
        CategoryLearner::forget();
        Activity::log('products.bulk_undo', $move, ['restored' => $restored, 'skipped' => $skipped]);

        $msg = "Taşıma geri alındı: {$restored} ürün eski kategorisine döndü.";
        if ($skipped) {
            $msg .= " {$skipped} ürün o arada başka yere taşındığı, silindiği ya da eski kategorisi silindiği için değişmedi.";
        }
        if ($deletedRule) {
            $msg .= ' Bu taşımayla kaydedilen kural silindi.';
        }
        if ($deletedCategory) {
            $msg .= " Boş kalan “{$deletedCategory}” kategorisi silindi.";
        }

        return back()->with('status', $msg);
    }

    /**
     * Ürün listesinden toplu sayfa sayısı girişi: pages[ID] => sayfa. Boş hücre değiştirmez. Fiyatlar sayfa sayısından
     * otomatik hesaplanır; "publish" işaretliyse fiyatı oluşan taslaklar yayına alınır.
     */
    public function pages(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pages' => ['required', 'array', 'max:1000'],
            'pages.*' => ['nullable', 'integer', 'min:1', 'max:20000'],
        ], ['pages.*.integer' => 'Sayfa sayısı tam sayı olmalıdır.', 'pages.*.min' => 'Sayfa sayısı en az 1 olmalıdır.', 'pages.*.max' => 'Sayfa sayısı en fazla 20000 olabilir.']);
        $changes = array_filter($data['pages'], fn ($v) => $v !== null && $v !== '');
        $saved = 0;
        $published = 0;
        foreach (Product::whereIn('id', array_keys($changes))->get() as $p) {
            $p->page_count = (int) $changes[$p->id];
            if ($request->boolean('publish') && $p->status === 'draft') {
                $p->status = 'active';
                $published++;
            }
            if ($p->isDirty()) {
                $p->save();
                $saved++;
            }
        }
        Activity::log('products.pages', null, ['count' => $saved, 'published' => $published]);

        return back()->with('status', "{$saved} ürünün sayfa sayısı kaydedildi, fiyatları hesaplandı".($published ? "; {$published} ürün yayına alındı" : '').'.');
    }

    public function priceTemplate(Request $request, PriceSheet $sheet): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'fiyat').'.xlsx';
        $sheet->export($this->filtered($request->query()), $path);

        return response()->download($path, 'fiyat-sablonu-'.now()->format('Y-m-d-Hi').'.xlsx')->deleteFileAfterSend();
    }

    public function priceImport(Request $request, PriceSheet $sheet): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:20480']], ['file.mimes' => 'Yalnızca .xlsx dosyası yükleyin.']);
        try {
            $result = $sheet->import($request->file('file')->getRealPath(), $request->boolean('publish'));
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['file' => 'Dosya okunamadı. Excel\'de .xlsx olarak kaydedip yeniden deneyin.']);
        }

        return back()->with('status', "Fiyatlar işlendi: {$result['updated']} ürün güncellendi".
            ($result['published'] ? ", {$result['published']} ürün yayına alındı" : '').
            ($result['unchanged'] ? ", {$result['unchanged']} ürün zaten aynıydı" : '').'.')
            ->with('import_errors', array_slice($result['errors'], 0, 100));
    }

    private function validated(Request $request, ?Product $product): array
    {
        $request->merge(['isbn' => preg_replace('/[^0-9X]/i', '', (string) $request->input('isbn')) ?: null]);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:40', Rule::unique('products', 'code')->ignore($product)],
            'isbn' => ['nullable', 'string', 'between:10,13', Rule::unique('products', 'isbn')->ignore($product)],
            'author' => ['nullable', 'string', 'max:150'],
            'publisher_id' => ['nullable', 'integer', 'exists:publishers,id'],
            ...ClassificationInput::rules(),
            'page_count' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'print_year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'description' => ['nullable', 'string', 'max:20000'],
            'status' => ['required', Rule::in(array_keys(Product::STATUSES))],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:170'],
            'image' => ['nullable', 'image', 'max:15360'],
            ...SpecInput::rules(),
        ]);
        unset($data['spec_visibility'], $data['extra_specs']);
        $data = ClassificationInput::from($request) + $data;
        $data += SpecInput::from($request);
        $prices = [];
        // Satış fiyatları sayfa sayısından hesaplanır (Product::booted); burada yalnız üstü çizili eski fiyatlar alınır.
        foreach (['compare_bw', 'compare_color'] as $field) {
            $raw = $request->input($field);
            if (blank($raw)) {
                $prices[$field] = null;

                continue;
            }
            $value = Money::parse($raw);
            if ($value === null || bccomp($value, '0', 2) <= 0) {
                throw ValidationException::withMessages([$field => 'Geçerli bir tutar girin (ör. 149,90).']);
            }
            $prices[$field] = $value;
        }
        // Eski kayıt: sayfa sayısı hiç girilmemiş, elle fiyatı olan ürün yayında kalabilir.
        $legacyPriced = $product && $product->page_count === null && $product->price_bw !== null;
        if ($data['status'] === 'active' && empty($data['page_count']) && ! $legacyPriced) {
            throw ValidationException::withMessages(['page_count' => 'Sayfa sayısı olmayan ürünün fiyatı hesaplanamaz; yayına almak için sayfa sayısını girin.']);
        }
        $data['author_id'] = Author::resolve($data['author'] ?? null)?->id;
        unset($data['author'], $data['image']);
        $data['is_featured'] = $request->boolean('is_featured');

        return $data + $prices;
    }

    private function saveImage(Request $request, Product $product): void
    {
        if ($request->hasFile('image')) {
            $this->images->store($product, $request->file('image')->getRealPath());
        } elseif ($request->boolean('remove_image')) {
            $this->images->delete($product);
        }
    }
}
