<x-account-layout title="Favorilerim" :text="$settings['account_favorites_text']">
    @if ($products->isEmpty())
        @include('account._empty', ['icon' => 'heart', 'title' => 'Favori listeniz boş.', 'text' => 'Beğendiğiniz ürünlerdeki kalbe dokunun, burada toplansın.', 'cta' => ['Ürünlere göz atın', route('products.index')]])
    @else
        <p class="text-sm text-muted">{{ $products->total() }} ürün</p>
        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 xl:grid-cols-4">
            @foreach ($products as $product)
                <div class="relative">
                    <x-product-card :product="$product" />
                    <form method="post" action="{{ route('favorites.toggle', $product) }}" class="absolute top-2 right-2 z-10">@csrf
                        <button class="rounded-xl bg-white/95 p-2 text-danger shadow-card transition hover:bg-danger-soft" aria-label="Favorilerden çıkar" title="Favorilerden çıkar"><x-icon name="trash" class="size-4" /></button>
                    </form>
                </div>
            @endforeach
        </div>
        <div class="mt-6">{{ $products->links() }}</div>
    @endif
</x-account-layout>
