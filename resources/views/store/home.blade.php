<x-layouts.store>
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
