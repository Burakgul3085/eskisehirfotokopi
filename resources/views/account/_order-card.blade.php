@php
    use App\Models\Order;
    use App\Support\Money;
    /** Hesabım sipariş kartı: no, tarih, durum, ürün görselleri, mini adım çizelgesi ve işlemler. ($order: items yüklü) */
    $steps = array_slice(Order::SHORT, 0, 5, true);
    $current = $order->step();
    $images = $order->items->map(fn ($i) => ['image' => $i->meta['image'] ?? null, 'kind' => $i->kind, 'title' => $i->title])->take(4);
    $more = $order->items->count() - $images->count();
@endphp
<article class="account-order card overflow-hidden">
    <div class="flex flex-wrap items-center gap-x-5 gap-y-3 border-b border-line px-5 py-4">
        <div class="min-w-0">
            <p class="text-xs text-muted">Sipariş no</p>
            <p class="font-display font-extrabold">{{ $order->number }}</p>
        </div>
        <div class="hidden sm:block">
            <p class="text-xs text-muted">Tarih</p>
            <p class="text-sm font-semibold">{{ $order->created_at->locale('tr')->translatedFormat('j F Y') }}</p>
        </div>
        <div>
            <p class="text-xs text-muted">Tutar</p>
            <p class="text-sm font-bold">{{ Money::format($order->total) }}</p>
        </div>
        <span class="badge {{ Order::tone($order->status) }} ml-auto gap-1.5 px-3 py-1">
            @if ($order->isOpen())<span class="relative flex size-1.5"><span class="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-60"></span><span class="relative inline-flex size-1.5 rounded-full bg-current"></span></span>@endif
            {{ $order->statusLabel() }}
        </span>
    </div>

    <div class="grid items-center gap-5 px-5 py-4 md:grid-cols-[auto_1fr_auto]">
        {{-- Ürün görselleri --}}
        <div class="flex items-center -space-x-3">
            @foreach ($images as $img)
                <span class="flex size-14 items-center justify-center overflow-hidden rounded-xl bg-white ring-2 ring-white shadow-card" title="{{ $img['title'] }}">
                    @if ($img['image'])<img src="{{ $img['image'] }}" alt="" class="max-h-full max-w-full object-contain" loading="lazy">
                    @else<span class="flex size-full items-center justify-center bg-surface text-slate-400"><x-icon name="{{ $img['kind'] === 'print' ? 'printer' : 'book' }}" class="size-5" /></span>@endif
                </span>
            @endforeach
            @if ($more > 0)<span class="flex size-14 items-center justify-center rounded-xl bg-surface text-sm font-bold text-muted ring-2 ring-white">+{{ $more }}</span>@endif
        </div>

        {{-- Mini adım çizelgesi --}}
        @if ($current === null)
            <p class="flex items-center gap-2 text-sm font-semibold text-danger"><x-icon name="alert" class="size-4" />İptal edildi{{ $order->cancel_reason ? ': '.$order->cancel_reason : '' }}</p>
        @else
            <ol class="account-steps grid grid-cols-5 gap-1.5" aria-label="Sipariş durumu">
                @foreach ($steps as $key => $label)
                    @php($state = $loop->index < $current || $order->status === 'delivered' ? 'done' : ($loop->index === $current ? 'now' : 'next'))
                    <li class="min-w-0" data-state="{{ $state }}">
                        <span class="account-step-bar block h-1.5 rounded-full"></span>
                        <span class="mt-1.5 block truncate text-[11px] font-semibold {{ $state === 'next' ? 'text-muted' : 'text-ink' }}">{{ $label }}</span>
                    </li>
                @endforeach
            </ol>
        @endif

        <div class="flex flex-wrap gap-2 md:justify-end">
            <a href="{{ route('account.orders.show', $order) }}" class="btn-primary btn-sm"><x-icon name="truck" class="size-4" />{{ $order->isOpen() ? 'Takip et' : 'Detay' }}</a>
            @if (! $order->isOpen() && $order->items->whereNotNull('product_id')->isNotEmpty())
                <form method="post" action="{{ route('account.orders.reorder', $order) }}">@csrf
                    <button class="btn-ghost btn-sm"><x-icon name="cart" class="size-4" />Tekrar al</button></form>
            @endif
        </div>
    </div>
</article>
