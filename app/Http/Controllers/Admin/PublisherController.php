<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Publisher;
use App\Models\PublisherAlias;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Yayınevleri: logo, açıklama, öne çıkarma, takma adlar (içe aktarma eşleştirmesi) ve birleştirme. */
class PublisherController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $publishers = Publisher::query()
            ->withCount('products')
            ->when($q !== '', fn ($w) => $w->where('match_key', 'like', '%'.Publisher::matchKey($q).'%'))
            ->when($request->query('filtre') === 'logosuz', fn ($w) => $w->whereNull('logo_path'))
            ->when($request->query('filtre') === 'one-cikan', fn ($w) => $w->where('is_featured', true))
            ->orderBy('name')->paginate(60)->withQueryString();

        return view('admin.publishers.index', compact('publishers', 'q'));
    }

    public function create(): View
    {
        return view('admin.publishers.form', ['publisher' => new Publisher(['is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        $publisher = Publisher::create($data + ['slug' => Publisher::uniqueSlug($data['name'])]);
        $this->saveExtras($request, $publisher);
        Activity::log('publisher.created', $publisher, ['name' => $publisher->name]);

        return redirect()->route('admin.publishers.index')->with('status', '“'.$publisher->name.'” eklendi.');
    }

    public function edit(Publisher $publisher): View
    {
        return view('admin.publishers.form', [
            'publisher' => $publisher->load('aliases'),
            'others' => Publisher::whereKeyNot($publisher->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Publisher $publisher): RedirectResponse
    {
        $data = $this->validated($request, $publisher);
        $publisher->update($data);
        $this->saveExtras($request, $publisher);
        Activity::log('publisher.updated', $publisher, ['name' => $publisher->name]);

        return redirect()->route('admin.publishers.index')->with('status', '“'.$publisher->name.'” güncellendi.');
    }

    public function destroy(Publisher $publisher): RedirectResponse
    {
        if ($publisher->products()->exists()) {
            return back()->withErrors(['admin' => 'Bu yayınevinin ürünleri var. Silmek yerine başka bir yayınevine birleştirin.']);
        }
        if ($publisher->logo_path) {
            Storage::disk('public')->delete($publisher->logo_path);
        }
        Activity::log('publisher.deleted', null, ['name' => $publisher->name]);
        $publisher->delete();

        return redirect()->route('admin.publishers.index')->with('status', 'Yayınevi silindi.');
    }

    /** Bu yayınevini hedefe birleştirir: ürünler taşınır, adı hedefin takma adı olur, kayıt silinir. */
    public function merge(Request $request, Publisher $publisher): RedirectResponse
    {
        $target = Publisher::findOrFail($request->validate(['target_id' => ['required', 'integer', 'different:'.$publisher->id]])['target_id']);
        DB::transaction(function () use ($publisher, $target) {
            Product::where('publisher_id', $publisher->id)->update(['publisher_id' => $target->id]);
            PublisherAlias::where('publisher_id', $publisher->id)->update(['publisher_id' => $target->id]);
            PublisherAlias::firstOrCreate(['match_key' => $publisher->match_key], ['publisher_id' => $target->id]);
            if (! $target->logo_path && $publisher->logo_path) {
                $target->update(['logo_path' => $publisher->logo_path]);
            } elseif ($publisher->logo_path) {
                Storage::disk('public')->delete($publisher->logo_path);
            }
            $publisher->delete();
        });
        // Ürünlerin arama metni yeni yayınevi adıyla güncellenir.
        Product::where('publisher_id', $target->id)->chunkById(500, fn ($ps) => $ps->each(fn ($p) => $p->forceFill(['search_text' => $p->buildSearchText()])->saveQuietly()));
        Activity::log('publisher.merged', $target, ['from' => $publisher->name]);

        return redirect()->route('admin.publishers.edit', $target)->with('status', '“'.$publisher->name.'” bu yayınevine birleştirildi.');
    }

    private function validated(Request $request, ?Publisher $publisher): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'position' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:170'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'aliases' => ['nullable', 'string', 'max:2000'],
        ]);
        $key = Publisher::matchKey($data['name']);
        $clash = Publisher::where('match_key', $key)->when($publisher, fn ($q) => $q->whereKeyNot($publisher->id))->exists()
            || PublisherAlias::where('match_key', $key)->when($publisher, fn ($q) => $q->where('publisher_id', '!=', $publisher->id))->exists();
        if ($clash) {
            throw ValidationException::withMessages(['name' => 'Bu adla eşleşen bir yayınevi zaten var.']);
        }
        unset($data['logo'], $data['aliases']);

        return $data + ['match_key' => $key, 'position' => (int) ($data['position'] ?? 0),
            'is_active' => $request->boolean('is_active'), 'is_featured' => $request->boolean('is_featured')];
    }

    private function saveExtras(Request $request, Publisher $publisher): void
    {
        if ($request->hasFile('logo')) {
            if ($publisher->logo_path) {
                Storage::disk('public')->delete($publisher->logo_path);
            }
            $publisher->update(['logo_path' => $request->file('logo')->store('publishers', 'public')]);
        } elseif ($request->boolean('remove_logo') && $publisher->logo_path) {
            Storage::disk('public')->delete($publisher->logo_path);
            $publisher->update(['logo_path' => null]);
        }

        // Takma adlar: her satır bir ad. Başka yayınevine ait olanlar atlanır.
        $keys = collect(preg_split('/\r?\n/', (string) $request->input('aliases')))
            ->map(fn ($a) => Publisher::matchKey($a))->filter()->unique()->reject(fn ($k) => $k === $publisher->match_key);
        $taken = Publisher::whereIn('match_key', $keys)->whereKeyNot($publisher->id)->pluck('match_key')
            ->merge(PublisherAlias::whereIn('match_key', $keys)->where('publisher_id', '!=', $publisher->id)->pluck('match_key'));
        $keys = $keys->diff($taken);
        PublisherAlias::where('publisher_id', $publisher->id)->whereNotIn('match_key', $keys)->delete();
        foreach ($keys as $k) {
            PublisherAlias::firstOrCreate(['match_key' => $k], ['publisher_id' => $publisher->id]);
        }
    }
}
