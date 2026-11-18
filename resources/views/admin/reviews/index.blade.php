@php
    use App\Http\Controllers\Admin\ReviewController;
    use App\Models\ProductReview;
@endphp
<x-layouts.admin title="Değerlendirmeler">
    <x-slot:actions>
        <a href="{{ route('admin.settings.edit', 'degerlendirme') }}" class="btn-ghost btn-sm"><x-icon name="settings" class="size-4" />Ayarlar ve e-postalar</a>
    </x-slot:actions>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="flex gap-1 overflow-x-auto rounded-xl bg-white p-1 shadow-card">
            @foreach (ReviewController::TABS as $key => $label)
                <a href="{{ route('admin.reviews.index', array_filter(['durum' => $key, 'q' => request('q'), 'puan' => request('puan')])) }}"
                   class="flex shrink-0 items-center gap-1.5 rounded-lg px-3.5 py-2 text-sm font-semibold {{ $status === $key ? 'bg-primary text-white' : 'text-muted hover:bg-surface hover:text-ink' }}">
                    {{ $label }}<span class="text-xs opacity-70">{{ $key === 'tumu' ? $counts->sum() : ($counts[$key] ?? 0) }}</span>
                </a>
            @endforeach
        </div>
        <form method="get" class="flex flex-1 flex-wrap gap-2 sm:max-w-lg">
            <input type="hidden" name="durum" value="{{ $status }}">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Ürün, müşteri, yorum" class="field-input min-w-0 flex-1">
            <select name="puan" class="field-input w-auto" aria-label="Puan">
                <option value="">Tüm puanlar</option>
                @foreach (range(5, 1) as $r)<option value="{{ $r }}" @selected((string) request('puan') === (string) $r)>{{ $r }} yıldız</option>@endforeach
            </select>
            <button class="btn-primary btn-sm shrink-0" aria-label="Ara"><x-icon name="search" class="size-4" /></button>
        </form>
    </div>

    <div class="card overflow-x-auto">
        <table class="table-panel">
            <thead><tr><th>Değerlendirme</th><th>Ürün</th><th>Müşteri</th><th>Tarih</th><th>Durum</th></tr></thead>
            <tbody>
                @forelse ($reviews as $r)
                    <tr>
                        <td class="max-w-md">
                            <a href="{{ route('admin.reviews.show', $r) }}" class="block hover:text-primary">
                                <span class="admin-stars" aria-label="{{ $r->rating }} yıldız">@foreach (range(1, 5) as $i)<x-icon name="star" class="size-3.5 {{ $i <= $r->rating ? 'is-on' : '' }}" />@endforeach</span>
                                @if ($r->title)<span class="mt-1 block font-semibold">{{ \Illuminate\Support\Str::limit($r->title, 60) }}</span>@endif
                                <span class="mt-0.5 block text-sm text-muted">{{ \Illuminate\Support\Str::limit($r->body, 110) }}</span>
                            </a>
                            <span class="mt-1 flex flex-wrap gap-2 text-xs text-muted">
                                @if ($r->photos_count)<span class="inline-flex items-center gap-1"><x-icon name="image" class="size-3.5" />{{ $r->photos_count }} fotoğraf</span>@endif
                                @if ($r->reply)<span class="inline-flex items-center gap-1"><x-icon name="chat" class="size-3.5" />Cevaplandı</span>@endif
                            </span>
                        </td>
                        <td class="text-sm">@if ($r->product)<a href="{{ route('product.show', $r->product) }}" target="_blank" class="hover:text-primary">{{ \Illuminate\Support\Str::limit($r->product->title, 50) }}</a>@endif</td>
                        <td class="text-sm">{{ $r->user?->name }}<span class="block text-xs text-muted">{{ $r->user?->email }}</span></td>
                        <td class="text-sm whitespace-nowrap">{{ $r->submitted_at?->format('d.m.Y H:i') }}</td>
                        <td><span class="badge {{ ProductReview::tone($r->status) }}">{{ $r->statusLabel() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-10 text-center text-muted">Bu listede değerlendirme yok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $reviews->links() }}</div>
</x-layouts.admin>
