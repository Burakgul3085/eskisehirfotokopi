<x-layouts.admin title="Kitap kontrolü">
    <x-slot:actions>
        <a href="{{ route('admin.imports.show', $item->batch_id) }}" class="btn-ghost btn-sm">Listeye dön</a>
        @if ($next)<a href="{{ route('admin.imports.review', $next) }}" class="btn-ghost btn-sm">Atla<x-icon name="chevron-right" class="size-4" /></a>@endif
    </x-slot:actions>


    <div class="grid gap-5 xl:grid-cols-2">
        <div class="space-y-4">
            <div class="card p-4">
                <p class="mb-1 text-xs text-muted">{{ $item->publisher_folder }}</p>
                <p class="font-semibold">{{ $item->folder }}</p>
                @if ($item->issues)
                    <ul class="mt-3 space-y-1 rounded-lg bg-warning-soft p-3 text-sm text-warning">@foreach ($item->issues as $i)<li>• {{ $i }}</li>@endforeach</ul>
                @endif
            </div>
            @if ($item->info_path)
                <a href="{{ route('admin.imports.image', $item) }}" target="_blank" class="card block overflow-hidden">
                    <img src="{{ route('admin.imports.image', $item) }}" alt="Bilgi görseli" class="w-full">
                </a>
            @else
                <p class="card p-6 text-sm text-muted">Bilgi görseli yok veya saklama süresi doldu.</p>
            @endif
            @if ($item->product?->imageUrl('md'))
                <img src="{{ $item->product->imageUrl('md') }}" alt="" class="card max-h-80 w-auto">
            @endif
        </div>

        <form method="post" action="{{ route('admin.imports.update', $item) }}" class="card space-y-4 p-5">
            @csrf @method('put')
            <x-field name="title" label="Kitap adı" :value="$values['title']" required />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="author" label="Yazar" :value="$values['author']" />
                <x-field name="publisher" label="Yayınevi" :value="$values['publisher']" help="Yoksa oluşturulur." />
            </div>
            <x-field name="category" label="Kategori yolu" :value="$values['category']" help="Üst / Alt / Alt-alt (ör. Tarih / Avrupa Tarihi). Eksikler oluşturulur." />
            <div class="grid gap-4 sm:grid-cols-3">
                <x-field name="isbn" label="ISBN" :value="$values['isbn']" />
                <x-field name="pages" label="Sayfa sayısı" type="number" :value="$values['pages']" />
                <x-field name="code" label="Ürün kodu" :value="$values['code']" />
            </div>
            @if ($item->ocr_text)
                <details class="text-xs text-muted"><summary class="cursor-pointer">OCR ham metni</summary><pre class="mt-2 whitespace-pre-wrap rounded-lg bg-surface p-3 font-mono">{{ $item->ocr_text }}</pre></details>
            @endif
            <div class="flex flex-wrap gap-2 border-t border-line pt-4">
                <button class="btn-primary"><x-icon name="check" class="size-4" />Kaydet ve onayla</button>
            </div>
        </form>
    </div>
    @if ($item->product_id)
        <form method="post" action="{{ route('admin.imports.approve', $item) }}" class="mt-3 text-right">@csrf<button class="btn-ghost btn-sm">Değiştirmeden onayla</button></form>
    @endif
</x-layouts.admin>
