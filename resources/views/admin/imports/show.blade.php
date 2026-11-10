<x-layouts.admin :title="$batch->name">
    <x-slot:actions>
        @if ($counts['failed'])
            <form method="post" action="{{ route('admin.imports.retry-failed', $batch) }}">@csrf<button class="btn-ghost btn-sm">Hatalıları yeniden işle</button></form>
        @endif
        <form method="post" action="{{ route('admin.imports.destroy', $batch) }}" data-confirm="Yükleme kaydı ve bilgi görselleri silinsin mi? Ürünler silinmez.">
            @csrf @method('delete')<button class="btn-danger btn-sm"><x-icon name="trash" class="size-4" />Kaydı sil</button>
        </form>
    </x-slot:actions>


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

    <div class="table-panel">
        <table class="w-full text-sm">
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
