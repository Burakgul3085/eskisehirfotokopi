<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\CategoryTree;
use App\Http\Controllers\Controller;
use App\Models\HomeBlock;
use App\Models\Product;
use App\Models\Publisher;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Ana sayfa blokları: ekle, düzenle, sırala, gizle. */
class HomeBlockController extends Controller
{
    public const MAX_SLIDES = 6;

    public const MAX_FEATURES = 8;

    public const MAX_FAQ = 12;

    public const ICONS = ['truck', 'cash', 'printer', 'shield', 'clock', 'book', 'phone', 'check', 'heart', 'pin', 'mail', 'upload', 'user', 'cart'];

    public function index(): View
    {
        return view('admin.home-blocks.index', ['blocks' => HomeBlock::orderBy('position')->orderBy('id')->get()]);
    }

    public function create(Request $request): View
    {
        $type = $request->query('tur');
        abort_unless(isset(HomeBlock::TYPES[$type]), 404);

        return $this->form(new HomeBlock(['type' => $type, 'is_active' => true, 'settings' => []]));
    }

    public function store(Request $request): RedirectResponse
    {
        $type = $request->input('type');
        abort_unless(isset(HomeBlock::TYPES[$type]), 422);
        $block = new HomeBlock(['type' => $type, 'position' => (int) HomeBlock::max('position') + 1]);
        $this->save($request, $block);
        Activity::log('home_block.created', $block);

        return redirect()->route('admin.home-blocks.index')->with('status', 'Blok eklendi.');
    }

    public function edit(HomeBlock $homeBlock): View
    {
        return $this->form($homeBlock);
    }

    public function update(Request $request, HomeBlock $homeBlock): RedirectResponse
    {
        $this->save($request, $homeBlock);
        Activity::log('home_block.updated', $homeBlock);

        return redirect()->route('admin.home-blocks.index')->with('status', 'Blok kaydedildi.');
    }

    public function destroy(HomeBlock $homeBlock): RedirectResponse
    {
        foreach ((array) $homeBlock->setting('slides', []) as $slide) {
            if (! empty($slide['image'])) {
                Storage::disk('public')->delete($slide['image']);
            }
        }
        $homeBlock->delete();
        Activity::log('home_block.deleted', null, ['type' => $homeBlock->type, 'title' => $homeBlock->title]);

        return back()->with('status', 'Blok silindi.');
    }

    public function move(Request $request, HomeBlock $homeBlock): RedirectResponse
    {
        $blocks = HomeBlock::orderBy('position')->orderBy('id')->get()->values();
        $i = $blocks->search(fn ($b) => $b->is($homeBlock));
        $j = $request->input('yon') === 'yukari' ? $i - 1 : $i + 1;
        if ($j >= 0 && $j < $blocks->count()) {
            [$blocks[$i], $blocks[$j]] = [$blocks[$j], $blocks[$i]];
        }
        $blocks->each(fn ($b, $k) => $b->position !== $k + 1 ? $b->update(['position' => $k + 1]) : null);

        return back();
    }

    public function toggle(HomeBlock $homeBlock): RedirectResponse
    {
        $homeBlock->update(['is_active' => ! $homeBlock->is_active]);

        return back()->with('status', $homeBlock->is_active ? 'Blok gösteriliyor.' : 'Blok gizlendi.');
    }

    private function form(HomeBlock $block): View
    {
        return view('admin.home-blocks.form', [
            'block' => $block,
            'categories' => app(CategoryTree::class)->flat(),
            'publishers' => in_array($block->type, ['products'], true) ? Publisher::orderBy('name')->get(['id', 'name']) : collect(),
            'manualProducts' => $block->type === 'products' && $block->setting('product_ids')
                ? Product::whereIn('id', (array) $block->setting('product_ids'))->get(['id', 'code', 'title'])->sortBy(fn ($p) => array_search($p->id, (array) $block->setting('product_ids')))
                : collect(),
        ]);
    }

