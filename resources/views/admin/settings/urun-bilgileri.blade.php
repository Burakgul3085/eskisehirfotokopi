<x-layouts.admin title="Ayarlar · Ürün bilgileri">
    @include('admin.settings._layout')
    @php $hidden = \App\Support\Settings::hiddenSpecs(); @endphp
    <form method="post" action="{{ route('admin.settings.update', $group) }}" class="card max-w-3xl space-y-5 p-6 sm:p-8">
        @csrf @method('put')
        <div>
            <h2 class="font-bold">Ürün sayfasında gösterilecek bilgiler</h2>
            <p class="mt-1 text-sm text-muted">İşaretli bilgiler tüm ürünlerde müşteriye gösterilir. Bir ürünü ayrıca ayarlamak için ürün sayfasındaki “Müşteriye gösterilen bilgiler” bölümünü, birden çok ürün için Ürünler listesindeki toplu işlemi ya da toplu yükleme sonuç sayfasını kullanın; ürünün kendi seçimi bu ayarın önüne geçer.</p>
        </div>
        <div class="grid gap-2 sm:grid-cols-2">
            @foreach (\App\Models\Product::SPECS as $key => $label)
                <label class="flex items-center gap-3 rounded-xl border border-line px-4 py-3 text-sm has-checked:border-primary has-checked:bg-primary-soft">
                    <input type="hidden" name="show[{{ $key }}]" value="0">
                    <input type="checkbox" name="show[{{ $key }}]" value="1" @checked(! in_array($key, $hidden, true)) class="size-4 accent-primary">
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
        <p class="field-help">Gizlenen bilgi silinmez. ISBN ve ürün kodu gizliyken de müşteri numarayla arama yapınca kitabı bulur. “Ek bilgiler” çevirmen, dil gibi standart dışı satırların varsayılanıdır. Boş veya “bulunamadı / henüz doğrulanmadı” gibi değerler hiçbir zaman gösterilmez.</p>
        <div class="flex justify-end"><button class="btn-primary">Kaydet</button></div>
    </form>
</x-layouts.admin>
