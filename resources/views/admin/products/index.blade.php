<x-layouts.admin title="Ürünler">
    <x-slot:actions>
        <a href="{{ route('admin.products.create') }}" class="btn-primary btn-sm"><x-icon name="plus" class="size-4" />Ürün ekle</a>
    </x-slot:actions>

    <div class="mb-5 grid gap-3 sm:grid-cols-3 xl:grid-cols-5">
        @foreach ([
            ['Toplam', $stats['total'], []],
            ['Yayında', $stats['active'], ['durum' => 'active']],
            ['Taslak', $stats['draft'], ['durum' => 'draft']],
            ['Fiyatı eksik', $stats['missing_price'], ['fiyat' => 'eksik']],
            ['Görseli eksik', $stats['missing_image'], ['gorsel' => 'eksik']],
        ] as [$label, $value, $query])
            <a href="{{ route('admin.products.index', $query) }}" class="card px-4 py-3 transition hover:shadow-lift">
                <p class="text-xs text-muted">{{ $label }}</p>
                <p class="font-display text-xl font-bold">{{ number_format($value, 0, ',', '.') }}</p>
            </a>
        @endforeach
    </div>

    {{-- Excel fiyat şablonu (K2) --}}
    <div class="card mb-5 space-y-4 p-5">
        <div>
            <p class="font-semibold">Excel ile toplu fiyat</p>
            <p class="text-sm text-muted">Şablon, aşağıdaki filtreye uyan ürünleri içerir. Yalnızca sarı fiyat sütunlarını doldurun; boş hücre değişmez, “-” fiyatı siler.</p>
            @error('file')<p class="field-error">{{ $message }}</p>@enderror
            @if (session('import_errors'))
                <details class="mt-2 text-sm text-danger"><summary class="cursor-pointer font-semibold">{{ count(session('import_errors')) }} satır işlenemedi</summary>
                    <ul class="mt-2 list-disc space-y-0.5 pl-5 text-xs">@foreach (session('import_errors') as $e)<li>{{ $e }}</li>@endforeach</ul>
                </details>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.products.price-template', request()->query()) }}" class="btn-ghost shrink-0"><x-icon name="external" class="size-4" />Şablonu indir</a>
        <form method="post" action="{{ route('admin.products.price-import') }}" enctype="multipart/form-data" class="flex shrink-0 flex-wrap items-center gap-2">
            @csrf
            <input type="file" name="file" accept=".xlsx" required class="max-w-[14rem] text-sm file:mr-2 file:rounded-lg file:border-0 file:bg-primary-soft file:px-3 file:py-1.5 file:font-semibold file:text-primary-dark">
            <label class="flex items-center gap-1.5 text-xs"><input type="checkbox" name="publish" value="1" checked class="accent-primary"> Fiyatı girilen taslakları yayına al</label>
            <button class="btn-primary btn-sm"><x-icon name="upload" class="size-4" />Fiyatları yükle</button>
        </form>
        </div>
    </div>

    <form method="get" class="mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-6">
        <input name="q" value="{{ request('q') }}" placeholder="Ad, ISBN, kod, yazar" class="field-input lg:col-span-2">
        <select name="yayinevi" class="field-input" data-autosubmit>
            <option value="">Tüm yayınevleri</option>
            @foreach ($publishers as $p)<option value="{{ $p->id }}" @selected((int) request('yayinevi') === $p->id)>{{ $p->name }}</option>@endforeach
        </select>
        <select name="kategori" class="field-input" data-autosubmit>
            <option value="">Tüm kategoriler</option>
            <option value="0" @selected(request('kategori') === '0')>— Kategorisiz —</option>
            @foreach ($categories as $c)<option value="{{ $c['id'] }}" @selected((string) request('kategori') === (string) $c['id'])>{{ str_repeat('— ', $c['depth']) }}{{ $c['name'] }}</option>@endforeach
        </select>
        <select name="durum" class="field-input" data-autosubmit>
            <option value="">Tüm durumlar</option>
            @foreach (\App\Models\Product::STATUSES as $k => $l)<option value="{{ $k }}" @selected(request('durum') === $k)>{{ $l }}</option>@endforeach
        </select>
        <div class="flex gap-2">
            <select name="fiyat" class="field-input" data-autosubmit>
                <option value="">Fiyat: tümü</option>
                <option value="eksik" @selected(request('fiyat') === 'eksik')>Fiyatı eksik</option>
                <option value="var" @selected(request('fiyat') === 'var')>Fiyatı var</option>
            </select>
            <button class="btn-ghost"><x-icon name="search" class="size-4" /></button>
        </div>
    </form>

    <form method="post" action="{{ route('admin.products.bulk') }}" id="bulk-form" x-data="bulkSelect" data-confirm="Seçilen ürünlere işlem uygulansın mı?">
        @csrf
        <div class="card overflow-x-auto">
            <div class="flex flex-wrap items-center gap-2 border-b border-line bg-surface px-4 py-2.5">
                <span class="text-sm text-muted"><span x-text="count">0</span> seçili</span>
                <select name="action" class="field-input w-auto py-1.5 text-sm" required>
                    <option value="">Toplu işlem…</option>
                    <option value="active">Yayına al</option>
                    <option value="passive">Pasif yap</option>
                    <option value="draft">Taslağa al</option>
                    <option value="feature">Öne çıkar</option>
                    <option value="unfeature">Öne çıkarmayı kaldır</option>
                    <option value="category">Kategoriye taşı</option>
                    <option value="delete">Sil</option>
                </select>
                <select name="category_id" class="field-input w-auto max-w-xs py-1.5 text-sm">
                    <option value="">(Taşınacak kategori)</option>
                    @foreach ($categories as $c)<option value="{{ $c['id'] }}">{{ str_repeat('— ', $c['depth']) }}{{ $c['name'] }}</option>@endforeach
                </select>
                <button class="btn-ghost btn-sm">Uygula</button>
                @error('ids')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <table class="table-panel">
                <thead>
                    <tr>
                        <th class="w-0"><input type="checkbox" x-on:change="toggleAll" class="size-4 accent-primary" aria-label="Tümünü seç"></th>
                        <th class="w-0"></th><th>Ürün</th><th>Yayınevi</th><th>Kategori</th><th class="text-right">S-B</th><th class="text-right">Renkli</th><th>Durum</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $p)
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="{{ $p->id }}" x-on:change="recount" class="size-4 accent-primary" aria-label="Seç"></td>
                            <td>
                                @if ($p->image_key)<img src="{{ $p->imageUrl('sm') }}" alt="" class="h-14 w-10 rounded object-cover" loading="lazy">
                                @else<span class="flex h-14 w-10 items-center justify-center rounded bg-surface text-slate-300"><x-icon name="image" class="size-4" /></span>@endif
                            </td>
                            <td class="max-w-sm">
                                <a href="{{ route('admin.products.edit', $p) }}" class="line-clamp-2 font-semibold hover:text-primary">{{ $p->title }}</a>
                                <span class="text-xs text-muted">{{ $p->author?->name }}{{ $p->isbn ? ' · '.$p->isbn : '' }}{{ $p->code ? ' · '.$p->code : '' }}</span>
                            </td>
                            <td class="text-muted">{{ $p->publisher?->name ?? '—' }}</td>
                            <td class="text-muted">{{ $p->category?->name ?? '—' }}</td>
                            <td class="text-right whitespace-nowrap">{{ $p->price_bw !== null ? \App\Support\Money::format($p->price_bw) : '—' }}</td>
                            <td class="text-right whitespace-nowrap">{{ $p->price_color !== null ? \App\Support\Money::format($p->price_color) : '—' }}</td>
                            <td class="whitespace-nowrap">
                                <span class="badge {{ ['active' => 'bg-success-soft text-success', 'draft' => 'bg-warning-soft text-warning', 'passive' => 'bg-slate-100 text-muted'][$p->status] }}">{{ \App\Models\Product::STATUSES[$p->status] }}</span>
                                @if ($p->is_featured)<span class="badge bg-accent-soft text-accent-ink">★</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-10 text-center text-muted">Ürün bulunamadı.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>
    <div class="mt-6">{{ $products->links() }}</div>
</x-layouts.admin>