    private function save(Request $request, HomeBlock $block): void
    {
        $common = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $url = ['nullable', 'string', 'max:255', 'regex:#^(/|https?://)#i'];

        $settings = match ($block->type) {
            'hero' => $request->validate([
                'heading' => ['nullable', 'string', 'max:160'],
                'text' => ['nullable', 'string', 'max:400'],
                'badge' => ['nullable', 'string', 'max:80'],
                'button_label' => ['nullable', 'string', 'max:40'],
                'button_url' => $url,
            ]),
            'print_banner' => $request->validate(['button_label' => ['nullable', 'string', 'max:40'], 'button_url' => $url]),
            'categories' => [
                'category_ids' => array_values(array_map('intval', $request->validate([
                    'category_ids' => ['nullable', 'array', 'max:24'], 'category_ids.*' => ['integer', 'exists:categories,id'],
                ])['category_ids'] ?? [])),
                'limit' => (int) $request->validate(['limit' => ['required', 'integer', 'min:2', 'max:24']])['limit'],
            ],
            'publishers' => ['limit' => (int) $request->validate(['limit' => ['required', 'integer', 'min:3', 'max:48']])['limit']],
            'products' => $this->productSettings($request),
            'slider' => ['slides' => $this->slides($request, $block)],
            'features' => ['items' => $this->rows($request->validate([
                'items' => ['nullable', 'array', 'max:'.self::MAX_FEATURES],
                'items.*.icon' => ['nullable', Rule::in(self::ICONS)],
                'items.*.title' => ['nullable', 'string', 'max:60'],
                'items.*.text' => ['nullable', 'string', 'max:140'],
            ])['items'] ?? [], 'title')],
            'faq' => ['items' => $this->rows($request->validate([
                'items' => ['nullable', 'array', 'max:'.self::MAX_FAQ],
                'items.*.q' => ['nullable', 'string', 'max:200'],
                'items.*.a' => ['nullable', 'string', 'max:1500'],
            ])['items'] ?? [], 'q')],
            'text' => $request->validate(['body' => ['nullable', 'string', 'max:5000']]),
            default => [],
        };

        $block->fill([
            'title' => $common['title'] ?? null,
            'subtitle' => $common['subtitle'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'settings' => $settings,
        ])->save();
    }

    private function productSettings(Request $request): array
    {
        $data = $request->validate([
            'source' => ['required', Rule::in(array_keys(HomeBlock::PRODUCT_SOURCES))],
            'category_id' => ['nullable', 'required_if:source,category', 'integer', 'exists:categories,id'],
            'publisher_id' => ['nullable', 'required_if:source,publisher', 'integer', 'exists:publishers,id'],
            'product_list' => ['nullable', 'required_if:source,manual', 'string', 'max:3000'],
            'limit' => ['required', 'integer', 'min:2', 'max:24'],
        ]);
        $ids = [];
        if ($data['source'] === 'manual') {
            // Satır veya virgülle ayrılmış ürün kodu, ISBN veya ID
            foreach (preg_split('/[\s,;]+/', (string) $data['product_list'], -1, PREG_SPLIT_NO_EMPTY) as $token) {
                $id = Product::where('code', $token)->orWhere('isbn', preg_replace('/\D/', '', $token) ?: '-')->value('id')
                    ?? (ctype_digit($token) ? Product::whereKey((int) $token)->value('id') : null);
                if ($id && ! in_array($id, $ids, true)) {
                    $ids[] = $id;
                }
            }
        }

        return [
            'source' => $data['source'],
            'category_id' => $data['source'] === 'category' ? (int) $data['category_id'] : null,
            'publisher_id' => $data['source'] === 'publisher' ? (int) $data['publisher_id'] : null,
            'product_ids' => $ids,
            'limit' => (int) $data['limit'],
        ];
    }

    private function slides(Request $request, HomeBlock $block): array
    {
        $request->validate([
            'slides' => ['nullable', 'array', 'max:'.self::MAX_SLIDES],
            'slides.*.file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'slides.*.title' => ['nullable', 'string', 'max:100'],
            'slides.*.text' => ['nullable', 'string', 'max:200'],
            'slides.*.button_label' => ['nullable', 'string', 'max:40'],
            'slides.*.url' => ['nullable', 'string', 'max:255', 'regex:#^(/|https?://)#i'],
        ]);
        $old = array_values((array) $block->setting('slides', []));
        $out = [];
        for ($i = 0; $i < self::MAX_SLIDES; $i++) {
            $in = $request->input("slides.$i", []);
            $image = $old[$i]['image'] ?? null;
            if ($image && ! empty($in['remove'])) {
                Storage::disk('public')->delete($image);
                $image = null;
            }
            if ($file = $request->file("slides.$i.file")) {
                if ($image) {
                    Storage::disk('public')->delete($image);
                }
                $image = $file->storeAs('slides', Str::lower(Str::random(16)).'.'.$file->extension(), 'public');
            }
            if ($image) {
                $out[] = ['image' => $image] + array_map(fn ($v) => $v === null ? null : trim($v), array_intersect_key($in, array_flip(['title', 'text', 'button_label', 'url'])));
            }
        }

        return $out;
    }

    /** Boş satırları atar. */
    private function rows(array $rows, string $required): array
    {
        return array_values(array_filter(array_map(fn ($r) => array_map(fn ($v) => is_string($v) ? trim($v) : $v, $r), $rows), fn ($r) => ! empty($r[$required])));
    }
}
