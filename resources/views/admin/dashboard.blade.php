<x-layouts.admin title="Gösterge paneli">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Müşteri', $customerCount, 'users'],
            ['Son 7 gün yeni üye', $newCustomers, 'user'],
            ['Bugünkü sipariş', '—', 'cart'],
            ['Aktif ürün', '—', 'book'],
        ] as [$label, $value, $icon])
            <div class="card flex items-center gap-4 p-5">
                <span class="flex size-11 items-center justify-center rounded-xl bg-primary-soft text-primary"><x-icon :name="$icon" /></span>
                <div>
                    <p class="text-sm text-muted">{{ $label }}</p>
                    <p class="font-display text-2xl font-bold">{{ $value }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <section class="card mt-6 overflow-hidden">
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
</x-layouts.admin>
