<x-layouts.admin :title="$batch->name">
    <x-slot:actions>
        @if ($counts['review'])
            <form method="post" action="{{ route('admin.imports.approve-all', $batch) }}" data-confirm="Kontrol bekleyen {{ $counts['review'] }} kitap değiştirilmeden onaylansın mı? Ürünler taslak kalır.">
                @csrf<button class="btn-primary btn-sm"><x-icon name="check" class="size-4" />Hepsini onayla ({{ $counts['review'] }})</button>
            </form>
        @endif
        @if ($counts['failed'])
            <form method="post" action="{{ route('admin.imports.retry-failed', $batch) }}">@csrf<button class="btn-ghost btn-sm">Hatalıları yeniden işle</button></form>
        @endif
        <form method="post" action="{{ route('admin.imports.destroy', $batch) }}" data-confirm="Yükleme kaydı ve bilgi görselleri silinsin mi? Ürünler silinmez.">
            @csrf @method('delete')<button class="btn-danger btn-sm"><x-icon name="trash" class="size-4" />Kaydı sil</button>
        </form>
    </x-slot:actions>

    <x-flash class="mb-5" />

    @if ($newCategories->isNotEmpty())
        <section class="card mb-5 p-5">
            <h2 class="font-bold">Bu yüklemede açılan yeni kategoriler ({{ $newCategories->count() }})</h2>
            <p class="mt-1 text-sm text-muted">Var olan kategoriler yeniden açılmadı; aşağıdakiler ilk kez oluşturuldu. Doğruysa “Doğru” deyin; okuma hatasıyla açıldıysa doğru kategoriye katın (ürünleri ve alt kategorileri taşınır).</p>
            @error('into')<p class="field-error mt-2">{{ $message }}</p>@enderror
            <ul class="mt-4 divide-y divide-line">
                @foreach ($newCategories as $cat)
                    <li class="flex flex-wrap items-center gap-3 py-2.5">
                        <span class="min-w-0 flex-1">
                            <span class="font-medium">{{ $cat->fullName() }}</span>
                            <span class="text-xs text-muted">· {{ $cat->products_count }} ürün</span>
                        </span>
                        <form method="post" action="{{ route('admin.imports.category.check', [$batch, $cat]) }}">@csrf<button class="btn-ghost btn-sm"><x-icon name="check" class="size-4" />Doğru</button></form>
                        <form method="post" action="{{ route('admin.imports.category.merge', [$batch, $cat]) }}" class="flex items-center gap-2" data-confirm="“{{ $cat->name }}” seçilen kategoriye katılsın mı? Ürünleri ve alt kategorileri taşınır, kendisi silinir.">
                            @csrf
                            <select name="into" class="field-input w-auto max-w-xs py-1.5 text-sm" required>
                                <option value="">Şuna kat…</option>
                                @foreach ($categories as $c)
                                    @if ($c['id'] !== $cat->id)<option value="{{ $c['id'] }}">{{ str_repeat('— ', $c['depth']) }}{{ $c['name'] }}</option>@endif
                                @endforeach
                            </select>
                            <button class="btn-ghost btn-sm">Birleştir</button>
                        </form>
                        <a href="{{ route('admin.categories.edit', $cat) }}" class="text-sm text-primary hover:underline">Adını düzelt</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($items->total() && $counts['imported'] + $counts['review'] > 0)
        <form method="post" action="{{ route('admin.imports.specs', $batch) }}" class="card mb-5 flex flex-wrap items-center gap-2 p-4 text-sm">
            @csrf
            <span class="font-semibold">Bu yüklemedeki tüm ürünlerde:</span>
            <select name="spec" class="field-input w-auto py-1.5 text-sm" required>
                <option value="">Bilgi seçin…</option>
                @foreach ($specOptions as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
            </select>
            <select name="state" class="field-input w-auto py-1.5 text-sm">
                <option value="hide">müşteriden gizle</option>
                <option value="show">müşteriye göster</option>
                <option value="default">genel ayara bırak</option>
            </select>
            <button class="btn-ghost btn-sm">Uygula</button>
            @error('spec')<span class="field-error">{{ $message }}</span>@enderror
            <span class="text-xs text-muted">Tek ürün için ürün sayfasını, tüm site için Ayarlar › Ürün bilgileri'ni kullanın.</span>
        </form>
    @endif

    <div class="mb-5 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('admin.imports.show', $batch) }}" class="badge {{ $status ? 'bg-white text-muted ring-1 ring-line' : 'bg-primary text-white' }} px-3 py-1.5">Tümü ({{ array_sum($counts) }})</a>
        @foreach (\App\Models\ImportItem::STATUSES as $k => $label)
            @if ($counts[$k])
                <a href="{{ route('admin.imports.show', [$batch, 'durum' => $k]) }}" class="badge px-3 py-1.5 {{ $status === $k ? 'bg-primary text-white' : \App\Models\ImportItem::tone($k) }}">{{ $label }} ({{ $counts[$k] }})</a>
            @endif
        @endforeach
        @if ($counts['review'])
            <a href="{{ route('admin.imports.review', $batch->items()->where('status', 'review')->orderBy('id')->value('id')) }}" class="btn-accent btn-sm ml-auto">Kontrole başla</a>
        @endif
    </div>

    <div class="card overflow-x-auto">
        <table class="table-panel">
            <thead><tr class="text-left text-xs text-muted"><th class="px-4 py-3">Klasör</th><th class="px-4 py-3">Durum</th><th class="px-4 py-3">Not</th><th class="px-4 py-3"></th></tr></thead>
            <tbody>
                @forelse ($items as $item)
                    <tr class="border-t border-line align-top">
                        <td class="px-4 py-3">
                            <span class="block font-medium">{{ $item->folder }}</span>
                            <span class="text-xs text-muted">{{ $item->publisher_folder }}</span>
                        </td>
                        <td class="px-4 py-3"><span class="badge {{ \App\Models\ImportItem::tone($item->status) }}">{{ $item->statusLabel() }}</span>
                            @if ($item->action === 'updated')<span class="badge bg-slate-100 text-muted">güncellendi</span>@endif</td>
                        <td class="px-4 py-3 text-xs">
                            @if ($item->error)<p class="text-danger">{{ $item->error }}</p>@endif
                            @foreach ($item->issues ?? [] as $issue)<p class="text-warning">• {{ $issue }}</p>@endforeach
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            @if ($item->product)<a href="{{ route('admin.products.edit', $item->product) }}" class="text-xs font-semibold text-primary hover:underline">Ürün</a>@endif
                            @if (in_array($item->status, ['review', 'imported']) && $item->product_id)
                                <a href="{{ route('admin.imports.review', $item) }}" class="ml-2 text-xs font-semibold text-primary hover:underline">Kontrol</a>
                            @endif
                            @if ($item->status === 'failed')
                                <form method="post" action="{{ route('admin.imports.retry', $item) }}" class="inline">@csrf<button class="ml-2 text-xs font-semibold text-primary hover:underline">Tekrar dene</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-muted">Kayıt yok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $items->links() }}</div>
</x-layouts.admin>
