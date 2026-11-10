<x-layouts.admin title="Ana sayfa düzeni">
    <x-slot:actions>
        <a href="{{ route('home') }}" target="_blank" class="btn-ghost btn-sm"><x-icon name="external" class="size-4" />Siteyi aç</a>
        <div x-data="toggle" class="relative">
            <button type="button" x-on:click="toggle" class="btn-primary btn-sm"><x-icon name="plus" class="size-4" />Blok ekle</button>
            <div x-show="open" x-cloak x-on:click.outside="close" class="absolute right-0 z-30 mt-2 w-72 rounded-xl border border-line bg-white p-1.5 shadow-lift">
                @foreach (\App\Models\HomeBlock::TYPES as $type => $label)
                    <a href="{{ route('admin.home-blocks.create', ['tur' => $type]) }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-surface">{{ $label }}</a>
                @endforeach
            </div>
        </div>
    </x-slot:actions>

    <p class="mb-4 text-sm text-muted">Bloklar ana sayfada bu sırayla görünür. İçeriği olmayan blok (ör. ürünü olmayan kategori satırı) gösterilmez. Değişiklikler hemen yansır.</p>

    <div class="card overflow-x-auto">
        <table class="table-panel">
            <thead><tr><th class="w-0">#</th><th>Blok</th><th>Tür</th><th>Durum</th><th class="w-0"></th></tr></thead>
            <tbody>
                @forelse ($blocks as $b)
                    <tr class="{{ $b->is_active ? '' : 'opacity-60' }}">
                        <td class="text-muted">{{ $loop->iteration }}</td>
                        <td><a href="{{ route('admin.home-blocks.edit', $b) }}" class="font-semibold hover:text-primary">{{ $b->title ?: ($b->setting('heading') ?: $b->typeLabel()) }}</a>
                            @if ($b->type === 'products')<span class="block text-xs text-muted">{{ \App\Models\HomeBlock::PRODUCT_SOURCES[$b->setting('source')] ?? '' }}</span>@endif</td>
                        <td class="text-muted">{{ $b->typeLabel() }}</td>
                        <td>
                            <form method="post" action="{{ route('admin.home-blocks.toggle', $b) }}">@csrf
                                <button class="badge {{ $b->is_active ? 'bg-success-soft text-success' : 'bg-slate-100 text-muted' }}">{{ $b->is_active ? 'Görünür' : 'Gizli' }}</button>
                            </form>
                        </td>
                        <td class="whitespace-nowrap text-right">
                            <form method="post" action="{{ route('admin.home-blocks.move', $b) }}" class="inline">@csrf<input type="hidden" name="yon" value="yukari"><button class="btn-ghost btn-sm" aria-label="Yukarı" @disabled($loop->first)>↑</button></form>
                            <form method="post" action="{{ route('admin.home-blocks.move', $b) }}" class="inline">@csrf<input type="hidden" name="yon" value="asagi"><button class="btn-ghost btn-sm" aria-label="Aşağı" @disabled($loop->last)>↓</button></form>
                            <a href="{{ route('admin.home-blocks.edit', $b) }}" class="btn-ghost btn-sm"><x-icon name="edit" class="size-3.5" /></a>
                            <form method="post" action="{{ route('admin.home-blocks.destroy', $b) }}" class="inline" data-confirm="Blok silinsin mi?">@csrf @method('delete')<button class="btn-ghost btn-sm text-danger" aria-label="Sil"><x-icon name="trash" class="size-3.5" /></button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-10 text-center text-muted">Blok yok. “Blok ekle” ile başlayın.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
