@php
    use App\Support\Money;
    $groups = $orders->groupBy('district_name');
@endphp
<x-layouts.admin title="Teslimat listesi">
    <x-slot:actions>
        <a href="{{ route('admin.orders.deliveries', ['gun' => $day->toDateString(), 'yazdir' => 1]) }}" target="_blank" class="btn-primary btn-sm"><x-icon name="printer" class="size-4" />Tümünü yazdır</a>
    </x-slot:actions>

    <form method="get" class="mb-5 flex flex-wrap items-center gap-2">
        <a href="{{ route('admin.orders.deliveries', ['gun' => $day->subDay()->toDateString()]) }}" class="btn-ghost btn-sm" aria-label="Önceki gün">‹</a>
        <input type="date" name="gun" value="{{ $day->toDateString() }}" class="field-input w-auto" data-autosubmit>
        <a href="{{ route('admin.orders.deliveries', ['gun' => $day->addDay()->toDateString()]) }}" class="btn-ghost btn-sm" aria-label="Sonraki gün">›</a>
        <span class="text-sm font-semibold">{{ $day->locale('tr')->translatedFormat('j F Y l') }}</span>
        <span class="ml-auto text-sm text-muted">{{ $orders->count() }} sipariş · tahsil edilecek {{ Money::format($orders->sum('total')) }}</span>
    </form>

    @forelse ($groups as $district => $list)
        <section class="card mb-5 overflow-x-auto">
            <h2 class="flex items-center justify-between border-b border-line px-5 py-3 font-bold">{{ $district }}<span class="text-sm font-normal text-muted">{{ $list->count() }} sipariş</span></h2>
            <table class="table-panel">
                <thead><tr><th>Sipariş</th><th>Saat</th><th>Müşteri</th><th>Adres</th><th>Ödeme</th><th class="text-right">Tutar</th><th>Durum</th></tr></thead>
                <tbody>
                    @foreach ($list as $o)
                        <tr>
                            <td><a href="{{ route('admin.orders.show', $o) }}" class="font-bold text-primary hover:underline">{{ $o->number }}</a></td>
                            <td class="whitespace-nowrap">{{ $o->delivery_slot ?: '—' }}</td>
                            <td>{{ $o->customer_name }}<span class="block text-xs text-muted">{{ \App\Rules\TurkishPhone::format($o->phone) }}</span></td>
                            <td class="max-w-xs text-xs">{{ $o->neighborhood }}, {{ $o->address }}</td>
                            <td class="text-xs">{{ $o->paymentLabel() }}</td>
                            <td class="text-right font-semibold whitespace-nowrap">{{ Money::format($o->total) }}</td>
                            <td><span class="badge {{ \App\Models\Order::tone($o->status) }}">{{ $o->shortStatus() }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @empty
        <div class="card px-6 py-14 text-center text-muted">Bu gün için bekleyen teslimat yok.</div>
    @endforelse
</x-layouts.admin>
