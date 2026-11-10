<x-layouts.store title="Favorilerim" :noindex="true">
    <div class="container-page py-10">
        <h1 class="text-3xl font-extrabold">Favorilerim</h1>
        @include('account._nav')
        <x-flash class="mt-6" />

        @if ($products->isEmpty())
            <div class="card mt-6 px-6 py-14 text-center">
                <x-icon name="heart" class="mx-auto size-10 text-slate-300" />
                <p class="mt-3 font-semibold">Favori listeniz boş.</p>
                <p class="mt-1 text-sm text-muted">Ürün sayfalarındaki kalp düğmesiyle ekleyebilirsiniz.</p>
            </div>
        @else
            <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-5">
                @foreach ($products as $product)
                    <div class="relative">
                        <x-product-card :product="$product" />
                        <form method="post" action="{{ route('favorites.toggle', $product) }}" class="absolute top-2 right-2">@csrf
                            <button class="rounded-lg bg-white/90 p-1.5 text-danger shadow-card hover:bg-danger-soft" aria-label="Favorilerden çıkar"><x-icon name="trash" class="size-4" /></button>
                        </form>
                    </div>
                @endforeach
            </div>
            <div class="mt-6">{{ $products->links() }}</div>
        @endif
    </div>
</x-layouts.store>
