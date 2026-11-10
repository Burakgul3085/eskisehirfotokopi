<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Yönetim › Sayfalar: kurumsal ve yasal sayfalar, alt bilgi sütunları. Sistem sayfaları silinemez. */
class PageController extends Controller
{
    public function index(): View
    {
        return view('admin.pages.index', ['pages' => Page::orderByRaw('footer_column is null')->orderBy('footer_column')->orderBy('position')->get()]);
    }

    public function create(): View
    {
        return view('admin.pages.form', ['page' => new Page(['is_active' => true, 'footer_column' => 'corporate'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $page = Page::create($this->validated($request) + ['position' => (int) Page::max('position') + 1]);
        Activity::log('page.created', $page);

        return redirect()->route('admin.pages.index')->with('status', $page->title.' eklendi.');
    }

    public function edit(Page $page): View
    {
        return view('admin.pages.form', ['page' => $page]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $page->update($this->validated($request, $page));
        Activity::log('page.updated', $page);

        return redirect()->route('admin.pages.edit', $page)->with('status', 'Sayfa kaydedildi.');
    }

    public function destroy(Page $page): RedirectResponse
    {
        abort_if($page->isSystem(), 403, 'Sistem sayfası silinemez; isterseniz gizleyin.');
        $page->delete();
        Activity::log('page.deleted', null, ['title' => $page->title]);

        return redirect()->route('admin.pages.index')->with('status', 'Sayfa silindi.');
    }

    private function validated(Request $request, ?Page $page = null): array
    {
        $request->merge(['slug' => Str::slug($request->input('slug') ?: $request->input('title'))]);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:180', Rule::unique('pages', 'slug')->ignore($page?->id)],
            'body' => ['nullable', 'string', 'max:100000'],
            'meta_description' => ['nullable', 'string', 'max:170'],
            'footer_column' => ['nullable', Rule::in(array_keys(Page::COLUMNS))],
            'position' => ['nullable', 'integer', 'between:0,999'],
            'is_active' => ['required', 'boolean'],
        ]);
        $data['position'] ??= $page?->position ?? 0;

        return $data;
    }
}
