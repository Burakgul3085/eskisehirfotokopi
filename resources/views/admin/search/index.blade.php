<x-layouts.admin title="Site araması">
    <x-flash class="mb-5" />
    <p class="mb-5 max-w-3xl text-sm text-muted">
        Arama kutusuna bir sayfanın, kategorinin, yayınevinin veya ürünün adı birebir yazılırsa doğrudan oraya gidilir; tek sonuç kalırsa yine doğrudan açılır.
        Adı olmayan ama sık aranan kelimeler için aşağıdan yönlendirme ekleyin (ör. “kargo” → Sipariş takibi). Eşleşme büyük/küçük harf ve Türkçe karakter farkı gözetmez.
    </p>

    <div class="grid items-start gap-6 xl:grid-cols-2">
        {{-- Yönlendirmeler --}}
        <section class="card overflow-hidden">
            <h2 class="flex items-center gap-2 border-b border-line px-5 py-4 font-bold"><x-icon name="arrow-right" class="size-4 text-primary" />Yönlendirmeler <span class="ml-auto text-sm font-normal text-muted">{{ $redirects->count() }}</span></h2>
            <form method="post" action="{{ route('admin.search.store') }}" id="yonlendirme" class="grid gap-3 border-b border-line bg-surface/60 p-4 sm:grid-cols-[1fr_1.3fr_auto] sm:items-end">
                @csrf
                <x-field name="term" label="Aranan kelime" :value="$prefill" required placeholder="kargo" />
                <div>
                    <x-field name="url" label="Gidilecek adres" required placeholder="/siparis-takip" list="arama-hedefleri" />
                    <datalist id="arama-hedefleri">@foreach ($targets as [$label, $path])<option value="{{ $path }}">{{ $label }}</option>@endforeach</datalist>
                </div>
                <button class="btn-primary"><x-icon name="plus" class="size-4" />Ekle</button>
                @error('match_key')<p class="field-error sm:col-span-3">{{ $message }}</p>@enderror
            </form>
            <table class="table-panel">
                <thead><tr><th>Kelime</th><th>Adres</th><th class="text-right">Kullanım</th><th class="w-0"></th></tr></thead>
                <tbody>
                    @forelse ($redirects as $r)
                        <tr>
                            <td class="font-semibold">{{ $r->term }}</td>
                            <td class="text-sm break-all"><a href="{{ $r->url }}" target="_blank" class="text-muted hover:text-primary">{{ $r->url }}</a></td>
                            <td class="text-right text-sm">{{ number_format($r->hits, 0, ',', '.') }}</td>
                            <td class="text-right">
                                <form method="post" action="{{ route('admin.search.destroy', $r) }}" data-confirm="“{{ $r->term }}” yönlendirmesi silinsin mi?">@csrf @method('delete')
                                    <button class="btn-ghost btn-sm text-danger" aria-label="Sil"><x-icon name="trash" class="size-3.5" /></button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center text-muted">Henüz yönlendirme yok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        {{-- Bulunamayan aramalar --}}
        <section class="card overflow-hidden">
            <h2 class="flex items-center gap-2 border-b border-line px-5 py-4 font-bold"><x-icon name="search" class="size-4 text-accent-ink" />Bulunamayan aramalar
                <span class="ml-auto text-sm font-normal text-muted">{{ number_format($missTotal, 0, ',', '.') }} arama</span>
                @if ($misses->total())
                    <form method="post" action="{{ route('admin.search.misses.clear') }}" data-confirm="Liste temizlensin mi?">@csrf @method('delete')
                        <button class="btn-ghost btn-sm">Temizle</button></form>
                @endif
            </h2>
            <p class="border-b border-line px-5 py-3 text-xs text-muted">Müşterilerin aradığı ama hiçbir sonuç bulamadığı kelimeler. Ürün ekleyin ya da bir yönlendirme tanımlayın.</p>
            <table class="table-panel">
                <thead><tr><th>Kelime</th><th class="text-right">Arama</th><th>Son</th><th class="w-0"></th></tr></thead>
                <tbody>
                    @forelse ($misses as $m)
                        <tr>
                            <td class="font-semibold break-all">{{ $m->term }}</td>
                            <td class="text-right text-sm">{{ number_format($m->hits, 0, ',', '.') }}</td>
                            <td class="text-sm whitespace-nowrap text-muted">{{ $m->last_searched_at?->format('d.m.Y H:i') }}</td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('admin.search.index', ['kelime' => $m->term]) }}#yonlendirme" class="btn-ghost btn-sm" title="Yönlendirme ekle"><x-icon name="arrow-right" class="size-3.5" />Yönlendir</a>
                                <form method="post" action="{{ route('admin.search.misses.destroy', $m) }}" class="inline">@csrf @method('delete')
                                    <button class="btn-ghost btn-sm text-danger" aria-label="Sil"><x-icon name="trash" class="size-3.5" /></button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center text-muted">Bulunamayan arama yok.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if ($misses->hasPages())<div class="border-t border-line p-4">{{ $misses->links() }}</div>@endif
        </section>
    </div>
</x-layouts.admin>
