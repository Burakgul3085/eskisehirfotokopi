<x-layouts.store>
    {{-- Arka zemin: hareli ışık + noktalı ızgara (CSS) ve üzerinde süzülen 3D kâğıt evreni (resources/js/scenes/ambient.js) --}}
    <div data-scene="ambient" class="ambient-bg pointer-events-none fixed inset-0 -z-10 overflow-hidden" aria-hidden="true">
        <div class="ambient-orb ambient-orb-1"></div>
        <div class="ambient-orb ambient-orb-2"></div>
        <div class="ambient-orb ambient-orb-3"></div>
        <div class="ambient-dots absolute inset-0"></div>
        <canvas data-scene-canvas class="scene-canvas ambient-canvas absolute inset-0 block size-full"></canvas>
    </div>
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
