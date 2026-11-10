<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Product;
use App\Support\Activity;
use App\Support\Text;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Yazarlar içe aktarmada kendiliğinden oluşur; burada ad düzeltilir veya iki kayıt birleştirilir. */
class AuthorController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $authors = Author::withCount('products')
            ->when($q !== '', fn ($w) => $w->where('match_key', 'like', '%'.Text::key($q).'%'))
            ->orderBy('name')->paginate(80)->withQueryString();

        return view('admin.authors.index', compact('authors', 'q'));
    }

    public function update(Request $request, Author $author): RedirectResponse
    {
        $name = trim($request->validate(['name' => ['required', 'string', 'max:150']])['name']);
        $existing = Author::where('match_key', Text::key($name))->whereKeyNot($author->id)->first();
        if ($existing) {
            // Aynı ada sahip başka kayıt varsa birleştirilir.
            DB::transaction(function () use ($author, $existing) {
                Product::where('author_id', $author->id)->update(['author_id' => $existing->id]);
                $author->delete();
            });
            $target = $existing;
        } else {
            $author->update(['name' => $name, 'match_key' => Text::key($name)]);
            $target = $author;
        }
        Product::where('author_id', $target->id)->chunkById(500, fn ($ps) => $ps->each(fn ($p) => $p->forceFill(['search_text' => $p->buildSearchText()])->saveQuietly()));
        Activity::log('author.updated', $target, ['name' => $target->name]);

        return back()->with('status', $existing ? 'Yazar, aynı adlı kayıtla birleştirildi.' : 'Yazar adı güncellendi.');
    }
}
