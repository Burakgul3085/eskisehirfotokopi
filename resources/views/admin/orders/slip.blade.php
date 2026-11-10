@php
    use App\Support\Money;
    $siteName = \App\Support\Settings::get('site_name');
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ isset($day) ? 'Teslimat listesi '.$day->format('d.m.Y') : 'Teslimat fişi '.$orders->first()?->number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font: 13px/1.45 system-ui, -apple-system, "Segoe UI", sans-serif; color: #111; margin: 0; padding: 16px; }
        .slip { border: 1px solid #999; border-radius: 6px; padding: 14px 16px; margin-bottom: 14px; page-break-inside: avoid; }
        .head { display: flex; justify-content: space-between; gap: 12px; border-bottom: 1px dashed #999; padding-bottom: 8px; margin-bottom: 8px; }
        h1 { font-size: 18px; margin: 0; }
        h2 { font-size: 16px; margin: 0 0 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        td, th { padding: 3px 4px; text-align: left; vertical-align: top; }
        th { border-bottom: 1px solid #ccc; font-size: 11px; color: #555; }
        .r { text-align: right; white-space: nowrap; }
        .total { font-size: 16px; font-weight: 700; }
        .muted { color: #555; }
        .box { background: #f3f3f3; padding: 6px 8px; border-radius: 4px; margin-top: 6px; }
        .noprint { margin-bottom: 12px; }
        @media print { .noprint { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="noprint"><button type="button" data-print>Yazdır</button> <span class="muted">{{ $orders->count() }} sipariş</span></div>
    @isset($day)<h2>{{ $siteName }} — {{ $day->locale('tr')->translatedFormat('j F Y l') }} teslimatları</h2>@endisset
    @forelse ($orders as $order)
        <div class="slip">
            <div class="head">
                <div><h1>{{ $order->number }}</h1><span class="muted">{{ $order->created_at->format('d.m.Y H:i') }}</span></div>
                <div class="r"><strong>{{ $order->district_name }}</strong><br>{{ $order->delivery_date?->format('d.m.Y') }} {{ $order->delivery_slot }}</div>
            </div>
            <div><strong>{{ $order->customer_name }}</strong> · {{ \App\Rules\TurkishPhone::format($order->phone) }}</div>
            <div>{{ $order->neighborhood }} Mah., {{ $order->address }} — {{ $order->district_name }}</div>
            @if ($order->note)<div class="box">Not: {{ $order->note }}</div>@endif
            <table>
                <thead><tr><th>Ürün</th><th>Baskı</th><th class="r">Adet</th><th class="r">Tutar</th></tr></thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr><td>{{ $item->title }}@if ($item->meta['publisher'] ?? null) <span class="muted">({{ $item->meta['publisher'] }})</span>@endif
                            @if ($item->kind === 'print')<br><span class="muted">Fotokopi: {{ $item->meta['summary'] ?? '' }}</span>@endif</td>
                            <td>{{ $item->printTypeLabel() }}</td><td class="r">{{ $item->quantity }}</td><td class="r">{{ Money::format($item->line_total) }}</td></tr>
                    @endforeach
                    <tr><td colspan="3" class="r muted">Teslimat</td><td class="r">{{ Money::format($order->delivery_fee) }}</td></tr>
                    <tr><td colspan="3" class="r total">Tahsil edilecek ({{ $order->paymentLabel() }})</td><td class="r total">{{ Money::format($order->total) }}</td></tr>
                </tbody>
            </table>
        </div>
    @empty
        <p>Bu gün için teslimat yok.</p>
    @endforelse
    @vite('resources/js/print.js')
</body>
</html>
