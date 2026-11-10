<x-layouts.store title="Siparişlerim" :noindex="true">
    <div class="container-page py-10">
        <h1 class="text-3xl font-extrabold">Siparişlerim</h1>
        @include('account._nav')
        <x-flash class="mt-6" />

        @if ($orders->isEmpty())
            <div class="card mt-6 px-6 py-14 text-center">
                <x-icon name="package" class="mx-auto size-10 text-slate-300" />
                <p class="mt-3 font-semibold">Henüz siparişiniz yok.</p>
                <a href="{{ route('products.index') }}" class="btn-primary mt-5">Ürünlere göz at</a>
            </div>
        @else
            <div class="mt-6 space-y-3">
                @foreach ($orders as $order)
                    <a href="{{ route('account.orders.show', $order) }}" class="card flex flex-wrap items-center gap-x-6 gap-y-2 p-4 transition hover:shadow-lift sm:p-5">
                        <span class="min-w-32"><span class="block font-display font-bold">{{ $order->number }}</span>
                            <span class="text-xs text-muted">{{ $order->created_at->locale('tr')->translatedFormat('j F Y') }}</span></span>
                        <span class="text-sm text-muted">{{ $order->items_count }} kalem</span>
                        <span class="badge {{ \App\Models\Order::tone($order->status) }}">{{ $order->shortStatus() }}</span>
                        <span class="ml-auto font-display text-lg font-bold">{{ \App\Support\Money::format($order->total) }}</span>
                        <x-icon name="chevron-right" class="size-4 text-muted" />
                    </a>
                @endforeach
            </div>
            <div class="mt-6">{{ $orders->links() }}</div>
        @endif
    </div>
</x-layouts.store>
