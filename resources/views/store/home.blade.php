<x-layouts.store>
    {{-- Geçici vitrin: ana sayfa blokları (slider, kategoriler, yayınevleri, ürün satırları) Aşama 4'te yönetimden gelir. --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-primary-dark via-primary to-primary-hover text-white">
        <div class="pointer-events-none absolute -top-24 -right-24 size-96 rounded-full bg-accent/25 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-32 -left-20 size-96 rounded-full bg-white/10 blur-3xl"></div>
        <div class="container-page relative grid items-center gap-10 py-16 md:grid-cols-2 md:py-24">
            <div>
                <span class="badge bg-white/15 text-white ring-1 ring-white/20">Eskişehir içi kapıya teslim</span>
                <h1 class="mt-5 text-4xl leading-[1.1] font-extrabold sm:text-5xl">{{ $settings['site_tagline'] }}</h1>
                <p class="mt-5 max-w-lg text-lg text-white/80">Aradığın kitabı saniyeler içinde bul, siparişini ver; ödemeyi kapıda nakit veya kartla yap.</p>
                <form action="{{ url('/') }}" method="get" role="search" class="mt-8 flex max-w-lg gap-2 rounded-2xl bg-white p-1.5 shadow-lift">
                    <input name="q" type="search" placeholder="Kitap adı, yayınevi veya ISBN" aria-label="Ara"
                           class="min-w-0 flex-1 rounded-xl px-4 text-[15px] text-ink placeholder:text-slate-400 focus:outline-none">
                    <button class="btn-accent px-5">Ara</button>
                </form>
            </div>
            <div class="hidden justify-end md:flex">
                <div class="grid w-full max-w-sm grid-cols-2 gap-4">
                    @foreach ([['book', 'Kitap ve soru bankası'], ['printer', 'Belge baskısı'], ['truck', 'Kapıya teslim'], ['cash', 'Kapıda ödeme']] as $i => [$icon, $text])
                        <div class="rounded-3xl bg-white/10 p-5 ring-1 ring-white/15 backdrop-blur {{ $i % 2 ? 'translate-y-6' : '' }}">
                            <x-icon :name="$icon" class="size-8 text-accent" />
                            <p class="mt-6 font-display font-semibold">{{ $text }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="container-page py-14">
        <div class="card p-8 text-center">
            <h2 class="text-2xl font-bold">Vitrin hazırlanıyor</h2>
            <p class="mx-auto mt-2 max-w-xl text-muted">Kategoriler, yayınevleri ve ürünler yönetim panelinden eklendikçe burada görünecek.</p>
        </div>
    </section>
</x-layouts.store>
