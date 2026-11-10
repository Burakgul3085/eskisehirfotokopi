@php
    use App\Rules\TurkishPhone;
    use App\Support\Money;
@endphp
<x-layouts.admin title="Müşteriler">
    <form method="get" class="card mb-4 flex flex-wrap gap-3 p-4">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Ad, e-posta, telefon" class="field-input min-w-48 flex-1">
        <select name="sirala" class="field-input w-auto" data-autosubmit>
            @foreach (['yeni' => 'En yeni üyeler', 'siparis' => 'En çok sipariş', 'tutar' => 'En yüksek tutar'] as $k => $l)
                <option value="{{ $k }}" @selected(request('sirala', 'yeni') === $k)>{{ $l }}</option>
            @endforeach
        </select>
        <select name="durum" class="field-input w-auto" data-autosubmit>
            <option value="">Tüm hesaplar</option>
            <option value="kapali" @selected(request('durum') === 'kapali')>Kapalı hesaplar</option>
        </select>
        <button class="btn-primary btn-sm" aria-label="Ara"><x-icon name="search" class="size-4" /></button>
    </form>
    <p class="mb-3 text-sm text-muted">{{ $customers->total() }} üye · üye olmadan verilen {{ $guestOrders }} sipariş Siparişler sayfasında “misafir” olarak görünür.</p>

    <div class="card overflow-x-auto">
        <table class="table-panel">
            <thead><tr><th>Müşteri</th><th>Telefon</th><th>Üyelik</th><th class="text-right">Sipariş</th><th class="text-right">Toplam</th><th>Durum</th></tr></thead>
            <tbody>
                @forelse ($customers as $c)
                    <tr>
                        <td><a href="{{ route('admin.customers.show', $c) }}" class="font-semibold text-primary hover:underline">{{ $c->name }}</a><span class="block text-xs text-muted">{{ $c->email }}</span></td>
                        <td class="whitespace-nowrap">{{ $c->phone ? TurkishPhone::format($c->phone) : '—' }}</td>
                        <td class="whitespace-nowrap text-sm">{{ $c->created_at->format('d.m.Y') }}<span class="block text-xs text-muted">{{ $c->last_login_at ? 'Son giriş '.$c->last_login_at->diffForHumans() : 'Giriş yok' }}</span></td>
                        <td class="text-right">{{ $c->orders_count }}</td>
                        <td class="text-right font-semibold whitespace-nowrap">{{ Money::format($c->orders_total ?? 0) }}</td>
                        <td>@if ($c->is_active)<span class="badge bg-success-soft text-success">Açık</span>@else<span class="badge bg-danger-soft text-danger">Kapalı</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-10 text-center text-muted">Müşteri bulunamadı.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $customers->links() }}</div>
</x-layouts.admin>
