<x-layouts.store>
    {{-- Arka zemin: hareketsiz, hafif renk geçişi ve kenarlarda noktalı ızgara (GPU yükü yok) --}}
    <div class="ambient-bg pointer-events-none fixed inset-0 -z-10" aria-hidden="true"><div class="ambient-dots absolute inset-0"></div></div>
    {{-- Bloklar Yönetim › Vitrin › Ana sayfa'dan eklenir, sıralanır ve düzenlenir. --}}
    @forelse ($blocks as $i => $row)
        @include('store.blocks.'.$row['block']->type, ['block' => $row['block'], 'data' => $row['data'], 'first' => $i === 0])
    @empty
        <section class="container-page py-20 text-center">
            <h1 class="text-3xl font-extrabold">{{ $settings['site_name'] }}</h1>
            <p class="mt-3 text-muted">{{ $settings['site_tagline'] }}</p>
        </section>
    @endforelse
</x-layouts.store>
