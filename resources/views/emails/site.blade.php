@php
    use App\Models\Order;
    use App\Support\Money;
    $primary = $site['color_primary'] ?: '#0f766e';
    $accent = $site['color_accent'] ?: '#f59e0b';
    $steps = array_slice(Order::SHORT, 0, 5, true);
    $current = $order?->step();
    $font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:{{ $font }};color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px;">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;">
                {{-- Başlık --}}
                <tr><td style="padding:4px 4px 16px;">
                    <a href="{{ route('home') }}" style="text-decoration:none;color:#0f172a;font-weight:800;font-size:18px;">
                        @if ($logo)<img src="{{ $logo }}" alt="{{ $siteName }}" style="max-height:40px;max-width:200px;border:0;display:block;">
                        @else<span style="display:inline-block;background:{{ $primary }};color:#fff;border-radius:10px;padding:6px 10px;font-size:14px;">&#9113;</span>&nbsp;{{ $siteName }}@endif
                    </a>
                </td></tr>

                <tr><td style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 1px 2px rgba(15,23,42,.06);">
                    <div style="height:6px;background:{{ $primary }};"></div>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:28px 28px 8px;">
                        <h1 style="margin:0 0 12px;font-size:22px;line-height:1.3;font-weight:800;color:#0f172a;">{{ $heading }}</h1>
                        @foreach ($lines as $line)
                            <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#334155;">{{ $line }}</p>
                        @endforeach

                        @if ($showProgress && $current !== null)
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:8px 0 16px;"><tr>
                                @foreach ($steps as $key => $label)
                                    @php($done = $loop->index <= $current)
                                    <td style="padding:0 2px;vertical-align:top;text-align:center;">
                                        <div style="height:6px;border-radius:6px;background:{{ $done ? $primary : '#e2e8f0' }};"></div>
                                        <div style="margin-top:6px;font-size:11px;font-weight:600;color:{{ $done ? '#0f172a' : '#94a3b8' }};">{{ $label }}</div>
                                    </td>
                                @endforeach
                            </tr></table>
                        @endif

                        @if ($quote)
                            <div style="margin:4px 0 16px;padding:14px 16px;border-left:4px solid {{ $accent }};background:#f8fafc;border-radius:8px;font-size:15px;line-height:1.6;color:#0f172a;white-space:pre-line;">{{ $quote }}</div>
                        @endif

                        @if ($facts)
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:4px 0 16px;font-size:14px;">
                                @foreach ($facts as [$label, $value])
                                    <tr><td style="padding:6px 0;color:#64748b;width:110px;vertical-align:top;">{{ $label }}</td><td style="padding:6px 0;color:#0f172a;font-weight:600;">{{ $value }}</td></tr>
                                @endforeach
                            </table>
                        @endif

                        @if ($order && $items->isNotEmpty())
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:4px 0 16px;font-size:14px;border-top:1px solid #e2e8f0;">
                                @foreach ($items as $item)
                                    <tr>
                                        <td style="padding:10px 0;border-bottom:1px solid #f1f5f9;">
                                            <div style="font-weight:600;color:#0f172a;">{{ $item->title }}</div>
                                            <div style="font-size:12px;color:#64748b;">{{ collect([$item->meta['summary'] ?? $item->printTypeLabel(), $item->quantity.' × '.Money::format($item->unit_price)])->filter()->implode(' · ') }}</div>
                                        </td>
                                        <td style="padding:10px 0;border-bottom:1px solid #f1f5f9;text-align:right;white-space:nowrap;font-weight:600;">{{ Money::format($item->line_total) }}</td>
                                    </tr>
                                @endforeach
                                <tr><td style="padding:8px 0 2px;color:#64748b;">Ara toplam</td><td style="padding:8px 0 2px;text-align:right;">{{ Money::format($order->subtotal) }}</td></tr>
                                <tr><td style="padding:2px 0;color:#64748b;">Teslimat</td><td style="padding:2px 0;text-align:right;">{{ (float) $order->delivery_fee > 0 ? Money::format($order->delivery_fee) : 'Ücretsiz' }}</td></tr>
                                <tr><td style="padding:6px 0;font-size:16px;font-weight:800;">Toplam</td><td style="padding:6px 0;text-align:right;font-size:16px;font-weight:800;">{{ Money::format($order->total) }}</td></tr>
                            </table>
                        @endif

                        @if ($buttonUrl)
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 24px;"><tr><td style="border-radius:12px;background:{{ $primary }};">
                                <a href="{{ $buttonUrl }}" style="display:inline-block;padding:13px 24px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:12px;">{{ $buttonText }}</a>
                            </td></tr></table>
                        @endif
                    </td></tr></table>
                </td></tr>

                {{-- Alt bilgi --}}
                <tr><td style="padding:20px 8px;text-align:center;font-size:12px;line-height:1.6;color:#64748b;">
                    <strong style="color:#334155;">{{ $siteName }}</strong><br>
                    @if ($site['phone']){{ $site['phone'] }}@endif
                    @if ($site['phone'] && $site['email']) · @endif
                    @if ($site['email'])<a href="mailto:{{ $site['email'] }}" style="color:#64748b;">{{ $site['email'] }}</a>@endif
                    @if ($site['address'])<br>{{ $site['address'] }}@endif
                    <br><a href="{{ route('home') }}" style="color:{{ $primary }};text-decoration:none;">{{ preg_replace('#^https?://#', '', rtrim(route('home'), '/')) }}</a>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
