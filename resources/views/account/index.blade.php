@php
    use App\Models\Order;
    use App\Support\Money;
    $first = preg_split('/\s+/u', trim($user->name))[0] ?? $user->name;
    $tiles = [
        ['Siparişler', $stats['orders'], 'package', 'account.orders', [], 'tone-a'],
        ['Devam eden', $stats['open'], 'truck', 'account.orders', ['durum' => 'devam'], 'tone-b'],
        ['Favoriler', $stats['favorites'], 'heart', 'account.favorites', [], 'tone-c'],
        ['Adresler', $stats['addresses'], 'pin', 'account.addresses.index', [], 'tone-d'],
    ];
@endphp
<x-account-layout title="Hesabım" :heading="'Merhaba, '.$first" :text="$settings['account_overview_text']">
    <x-slot:actions>
        <a href="{{ route('products.index') }}" class="btn-primary">Alışverişe devam et<x-icon name="arrow-right" class="size-4" /></a>
    </x-slot:actions>

    {{-- Özet kutucukları --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        @foreach ($tiles as [$label, $value, $icon, $route, $params, $tone])
            <a href="{{ route($route, $params) }}" class="account-tile card group p-5" style="--i: {{ $loop->index }}">
                <span class="account-tile-icon {{ $tone }} flex size-11 items-center justify-center rounded-2xl"><x-icon :name="$icon" class="size-5" /></span>
                <span class="mt-4 block font-display text-3xl font-extrabold">{{ $value }}</span>
                <span class="mt-0.5 flex items-center justify-between text-sm text-muted">{{ $label }}<x-icon name="arrow-right" class="size-4 opacity-0 transition group-hover:translate-x-0.5 group-hover:opacity-100" /></span>
            </a>
        @endforeach
    </div>

    {{-- Devam eden siparişler: adım çizelgesiyle --}}
    @if ($active->isNotEmpty())
        <section class="mt-8">
            <h2 class="flex items-center gap-2.5 text-lg font-extrabold"><span class="relative flex size-2.5"><span class="absolute inline-flex size-full animate-ping rounded-full bg-primary opacity-50"></span><span class="relative inline-flex size-2.5 rounded-full bg-primary"></span></span>Devam eden siparişler</h2>
            <div class="mt-4 space-y-4">
                @foreach ($active as $order)
                    @include('account._order-card')
                @endforeach
            </div>
        </section>
    @endif

    {{-- Son siparişler --}}
    <section class="card mt-8 overflow-hidden">
        <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
            <h2 class="flex items-center gap-3 font-extrabold"><span class="flex size-9 items-center justify-center rounded-xl bg-accent-soft text-accent-ink"><x-icon name="package" class="size-4" /></span>Son siparişlerim</h2>
            @if ($recent->isNotEmpty())<a href="{{ route('account.orders') }}" class="text-sm font-semibold text-primary hover:underline">Tümünü gör</a>@endif
        </div>
        @if ($recent->isEmpty())
            <div class="px-6 py-12 text-center">
                <span class="account-empty-icon mx-auto flex size-16 items-center justify-center rounded-2xl"><x-icon name="cart" class="size-7" /></span>
                <p class="mt-4 font-display font-extrabold">Henüz siparişiniz yok.</p>
                <p class="mt-1 text-sm text-muted">İlk siparişinizi verdiğinizde buradan adım adım takip edebilirsiniz.</p>
                <div class="mt-5 flex flex-wrap justify-center gap-2">
                    <a href="{{ route('products.index') }}" class="btn-primary">Kitaplara göz at</a>
                    <a href="{{ route('print.index') }}" class="btn-ghost"><x-icon name="printer" class="size-4" />Belge yükle</a>
                </div>
            </div>
        @else
            <ul class="divide-y divide-line">
                @foreach ($recent as $order)
                    <li>
                        <a href="{{ route('account.orders.show', $order) }}" class="search-hit flex flex-wrap items-center gap-x-5 gap-y-1 px-5 py-3.5">
                            <span class="min-w-28 font-display font-bold">{{ $order->number }}</span>
                            <span class="text-sm text-muted">{{ $order->created_at->locale('tr')->translatedFormat('j M Y') }} · {{ $order->items_count }} kalem</span>
                            <span class="badge {{ Order::tone($order->status) }} ml-auto">{{ $order->shortStatus() }}</span>
                            <span class="w-24 text-right font-bold">{{ Money::format($order->total) }}</span>
                            <x-icon name="chevron-right" class="size-4 text-slate-300" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Kısayollar --}}
    <div class="mt-8 grid gap-4 sm:grid-cols-3">
        @foreach ([
            ['printer', 'Belge yükle', 'Fotokopi ve çıktınızı sipariş edin', route('print.index')],
            ['lock', 'Bilgilerim ve parola', 'Ad, e-posta, telefon ve parola', route('account.settings')],
            ['chat', 'Bize yazın', 'Sorunuz mu var? Hemen yanıtlayalım', route('contact')],
        ] as [$icon, $title, $text, $url])
            <a href="{{ $url }}" class="account-shortcut card group flex items-center gap-4 p-5">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary-soft text-primary transition group-hover:bg-primary group-hover:text-white"><x-icon :name="$icon" class="size-5" /></span>
                <span class="min-w-0"><span class="block font-bold">{{ $title }}</span><span class="block truncate text-sm text-muted">{{ $text }}</span></span>
            </a>
        @endforeach
    </div>

    {{-- Favoriler önizleme --}}
    @if ($favorites->isNotEmpty())
        <section class="mt-8">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-extrabold">Favorilerinizden</h2>
                <a href="{{ route('account.favorites') }}" class="text-sm font-semibold text-primary hover:underline">Tümünü gör</a>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 xl:grid-cols-5">
                @foreach ($favorites as $product)<x-product-card :product="$product" />@endforeach
            </div>
        </section>
    @endif
</x-account-layout>
