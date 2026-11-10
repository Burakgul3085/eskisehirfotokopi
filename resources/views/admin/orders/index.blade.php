@php
    use App\Models\Order;
    use App\Support\Money;
    $current = request('durum');
    $tabs = ['' => 'Tümü', 'acik' => 'Açık'] + Order::SHORT;
@endphp
<x-layouts.admin title="Siparişler">
    <x-slot:actions>
        <a href="{{ route('admin.orders.deliveries') }}" class="btn-ghost btn-sm"><x-icon name="truck" class="size-4" />Bugünün teslimatları</a>
    </x-slot:actions>

    <div class="mb-4 flex gap-1 overflow-x-auto rounded-xl bg-white p-1 shadow-card">
        @foreach ($tabs as $key => $label)
            @php($n = $key === '' ? $counts->sum() : ($key === 'acik' ? $counts->except(['delivered', 'cancelled'])->sum() : ($counts[$key] ?? 0)))
            <a href="{{ route('admin.orders.index', array_filter(['durum' => $key] + request()->except('durum', 'page'))) }}"
               class="flex shrink-0 items-center gap-1.5 rounded-lg px-3.5 py-2 text-sm font-semibold {{ (string) $current === (string) $key ? 'bg-primary text-white' : 'text-muted hover:bg-surface hover:text-ink' }}">
                {{ $label }}<span class="text-xs opacity-70">{{ $n }}</span>
            </a>
        @endforeach
    </div>

    <form method="get" class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-6">
        @if ($current)<input type="hidden" name="durum" value="{{ $current }}">@endif
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Sipariş no, ad, telefon, e-posta" class="field-input sm:col-span-2">
        <select name="ilce" class="field-input" data-autosubmit>
            <option value="">Tüm ilçeler</option>
            @foreach ($districts as $d)<option value="{{ $d->id }}" @selected((int) request('ilce') === $d->id)>{{ $d->name }}</option>@endforeach
        </select>
        <select name="odeme" class="field-input" data-autosubmit>
            <option value="">Tüm ödemeler</option>
            @foreach (Order::PAYMENT_METHODS as $k => $l)<option value="{{ $k }}" @selected(request('odeme') === $k)>{{ $l }}</option>@endforeach
        </select>
        <input type="date" name="baslangic" value="{{ request('baslangic') }}" class="field-input" aria-label="Başlangıç tarihi">
        <div class="flex gap-2">
            <input type="date" name="bitis" value="{{ request('bitis') }}" class="field-input" aria-label="Bitiş tarihi">
            <button class="btn-primary btn-sm shrink-0"><x-icon name="search" class="size-4" /></button>
        </div>
    </form>

    <div class="card overflow-x-auto">
        <table class="table-panel">
            <thead><tr><th>Sipariş</th><th>Müşteri</th><th>Bölge</th><th>Teslimat</th><th>Ödeme</th><th class="text-right">Tutar</th><th>Durum</th></tr></thead>
            <tbody>
                @forelse ($orders as $o)
                    <tr>
                        <td><a href="{{ route('admin.orders.show', $o) }}" class="font-display font-bold text-primary hover:underline">{{ $o->number }}</a>
                            <span class="block text-xs text-muted">{{ $o->created_at->format('d.m.Y H:i') }} · {{ $o->items_count }} kalem</span></td>
                        <td>{{ $o->customer_name }}<span class="block text-xs text-muted">{{ \App\Rules\TurkishPhone::format($o->phone) }}{{ $o->user_id ? '' : ' · misafir' }}</span></td>
                        <td>{{ $o->district_name }}<span class="block text-xs text-muted">{{ $o->neighborhood }}</span></td>
                        <td class="whitespace-nowrap">{{ $o->delivery_date?->format('d.m.Y') }}<span class="block text-xs text-muted">{{ $o->delivery_slot }}</span></td>
                        <td class="text-xs">{{ $o->paymentLabel() }}</td>
                        <td class="text-right font-semibold whitespace-nowrap">{{ Money::format($o->total) }}</td>
                        <td><span class="badge {{ Order::tone($o->status) }}">{{ $o->shortStatus() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-10 text-center text-muted">Sipariş bulunamadı.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $orders->links() }}</div>
</x-layouts.admin>
