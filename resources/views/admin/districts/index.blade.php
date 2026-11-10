@php use App\Support\Money; @endphp
<x-layouts.admin title="Teslimat bölgeleri">
    <x-slot:actions>
        <a href="{{ route('admin.settings.edit', 'teslimat') }}" class="btn-ghost btn-sm"><x-icon name="settings" class="size-4" />Teslimat ayarları</a>
        <a href="{{ route('admin.districts.create') }}" class="btn-primary btn-sm"><x-icon name="plus" class="size-4" />İlçe ekle</a>
    </x-slot:actions>

    <x-flash class="mb-5" />
    <p class="mb-4 text-sm text-muted">Yalnız açık ilçelere sipariş verilebilir. Boş bırakılan eşik ve en düşük tutar için
        <a href="{{ route('admin.settings.edit', 'teslimat') }}" class="text-primary hover:underline">genel teslimat ayarı</a> geçerlidir
        (şu an ücretsiz teslimat {{ $settings['delivery_free_threshold'] ? Money::format($settings['delivery_free_threshold']) : 'yok' }}, en düşük sipariş {{ $settings['delivery_min_order'] ? Money::format($settings['delivery_min_order']) : 'yok' }}).</p>

    <div class="card overflow-x-auto">
        <table class="table-panel">
            <thead><tr><th>İlçe</th><th class="text-right">Ücret</th><th class="text-right">Ücretsiz eşiği</th><th class="text-right">En düşük</th><th class="text-right">Ek gün</th><th class="text-right">Mahalle</th><th>Durum</th><th class="w-0"></th></tr></thead>
            <tbody>
                @forelse ($districts as $d)
                    <tr class="{{ $d->is_active ? '' : 'opacity-60' }}">
                        <td><a href="{{ route('admin.districts.edit', $d) }}" class="font-semibold hover:text-primary">{{ $d->name }}</a>@if ($d->note)<span class="block text-xs text-muted">{{ $d->note }}</span>@endif</td>
                        <td class="text-right whitespace-nowrap">{{ Money::format($d->fee) }}</td>
                        <td class="text-right whitespace-nowrap text-muted">{{ $d->free_threshold !== null ? Money::format($d->free_threshold) : 'genel' }}</td>
                        <td class="text-right whitespace-nowrap text-muted">{{ $d->min_order !== null ? Money::format($d->min_order) : 'genel' }}</td>
                        <td class="text-right">{{ $d->extra_days ?: '—' }}</td>
                        <td class="text-right">{{ $d->neighborhoods_count ?: 'serbest' }}</td>
                        <td>
                            <form method="post" action="{{ route('admin.districts.toggle', $d) }}">@csrf
                                <button class="badge {{ $d->is_active ? 'bg-success-soft text-success' : 'bg-slate-100 text-muted' }}">{{ $d->is_active ? 'Teslimat var' : 'Kapalı' }}</button>
                            </form>
                        </td>
                        <td class="whitespace-nowrap text-right">
                            <a href="{{ route('admin.districts.edit', $d) }}" class="btn-ghost btn-sm"><x-icon name="edit" class="size-3.5" /></a>
                            <form method="post" action="{{ route('admin.districts.destroy', $d) }}" class="inline" data-confirm="{{ $d->name }} silinsin mi? Kayıtlı adreslerdeki ilçe boşalır.">@csrf @method('delete')
                                <button class="btn-ghost btn-sm text-danger" aria-label="Sil"><x-icon name="trash" class="size-3.5" /></button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-10 text-center text-muted">Henüz ilçe yok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
