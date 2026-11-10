@php
    use App\Models\MessageThread;
    use App\Models\Order;
    use App\Rules\TurkishPhone;
    use App\Support\Money;
@endphp
<x-layouts.admin :title="'Müşteri · '.$customer->name">
    <x-slot:actions>
        <a href="{{ route('admin.customers.index') }}" class="btn-ghost btn-sm">Tüm müşteriler</a>
    </x-slot:actions>

    <div class="grid gap-6 xl:grid-cols-[20rem_1fr]">
        <aside class="space-y-4">
            <div class="card space-y-2 p-5 text-sm">
                <div class="flex items-start justify-between gap-2">
                    <h1 class="text-lg font-bold">{{ $customer->name }}</h1>
                    @if ($customer->is_active)<span class="badge bg-success-soft text-success">Açık</span>@else<span class="badge bg-danger-soft text-danger">Kapalı</span>@endif
                </div>
                <p><a href="mailto:{{ $customer->email }}" class="text-primary hover:underline">{{ $customer->email }}</a></p>
                @if ($customer->phone)<p>{{ TurkishPhone::format($customer->phone) }}</p>@endif
                <p class="text-muted">Üyelik: {{ $customer->created_at->format('d.m.Y') }}</p>
                <p class="text-muted">Son giriş: {{ $customer->last_login_at?->format('d.m.Y H:i') ?? '—' }}</p>
                <div class="grid grid-cols-2 gap-3 border-t border-line pt-3">
                    <div><p class="text-xs text-muted">Sipariş</p><p class="font-display text-xl font-bold">{{ $stats->c }}</p></div>
                    <div><p class="text-xs text-muted">Toplam</p><p class="font-display text-xl font-bold">{{ Money::format($stats->t) }}</p></div>
                </div>
                <form method="post" action="{{ route('admin.customers.toggle', $customer) }}" class="border-t border-line pt-3"
                      data-confirm="{{ $customer->is_active ? 'Hesap kapatılsın mı? Müşteri giriş yapamaz, açık oturumları sonlanır.' : 'Hesap yeniden açılsın mı?' }}">
                    @csrf
                    <button class="btn-ghost btn-sm w-full {{ $customer->is_active ? 'text-danger' : '' }}">{{ $customer->is_active ? 'Hesabı kapat' : 'Hesabı aç' }}</button>
                </form>
            </div>

            <div class="card p-5 text-sm">
                <h2 class="font-bold">Adresler</h2>
                @forelse ($customer->addresses as $a)
                    <div class="mt-3 border-t border-line pt-3 first-of-type:border-0 first-of-type:pt-0">
                        <p class="font-semibold">{{ $a->title }}@if ($a->is_default) <span class="text-xs font-normal text-muted">(varsayılan)</span>@endif</p>
                        <p class="text-muted">{{ $a->oneLine() }}</p>
                    </div>
                @empty
                    <p class="mt-2 text-muted">Kayıtlı adres yok.</p>
                @endforelse
            </div>
        </aside>

        <div class="min-w-0 space-y-6">
            <section class="card overflow-x-auto">
                <h2 class="border-b border-line px-5 py-4 font-bold">Siparişler</h2>
                <table class="table-panel">
                    <thead><tr><th>Sipariş</th><th>Tarih</th><th>Bölge</th><th class="text-right">Tutar</th><th>Durum</th></tr></thead>
                    <tbody>
                        @forelse ($orders as $o)
                            <tr>
                                <td><a href="{{ route('admin.orders.show', $o) }}" class="font-display font-bold text-primary hover:underline">{{ $o->number }}</a><span class="block text-xs text-muted">{{ $o->items_count }} kalem</span></td>
                                <td class="whitespace-nowrap text-sm">{{ $o->created_at->format('d.m.Y H:i') }}</td>
                                <td>{{ $o->district_name }}</td>
                                <td class="text-right font-semibold whitespace-nowrap">{{ Money::format($o->total) }}</td>
                                <td><span class="badge {{ Order::tone($o->status) }}">{{ $o->shortStatus() }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-8 text-center text-muted">Sipariş yok.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

            <section class="card overflow-hidden">
                <h2 class="border-b border-line px-5 py-4 font-bold">Mesajlar</h2>
                @forelse ($threads as $t)
                    <a href="{{ route('admin.messages.show', $t) }}" class="flex items-center gap-3 border-b border-line/70 px-5 py-3 text-sm last:border-0 hover:bg-surface">
                        <span class="min-w-0 flex-1 truncate font-semibold">{{ $t->subject }}</span>
                        <span class="text-xs text-muted">{{ $t->last_message_at?->format('d.m.Y') }}</span>
                        <span class="badge {{ MessageThread::tone($t->status) }}">{{ $t->statusLabel() }}</span>
                    </a>
                @empty
                    <p class="px-5 py-6 text-sm text-muted">Mesaj yok.</p>
                @endforelse
            </section>
        </div>
    </div>
</x-layouts.admin>
