<x-layouts.admin title="Toplu yükleme">
    <x-slot:actions><a href="{{ route('admin.imports.create') }}" class="btn-primary btn-sm"><x-icon name="upload" class="size-4" />Klasör yükle</a></x-slot:actions>

    @if ($reviewTotal || $failedTotal)
        <div class="mb-5 flex flex-wrap gap-3 text-sm">
            @if ($reviewTotal)<p class="rounded-xl bg-warning-soft px-4 py-3 text-warning"><b>{{ number_format($reviewTotal, 0, ',', '.') }}</b> kitap kontrol bekliyor.</p>@endif
            @if ($failedTotal)<p class="rounded-xl bg-danger-soft px-4 py-3 text-danger"><b>{{ number_format($failedTotal, 0, ',', '.') }}</b> kitap işlenemedi.</p>@endif
        </div>
    @endif

    <div class="table-panel">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-xs text-muted">
                <th class="px-4 py-3">Yükleme</th><th class="px-4 py-3">Durum</th><th class="px-4 py-3 text-right">Kitap</th>
                <th class="px-4 py-3 text-right">Eklendi</th><th class="px-4 py-3 text-right">Kontrol</th><th class="px-4 py-3 text-right">Hata</th><th class="px-4 py-3">Tarih</th>
            </tr></thead>
            <tbody>
                @forelse ($batches as $b)
                    @php($c = $counts[$b->id] ?? collect())
                    <tr class="border-t border-line hover:bg-surface/60">
                        <td class="px-4 py-3"><a href="{{ route('admin.imports.show', $b) }}" class="font-semibold text-primary hover:underline">{{ $b->name }}</a>
                            <span class="block text-xs text-muted">{{ $b->admin?->name }}</span></td>
                        <td class="px-4 py-3"><span class="badge {{ $b->status === 'done' ? 'bg-success-soft text-success' : 'bg-info-soft text-info' }}">{{ ['uploading' => 'Yükleniyor', 'processing' => 'İşleniyor', 'done' => 'Tamamlandı'][$b->status] ?? $b->status }}</span></td>
                        <td class="px-4 py-3 text-right">{{ number_format($b->total_items, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right text-success">{{ $c['imported'] ?? 0 }}</td>
                        <td class="px-4 py-3 text-right text-warning">{{ $c['review'] ?? 0 }}</td>
                        <td class="px-4 py-3 text-right text-danger">{{ $c['failed'] ?? 0 }}</td>
                        <td class="px-4 py-3 text-xs text-muted">{{ $b->created_at->format('d.m.Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-muted">Henüz toplu yükleme yapılmadı.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $batches->links() }}</div>
</x-layouts.admin>
