<x-layouts.admin title="Kategoriler">
    <x-slot:actions><a href="{{ route('admin.categories.create') }}" class="btn-primary btn-sm"><x-icon name="plus" class="size-4" />Kategori ekle</a></x-slot:actions>

    <p class="mb-4 text-sm text-muted">Toplu yüklemede bilgi tablosundaki “Türü” yolu (ör. Tarih / Avrupa Tarihi) eksik kategorileri kendiliğinden oluşturur. Buradan ad, sıra ve konumlarını düzenleyebilirsiniz.</p>

    <div class="card overflow-x-auto">
        <table class="table-panel">
            <thead><tr><th>Kategori</th><th class="text-right">Ürün</th><th>Durum</th><th class="w-0"></th></tr></thead>
            <tbody>
                @forelse ($categories as $c)
                    <tr>
                        <td>
                            <div class="flex items-center gap-2" style="padding-left: {{ $c['depth'] * 22 }}px">
                                @if ($c['depth'])<span class="text-slate-300">└</span>@endif
                                <a href="{{ route('admin.categories.edit', $c['id']) }}" class="font-semibold hover:text-primary {{ $c['depth'] ? '' : 'text-base' }}">{{ $c['name'] }}</a>
                            </div>
                        </td>
                        <td class="text-right">
                            <a href="{{ route('admin.products.index', ['kategori' => $c['id']]) }}" class="text-muted hover:text-primary">{{ $counts[$c['id']] ?? 0 }}</a>
                        </td>
                        <td class="whitespace-nowrap">
                            @if (! $c['is_active'])<span class="badge bg-slate-100 text-muted">Gizli</span>@endif
                            @if ($c['is_active'] && ! $c['show_in_menu'])<span class="badge bg-info-soft text-info">Menüde yok</span>@endif
                        </td>
                        <td class="whitespace-nowrap text-right">
                            <form method="post" action="{{ route('admin.categories.move', $c['id']) }}" class="inline">@csrf<input type="hidden" name="dir" value="up"><button class="btn-ghost btn-sm" aria-label="Yukarı">↑</button></form>
                            <form method="post" action="{{ route('admin.categories.move', $c['id']) }}" class="inline">@csrf<input type="hidden" name="dir" value="down"><button class="btn-ghost btn-sm" aria-label="Aşağı">↓</button></form>
                            <a href="{{ route('admin.categories.create', ['parent' => $c['id']]) }}" class="btn-ghost btn-sm"><x-icon name="plus" class="size-3.5" />Alt</a>
                            <a href="{{ route('admin.categories.edit', $c['id']) }}" class="btn-ghost btn-sm"><x-icon name="edit" class="size-3.5" /></a>
                            <form method="post" action="{{ route('admin.categories.destroy', $c['id']) }}" class="inline" data-confirm="“{{ $c['name'] }}” silinsin mi?">@csrf @method('delete')<button class="btn-ghost btn-sm text-danger" aria-label="Sil"><x-icon name="trash" class="size-3.5" /></button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-10 text-center text-muted">Henüz kategori yok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
