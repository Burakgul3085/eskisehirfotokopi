<x-layouts.store title="Sipariş takibi" description="Sipariş numaranız ve telefonunuzla siparişinizin durumunu görün.">
    <div class="container-page max-w-xl py-10 sm:py-14">
        <div class="card p-6 sm:p-8">
            <span class="flex size-12 items-center justify-center rounded-2xl bg-primary-soft text-primary"><x-icon name="truck" class="size-6" /></span>
            <h1 class="mt-4 text-2xl font-extrabold">Sipariş takibi</h1>
            <p class="mt-1 text-sm text-muted">Sipariş numaranızı ve siparişte yazdığınız telefonu girin.</p>
            <form method="post" action="{{ route('orders.track') }}" class="mt-6 space-y-4">
                @csrf
                <x-field name="number" label="Sipariş numarası" required placeholder="ES26…" autocomplete="off" class="uppercase" />
                <x-field name="phone" type="tel" label="Telefon" required placeholder="05xx xxx xx xx" inputmode="tel" />
                <button class="btn-primary w-full py-3">Sorgula</button>
            </form>
            @auth('web')
                <p class="mt-5 text-center text-sm"><a href="{{ route('account.orders') }}" class="font-semibold text-primary hover:underline">Siparişlerim</a></p>
            @endauth
        </div>
    </div>
</x-layouts.store>
