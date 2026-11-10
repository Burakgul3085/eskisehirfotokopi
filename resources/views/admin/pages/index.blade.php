@php use App\Models\Page; @endphp
<x-layouts.admin title="Sayfalar">
    <x-slot:actions>
        <a href="{{ route('admin.pages.create') }}" class="btn-primary btn-sm"><x-icon name="plus" class="size-4" />Sayfa ekle</a>
    </x-slot:actions>

    <x-flash class="mb-5" />
    <p class="mb-4 text-sm text-muted">Alt bilgi sütununu seçilen sayfalar sitenin alt kısmında listelenir. Kilitli sayfalar sipariş, üyelik ve iletişim formlarından bağlanır; silinemez, gizlenebilir.</p>

    <div class="card overflow-x-auto">
        <table class="table-panel">
            <thead><tr><th>Başlık</th><th>Adres</th><th>Alt bilgi</th><th class="text-right">Sıra</th><th>Durum</th><th class="w-0"></th></tr></thead>
            <tbody>
                @forelse ($pages as $p)
                    <tr class="{{ $p->is_active ? '' : 'opacity-60' }}">
                        <td><a href="{{ route('admin.pages.edit', $p) }}" class="font-semibold hover:text-primary">{{ $p->title }}</a>
                            @if ($p->isSystem())<x-icon name="shield" class="ml-1 inline size-3.5 text-muted" title="Sistem sayfası" />@endif</td>
                        <td class="text-sm"><a href="{{ $p->url() }}" target="_blank" class="text-muted hover:text-primary">/sayfa/{{ $p->slug }}</a></td>
                        <td class="text-sm">{{ Page::COLUMNS[$p->footer_column] ?? '—' }}</td>
                        <td class="text-right">{{ $p->position }}</td>
                        <td><span class="badge {{ $p->is_active ? 'bg-success-soft text-success' : 'bg-slate-100 text-muted' }}">{{ $p->is_active ? 'Yayında' : 'Gizli' }}</span></td>
                        <td class="whitespace-nowrap text-right">
                            <a href="{{ route('admin.pages.edit', $p) }}" class="btn-ghost btn-sm" aria-label="Düzenle"><x-icon name="edit" class="size-3.5" /></a>
                            @unless ($p->isSystem())
                                <form method="post" action="{{ route('admin.pages.destroy', $p) }}" class="inline" data-confirm="{{ $p->title }} silinsin mi?">@csrf @method('delete')
                                    <button class="btn-ghost btn-sm text-danger" aria-label="Sil"><x-icon name="trash" class="size-3.5" /></button></form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-10 text-center text-muted">Henüz sayfa yok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
