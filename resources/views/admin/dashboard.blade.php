@php
    use App\Models\Order;
    use App\Support\Money;
@endphp
<x-layouts.admin title="Gösterge paneli">
    @if ($stuckJobs)
        <p class="mb-4 flex items-start gap-2 rounded-xl bg-danger-soft px-4 py-3 text-sm text-danger"><x-icon name="alert" class="mt-0.5 size-4 shrink-0" />
            <span><strong>Arka plan işleri bekliyor ({{ $stuckJobs }}).</strong> Kuyruk işçisi çalışmıyor olabilir: fotokopi belgeleri işlenmez, toplu yükleme ilerlemez, e-postalar gitmez. Sunucuda supervisor'ı, yerelde <code>php artisan yerel:sunucu</code> komutunu kontrol edin.</span></p>
    @endif
    @if ($failedJobs)
        <p class="mb-4 flex items-start gap-2 rounded-xl bg-accent-soft px-4 py-3 text-sm text-accent-ink"><x-icon name="alert" class="mt-0.5 size-4 shrink-0" />
            <span>Son 24 saatte {{ $failedJobs }} arka plan işi hata verdi. Ayrıntı: <a href="{{ route('admin.mail-logs', ['durum' => 'hata']) }}" class="font-semibold underline">E-posta kayıtları</a> ve sunucu günlüğü.</span></p>
    @endif
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Bugünkü sipariş', $todayOrders, Money::format($todayTotal), 'cart', route('admin.orders.index', ['baslangic' => today()->toDateString()])],
            ['Açık sipariş', $openOrders, 'Bugün teslim: '.$todayDeliveries, 'package', route('admin.orders.index', ['durum' => 'acik'])],
            ['Okunmamış mesaj', $unreadMessages, null, 'chat', route('admin.messages.index')],
            ['Satıştaki ürün', number_format($activeProducts, 0, ',', '.'), null, 'book', route('admin.products.index')],
        ] as [$label, $value, $sub, $icon, $url])
            <a href="{{ $url }}" class="card flex items-center gap-4 p-5 hover:ring-2 hover:ring-primary/20">
                <span class="flex size-11 items-center justify-center rounded-xl bg-primary-soft text-primary"><x-icon :name="$icon" /></span>
                <div>
                    <p class="text-sm text-muted">{{ $label }}</p>
                    <p class="font-display text-2xl font-bold">{{ $value }}</p>
                    @if ($sub)<p class="text-xs text-muted">{{ $sub }}</p>@endif
                </div>
            </a>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="card overflow-hidden">
            <h2 class="flex items-center justify-between border-b border-line px-5 py-4 font-bold">Son siparişler
                <a href="{{ route('admin.orders.index') }}" class="text-sm font-semibold text-primary hover:underline">Tümü</a></h2>
            @forelse ($recentOrders as $o)
                <a href="{{ route('admin.orders.show', $o) }}" class="flex items-center gap-3 border-b border-line/70 px-5 py-3 text-sm last:border-0 hover:bg-surface">
                    <span class="font-display font-bold text-primary">{{ $o->number }}</span>
                    <span class="min-w-0 flex-1 truncate">{{ $o->customer_name }} <span class="text-muted">· {{ $o->district_name }}</span></span>
                    <span class="font-semibold whitespace-nowrap">{{ Money::format($o->total) }}</span>
                    <span class="badge {{ Order::tone($o->status) }}">{{ $o->shortStatus() }}</span>
                </a>
            @empty
                <p class="px-5 py-6 text-sm text-muted">Henüz sipariş yok.</p>
            @endforelse
        </section>

        <section class="card overflow-hidden">
            <h2 class="border-b border-line px-5 py-4 font-bold">Son işlemler</h2>
            @forelse ($recentActivity as $log)
                <div class="flex items-center justify-between gap-4 border-b border-line/70 px-5 py-3 text-sm last:border-0">
                    <span><span class="font-semibold">{{ $log->admin?->name ?? 'Sistem' }}</span> · <span class="text-muted">{{ __('activity.'.$log->action) }}</span></span>
                    <time class="shrink-0 text-xs text-muted" datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->diffForHumans() }}</time>
                </div>
            @empty
                <p class="px-5 py-6 text-sm text-muted">Henüz işlem yok.</p>
            @endforelse
        </section>
    </div>
</x-layouts.admin>
