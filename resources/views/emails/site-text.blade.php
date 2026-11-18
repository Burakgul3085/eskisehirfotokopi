{{ $heading }}

@foreach ($lines as $line)
{{ $line }}

@endforeach
@if ($quote)
"{{ $quote }}"

@endif
@foreach ($facts as [$label, $value])
{{ $label }}: {{ $value }}
@endforeach
@foreach ($links as [$linkTitle, $linkText, $linkUrl])
- {{ $linkTitle }}: {{ $linkUrl }}
@endforeach
@if ($order && $items->isNotEmpty())

@foreach ($items as $item)
- {{ $item->title }} ({{ $item->quantity }} adet): {{ \App\Support\Money::format($item->line_total) }}
@endforeach
Toplam: {{ \App\Support\Money::format($order->total) }}
@endif
@if ($buttonUrl)

{{ $buttonText }}: {{ $buttonUrl }}
@endif

--
{{ $siteName }} · {{ route('home') }}
@if ($unsubscribeUrl)

{{ $site['newsletter_mail_footer'] }}
{{ $site['newsletter_unsub_link'] }}: {{ $unsubscribeUrl }}
@endif
