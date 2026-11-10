@php
    use App\Models\Order;
    use App\Support\Money;
    $next = ['received' => 'confirmed', 'confirmed' => 'preparing', 'preparing' => 'shipping', 'shipping' => 'delivered'][$order->status] ?? null;
@endphp
<x-layouts.admin :title="'Sipariş '.$order->number">
    <x-slot:actions>
        <a href="{{ route('admin.orders.slip', $order) }}" target="_blank" class="btn-ghost btn-sm"><x-icon name="printer" class="size-4" />Fişi yazdır</a>
        <a href="{{ $order->trackingUrl() }}" target="_blank" class="btn-ghost btn-sm"><x-icon name="external" class="size-4" />Müşteri görünümü</a>
    </x-slot:actions>

    <x-flash class="mb-5" />
    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-danger/20 bg-danger-soft px-4 py-3 text-sm font-medium text-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="grid items-start gap-6 xl:grid-cols-[1fr_380px]">
        <div class="space-y-6">
            <section class="card flex flex-wrap items-center gap-x-8 gap-y-3 p-5">
                <div><p class="text-xs text-muted">Durum</p><span class="badge {{ Order::tone($order->status) }} mt-1 px-3 py-1 text-sm">{{ $order->statusLabel() }}</span></div>
                <div><p class="text-xs text-muted">Sipariş zamanı</p><p class="font-semibold">{{ $order->created_at->format('d.m.Y H:i') }}</p></div>
                <div><p class="text-xs text-muted">Teslimat</p><p class="font-semibold">{{ $order->delivery_date?->format('d.m.Y') }} {{ $order->delivery_slot }}</p></div>
                <div><p class="text-xs text-muted">Tahsil edilecek</p><p class="font-display text-xl font-extrabold">{{ Money::format($order->total) }}</p><p class="text-xs text-muted">{{ $order->paymentLabel() }}</p></div>
            </section>

            <section class="card overflow-x-auto">
                <table class="table-panel">
                    <thead><tr><th>Ürün</th><th>Baskı</th><th class="text-right">Birim</th><th class="text-right">Adet</th><th class="text-right">Tutar</th></tr></thead>
                    <tbody>
                        @foreach ($order->items as $item)
                            <tr>
                                <td>@if ($item->product)<a href="{{ route('admin.products.edit', $item->product) }}" class="font-semibold hover:text-primary">{{ $item->title }}</a>@else<span class="font-semibold">{{ $item->title }}</span>@endif
                                    <span class="block text-xs text-muted">{{ collect([$item->meta['publisher'] ?? null, $item->meta['isbn'] ?? null, $item->meta['code'] ?? null])->filter()->implode(' · ') }}</span>
                                    @if ($item->kind === 'print')<span class="block text-xs text-muted">{{ $item->meta['summary'] ?? '' }}</span>
                                        @if ($item->print_job_id)<a href="{{ route('admin.print.download', $item->print_job_id) }}" class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline"><x-icon name="upload" class="size-3.5 rotate-180" />Baskı dosyasını indir</a>@endif
                                    @endif</td>
                                <td>{{ $item->printTypeLabel() }}</td>
                                <td class="text-right">{{ Money::format($item->unit_price) }}</td>
                                <td class="text-right">{{ $item->quantity }}</td>
                                <td class="text-right font-semibold">{{ Money::format($item->line_total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="text-sm">
                        <tr><td colspan="4" class="text-right text-muted">Ara toplam</td><td class="text-right">{{ Money::format($order->subtotal) }}</td></tr>
                        <tr><td colspan="4" class="text-right text-muted">Teslimat</td><td class="text-right">{{ Money::format($order->delivery_fee) }}</td></tr>
                        <tr><td colspan="4" class="text-right font-bold">Toplam</td><td class="text-right font-bold">{{ Money::format($order->total) }}</td></tr>
                    </tfoot>
                </table>
            </section>

            <section class="card p-5">
                <h2 class="font-bold">Durum geçmişi</h2>
                <ol class="mt-4 space-y-3 text-sm">
                    @foreach ($order->history->reverse() as $h)
                        <li class="flex gap-3">
                            <span class="badge {{ Order::tone($h->status) }} h-fit shrink-0">{{ Order::SHORT[$h->status] ?? $h->status }}</span>
                            <span class="min-w-0">
                                <span class="text-muted">{{ $h->created_at->format('d.m.Y H:i') }} · {{ $h->admin?->name ?? 'Müşteri' }}{{ $h->customer_notified ? ' · müşteriye bildirildi' : '' }}</span>
                                @if ($h->note)<span class="block">{{ $h->note }}</span>@endif
                            </span>
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>

        <div class="space-y-6">
            @if ($order->status !== 'cancelled')
                <form method="post" action="{{ route('admin.orders.status', $order) }}" class="card space-y-4 p-5">
                    @csrf @method('put')
                    <h2 class="font-bold">Durumu değiştir</h2>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (Order::SHORT as $key => $label)
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-line px-3 py-2 text-sm has-checked:border-primary has-checked:bg-primary-soft">
                                <input type="radio" name="status" value="{{ $key }}" class="accent-primary" @checked(old('status', $next ?? $order->status) === $key)> {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    <x-field name="note" type="textarea" rows="2" label="Not (müşteri görür)" help="İptalde zorunlu: iptal nedeni." />
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="notify" value="1" checked class="accent-primary" @disabled(! $order->email)>
                        Müşteriye e-postayla bildir @unless ($order->email)<span class="text-xs text-muted">(e-posta yok)</span>@endunless</label>
                    <button class="btn-primary w-full">Kaydet</button>
                </form>
            @endif

            <section class="card space-y-2 p-5 text-sm">
                <h2 class="font-bold">Müşteri</h2>
                <p class="font-semibold">{{ $order->customer_name }}
                    @if ($order->user)<span class="badge bg-primary-soft text-primary-dark">Üye</span>@else<span class="badge bg-slate-100 text-muted">Misafir</span>@endif</p>
                <p><a href="tel:{{ $order->phone }}" class="text-primary hover:underline">{{ \App\Rules\TurkishPhone::format($order->phone) }}</a>
                    @if (str_starts_with($order->phone, '05')) · <a href="https://wa.me/9{{ $order->phone }}" target="_blank" rel="noopener" class="text-primary hover:underline">WhatsApp</a>@endif</p>
                @if ($order->email)<p><a href="mailto:{{ $order->email }}" class="text-primary hover:underline">{{ $order->email }}</a></p>@endif
                <p class="pt-2 text-muted">{{ $order->neighborhood }}, {{ $order->address }}<br><strong class="text-ink">{{ $order->district_name }}</strong> / Eskişehir</p>
                @if ($order->note)<p class="rounded-lg bg-accent-soft p-2.5 text-accent-ink"><strong>Müşteri notu:</strong> {{ $order->note }}</p>@endif
                <p class="pt-2 text-xs text-muted">IP: {{ $order->ip }}</p>
            </section>

            <form method="post" action="{{ route('admin.orders.note', $order) }}" class="card space-y-3 p-5">
                @csrf @method('put')
                <x-field name="admin_note" type="textarea" rows="3" label="İç not (müşteri görmez)" :value="$order->admin_note" />
                <button class="btn-ghost btn-sm">Notu kaydet</button>
            </form>
        </div>
    </div>
</x-layouts.admin>
