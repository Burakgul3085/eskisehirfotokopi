<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\CategoryTree;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\Activity;
use App\Support\Text;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/** Kategori ağacı yönetimi: sınırsız derinlik, taşıma, sıralama, ürün sayıları. */
class CategoryController extends Controller
{
    public function __construct(private CategoryTree $tree) {}

    public function index(): View
    {
        $counts = Product::query()->select('category_id', DB::raw('count(*) as c'))->groupBy('category_id')->pluck('c', 'category_id');

        return view('admin.categories.index', ['categories' => $this->tree->flat(), 'counts' => $counts]);
    }

    public function create(Request $request): View
    {
        return view('admin.categories.form', [
            'category' => new Category(['is_active' => true, 'show_in_menu' => true, 'parent_id' => $request->integer('parent') ?: null]),
            'parents' => $this->tree->flat(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $parent = $request->filled('parent_id') ? Category::findOrFail($request->integer('parent_id')) : null;
        if (Category::where('parent_id', $parent?->id)->where('normalized_name', Text::key($data['name']))->exists()) {
            return back()->withInput()->withErrors(['name' => 'Bu üst kategoride aynı adlı bir kategori var.']);
        }
        $category = $this->tree->create($data, $parent);
        $this->storeImage($request, $category);
        Activity::log('category.created', $category, ['name' => $category->name]);

        return redirect()->route('admin.categories.index')->with('status', '“'.$category->name.'” eklendi.');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.form', [
            'category' => $category,
            'parents' => $this->tree->flat()->reject(fn ($c) => str_starts_with($c['path'], $category->path)),
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validated($request);
        $parent = $request->filled('parent_id') ? Category::findOrFail($request->integer('parent_id')) : null;
        $dup = Category::where('parent_id', $parent?->id)->where('normalized_name', Text::key($data['name']))->whereKeyNot($category->id)->exists();
        if ($dup) {
            return back()->withInput()->withErrors(['name' => 'Bu üst kategoride aynı adlı bir kategori var.']);
        }
        try {
            $category = $this->tree->update($category, $data, $parent);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['parent_id' => $e->getMessage()]);
        }
        $this->storeImage($request, $category);
        Activity::log('category.updated', $category, ['name' => $category->name]);

        return redirect()->route('admin.categories.index')->with('status', '“'.$category->name.'” güncellendi.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        try {
            $this->tree->delete($category);
        } catch (DomainException $e) {
            return back()->withErrors(['admin' => $e->getMessage()]);
        }
        if ($category->image_path) {
            Storage::disk('public')->delete($category->image_path);
        }
        Activity::log('category.deleted', null, ['name' => $category->name]);

        return back()->with('status', '“'.$category->name.'” silindi.');
    }

    /** Aynı düzeydeki kategorilerin sırası (yukarı / aşağı). */
    public function move(Request $request, Category $category): RedirectResponse
    {
        $dir = $request->input('dir') === 'up' ? 'up' : 'down';
        $siblings = Category::where('parent_id', $category->parent_id)->orderBy('position')->orderBy('name')->get()->values();
        $i = $siblings->search(fn ($c) => $c->id === $category->id);
        $j = $dir === 'up' ? $i - 1 : $i + 1;
        if ($i !== false && isset($siblings[$j])) {
            $order = $siblings->all();
            [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
            foreach ($order as $pos => $c) {
                $c->updateQuietly(['position' => $pos + 1]);
            }
            $this->tree->flush();
        }

        return back();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'icon' => ['nullable', 'string', 'max:40'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:170'],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);
        unset($data['parent_id'], $data['image']);
        $data['is_active'] = $request->boolean('is_active');
        $data['show_in_menu'] = $request->boolean('show_in_menu');

        return $data;
    }

    private function storeImage(Request $request, Category $category): void
    {
        if ($request->hasFile('image')) {
            if ($category->image_path) {
                Storage::disk('public')->delete($category->image_path);
            }
            $category->update(['image_path' => $request->file('image')->store('categories', 'public')]);
        } elseif ($request->boolean('remove_image') && $category->image_path) {
            Storage::disk('public')->delete($category->image_path);
            $category->update(['image_path' => null]);
        }
    }
}
