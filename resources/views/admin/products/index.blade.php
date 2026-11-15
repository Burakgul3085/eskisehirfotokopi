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
            <p class="font-semibold">Excel ile toplu sayfa sayısı</p>
            <p class="text-sm text-muted">Şablon, aşağıdaki filtreye uyan ürünleri içerir. Sarı <strong>Sayfa</strong> sütununa PDF sayfa sayısını yazın; fiyatlar fotokopi kademelerinden otomatik hesaplanır (fiyat sütunları yalnız bilgi içindir). Boş hücre değişmez, “-” siler. Az sayıda ürün için aşağıdaki tablodaki Sayfa kutuları da kullanılabilir.</p>
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
            <label class="flex items-center gap-1.5 text-xs"><input type="checkbox" name="publish" value="1" checked class="accent-primary"> Fiyatı oluşan taslakları yayına al</label>
            <button class="btn-primary btn-sm"><x-icon name="upload" class="size-4" />Şablonu yükle</button>
        </form>
        </div>
    </div>

    @if ($lastMove)
        <div class="card mb-4 flex flex-wrap items-center gap-3 bg-info-soft px-4 py-3 text-sm">
            <span>Son toplu taşıma: <strong>{{ $lastMove->count }} ürün</strong> → <strong>{{ $lastMove->target_name }}</strong>
                <span class="text-muted">({{ $lastMove->created_at->format('d.m.Y H:i') }}{{ $lastMove->created_category_id ? ', yeni kategori' : '' }}{{ $lastMove->rule_id ? ', kural kaydedildi' : '' }})</span></span>
            <form method="post" action="{{ route('admin.products.undo-move', $lastMove) }}" class="ml-auto"
                  data-confirm="{{ $lastMove->count }} ürün eski kategorisine döndürülsün mü?{{ $lastMove->rule_id ? ' Bu taşımayla kaydedilen kural da geri alınır.' : '' }}{{ $lastMove->created_category_id ? ' Bu taşıma için açılan kategori boş kalırsa silinir.' : '' }}">
                @csrf
                <button class="btn-ghost btn-sm">Geri al</button>
            </form>
        </div>
    @endif

    <form method="get" class="mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
        @if (request('gorsel'))<input type="hidden" name="gorsel" value="{{ request('gorsel') }}">@endif
        <input name="q" value="{{ request('q') }}" placeholder="Ad, ISBN, kod, yazar, ders" class="field-input lg:col-span-2">
        <select name="yayinevi" class="field-input" data-autosubmit>
            <option value="">Tüm yayınevleri</option>
            @foreach ($publishers as $p)<option value="{{ $p->id }}" @selected((int) request('yayinevi') === $p->id)>{{ $p->name }}</option>@endforeach
        </select>
        <select name="kategori" class="field-input" data-autosubmit>
            <option value="">Tüm kategoriler</option>
            <option value="0" @selected(request('kategori') === '0')>— Kategorisiz —</option>
            @foreach ($categories as $c)<option value="{{ $c['id'] }}" @selected((string) request('kategori') === (string) $c['id'])>{{ str_repeat('— ', $c['depth']) }}{{ $c['name'] }}</option>@endforeach
        </select>
        <select name="ders" class="field-input" data-autosubmit aria-label="Ders">
            <option value="">Tüm dersler</option>
            @foreach ($subjects as $v)<option value="{{ $v }}" @selected(request('ders') === $v)>{{ $v }}</option>@endforeach
        </select>
        <select name="tur" class="field-input" data-autosubmit aria-label="Kitap türü">
            <option value="">Tüm kitap türleri</option>
            @foreach ($bookTypes as $v)<option value="{{ $v }}" @selected(request('tur') === $v)>{{ $v }}</option>@endforeach
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

    <form method="post" action="{{ route('admin.products.bulk') }}" id="bulk-form" x-data="productBulk" x-on:change="refresh" x-on:input="refresh" data-total="{{ $products->total() }}">
        @csrf
        {{-- "Aramaya uyan tümü" seçilince sunucu aynı süzgeçleri uygular; expected onaylanan sayıdır --}}
        <input type="hidden" name="scope" x-bind:value="scope" value="selected">
        <input type="hidden" name="expected" value="{{ $products->total() }}">
        @foreach ($filters as $k => $v)<input type="hidden" name="f[{{ $k }}]" value="{{ $v }}">@endforeach
        <div class="card overflow-x-auto">
            <div class="flex flex-wrap items-center gap-2 border-b border-line bg-surface px-4 py-2.5">
                <span class="text-sm text-muted"><span x-text="selectedCount">0</span> seçili</span>
                <select name="action" class="field-input w-auto py-1.5 text-sm" required>
                    <option value="">Toplu işlem…</option>
                    <option value="active">Yayına al</option>
                    <option value="passive">Pasif yap</option>
                    <option value="draft">Taslağa al</option>
                    <option value="feature">Öne çıkar</option>
                    <option value="unfeature">Öne çıkarmayı kaldır</option>
                    <option value="category">Kategoriye taşı</option>
                    <option value="spec_show">Bilgiyi müşteriye göster</option>
                    <option value="spec_hide">Bilgiyi müşteriden gizle</option>
                    <option value="spec_default">Bilgi gösterimini genel ayara bırak</option>
                    <option value="delete">Sil</option>
                </select>
                @php($byId = $categories->keyBy('id'))
                <select name="category_id" class="field-input w-auto max-w-xs py-1.5 text-sm" aria-label="Taşınacak kategori">
                    <option value="">(Taşınacak kategori)</option>
                    <option value="new" @selected(old('category_id') === 'new')>+ Yeni kategori…</option>
                    @foreach ($categories as $c)<option value="{{ $c['id'] }}" data-full="{{ ! empty($c['parent_id']) && isset($byId[$c['parent_id']]) ? $byId[$c['parent_id']]['name'].' › ' : '' }}{{ $c['name'] }}" @selected((string) old('category_id') === (string) $c['id'])>{{ str_repeat('— ', $c['depth']) }}{{ $c['name'] }}</option>@endforeach
                </select>
                <span class="flex flex-wrap items-center gap-2" x-show="isNew" x-cloak>
                    <select name="new_category_parent" class="field-input w-auto py-1.5 text-sm" aria-label="Yeni kategorinin ana kategorisi">
                        @foreach ($groups as $g)<option value="{{ $g->id }}" @selected((int) old('new_category_parent', $defaultGroup) === $g->id)>{{ $g->name }}</option>@endforeach
                    </select>
                    <span class="text-muted">›</span>
                    <input name="new_category_name" value="{{ old('new_category_name') }}" maxlength="100" placeholder="Yeni kategori adı (ör. Biyoloji)" class="field-input w-56 py-1.5 text-sm" aria-label="Yeni kategori adı">
                </span>
                @error('category_id')<span class="field-error">{{ $message }}</span>@enderror
                @error('new_category_name')<span class="field-error">{{ $message }}</span>@enderror
                @error('new_category_parent')<span class="field-error">{{ $message }}</span>@enderror
                <select name="spec" class="field-input w-auto max-w-xs py-1.5 text-sm" aria-label="Bilgi">
                    <option value="">(Gösterilecek/gizlenecek bilgi)</option>
                    @foreach ($specOptions as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                </select>
                @error('spec')<span class="field-error">{{ $message }}</span>@enderror
                <button class="btn-ghost btn-sm">Uygula</button>
                @error('ids')<span class="field-error">{{ $message }}</span>@enderror
                @error('save_rule')<span class="field-error w-full">{{ $message }}</span>@enderror
                <div class="w-full text-sm" x-show="isMove" x-cloak>
                    @if ($ruleConditions)
                        <label class="flex items-start gap-2">
                            <input type="checkbox" name="save_rule" value="1" class="mt-0.5 size-4 accent-primary" @checked(old('save_rule'))>
                            <span>Bundan sonra yüklenen benzer kitaplar da buraya gitsin <span class="text-muted">— kural: {{ \App\Models\PlacementRule::describe($ruleConditions) }}. Otomatik yerleştirmeden (sınav adı dahil) önce uygulanır; Kategoriler sayfasından silinebilir.</span></span>
                        </label>
                    @else
                        <p class="text-muted">Bundan sonra gelen benzer kitapların da buraya gitmesi için önce arama yapın ya da Ders, Kitap türü, Yayınevi, Kategori süzgeçlerinden birini seçin; kural o aramayı saklar.</p>
                    @endif
                </div>
                {{-- Sayfa sayısı kutuları ayrı forma (products-pages) bağlıdır; formlar iç içe olamaz. --}}
                <span class="ml-auto flex flex-wrap items-center gap-2">
                    <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" name="publish" value="1" form="products-pages" checked class="size-4 accent-primary"> Taslakları yayına al</label>
                    <button form="products-pages" class="btn-primary btn-sm"><x-icon name="check" class="size-4" />Sayfa sayılarını kaydet</button>
                </span>
                @if ($errors->has('pages') || $errors->has('pages.*'))<span class="field-error w-full">{{ $errors->first('pages') ?: collect($errors->get('pages.*'))->flatten()->first() }}</span>@endif
            </div>
            <div class="border-b border-line bg-primary-soft px-4 py-2 text-sm" x-show="showSelectAll" x-cloak>
                Bu sayfadaki <span x-text="count"></span> ürün seçili.
                <button type="button" class="font-semibold text-primary-dark underline" x-on:click="selectAllMatching">Aramaya uyan tüm {{ number_format($products->total(), 0, ',', '.') }} ürünü seç</button>
            </div>
            <div class="border-b border-line bg-primary-soft px-4 py-2 text-sm" x-show="allMatching" x-cloak>
                Aramaya uyan <strong>tüm {{ number_format($products->total(), 0, ',', '.') }} ürün</strong> seçili (yalnız bu sayfadakiler değil).
                <button type="button" class="font-semibold text-primary-dark underline" x-on:click="clearAllMatching">Seçimi temizle</button>
            </div>
            <table class="table-panel">
                <thead>
                    <tr>
                        <th class="w-0"><input type="checkbox" x-on:change="toggleAll" class="size-4 accent-primary" aria-label="Tümünü seç"></th>
                        <th class="w-0"></th><th>Ürün</th><th>Yayınevi</th><th>Kategori</th><th class="w-0" title="PDF sayfa sayısı; fiyat buradan hesaplanır">Sayfa</th><th class="text-right" title="Önlü arkalı 1'e 1">S-B</th><th class="text-right" title="Önlü arkalı 1'e 1">Renkli</th><th>Durum</th>
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
                            <td class="text-muted">{{ $p->category?->name ?? '—' }}@if ($p->subject || $p->book_type)<span class="block text-xs">{{ implode(' · ', array_filter([$p->subject, $p->book_type])) }}</span>@endif</td>
                            <td><input type="number" name="pages[{{ $p->id }}]" value="{{ $p->page_count }}" min="1" max="20000" form="products-pages" class="field-input w-24 py-1.5 text-sm" aria-label="{{ $p->title }} sayfa sayısı" placeholder="Sayfa"></td>
                            <td class="text-right whitespace-nowrap">{{ $p->price_bw !== null ? \App\Support\Money::format($p->price_bw) : '—' }}</td>
                            <td class="text-right whitespace-nowrap">{{ $p->price_color !== null ? \App\Support\Money::format($p->price_color) : '—' }}</td>
                            <td class="whitespace-nowrap">
                                <span class="badge {{ ['active' => 'bg-success-soft text-success', 'draft' => 'bg-warning-soft text-warning', 'passive' => 'bg-slate-100 text-muted'][$p->status] }}">{{ \App\Models\Product::STATUSES[$p->status] }}</span>
                                @if ($p->is_featured)<span class="badge bg-accent-soft text-accent-ink">★</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="py-10 text-center text-muted">Ürün bulunamadı.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>
    <form method="post" action="{{ route('admin.products.pages') }}" id="products-pages">@csrf</form>
    <div class="mt-6">{{ $products->links() }}</div>
</x-layouts.admin>
