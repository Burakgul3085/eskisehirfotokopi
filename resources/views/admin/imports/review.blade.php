<x-layouts.admin title="Kitap kontrolü">
    <x-slot:actions>
        <a href="{{ route('admin.imports.show', $item->batch_id) }}" class="btn-ghost btn-sm">Listeye dön</a>
        @if ($next)<a href="{{ route('admin.imports.review', $next) }}" class="btn-ghost btn-sm">Atla<x-icon name="chevron-right" class="size-4" /></a>@endif
    </x-slot:actions>

    @php
        // Alanın görseldeki kesiti: yazılanla görselde okunanı yan yana görmek için
        $crop = fn (string $field) => isset($crops[$field])
            ? '<span class="mt-1.5 flex items-center gap-2 text-xs text-muted">Görselde: <img src="'.e($crops[$field]).'" alt="" loading="lazy" class="max-h-9 max-w-full rounded border border-line bg-white"></span>'
            : '';
    @endphp

    <div class="grid gap-5 xl:grid-cols-2">
        <div class="space-y-4">
            <div class="card p-4">
                <p class="mb-1 text-xs text-muted">{{ $item->publisher_folder }}</p>
                <p class="font-semibold">{{ $item->folder }}</p>
                @if ($item->issues)
                    <ul class="mt-3 space-y-1 rounded-lg bg-warning-soft p-3 text-sm text-warning">@foreach ($item->issues as $i)<li>• {{ $i }}</li>@endforeach</ul>
                @endif
                @if (! empty($item->parsed['language']))
                    <p class="mt-2 text-xs text-muted">Görselde tanınan ikinci dil: {{ ['deu' => 'Almanca', 'ita' => 'İtalyanca', 'fra' => 'Fransızca', 'spa' => 'İspanyolca', 'eng' => 'İngilizce'][$item->parsed['language']] ?? $item->parsed['language'] }}</p>
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
            <div>
                <x-field name="title" label="Kitap adı" :value="$values['title']" required />
                {!! $crop('title') !!}
            </div>
            <div>
                <x-field name="subtitle" label="Alt başlık" :value="$values['subtitle']" help="İsteğe bağlı; ürün sayfasında adın altında görünür." />
                {!! $crop('subtitle') !!}
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><x-field name="author" label="Yazar" :value="$values['author']" />{!! $crop('author') !!}</div>
                <div><x-field name="publisher" label="Yayınevi" :value="$values['publisher']" help="Yoksa oluşturulur." />{!! $crop('publisher') !!}</div>
            </div>
            <div class="space-y-4 rounded-xl border border-line p-4">
                @if ($readPath !== '')
                    <p class="text-sm"><span class="text-muted">Görselde “Türü”:</span> {{ $readPath }}</p>
                @endif
                {!! $crop('category') !!}
                @include('admin.products._classification', ['values' => $values, 'help' => $readPath !== ''
                    ? 'Seçtiğiniz kategori hatırlanır; aynı “Türü” sonraki yüklemelerde kendiliğinden bu kategoriye gider.'
                    : null])
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div><x-field name="isbn" label="ISBN" :value="$values['isbn']" />{!! $crop('isbn') !!}</div>
                <div><x-field name="pages" label="Sayfa sayısı" type="number" :value="$values['pages']" />{!! $crop('pages') !!}</div>
                <div><x-field name="code" label="Ürün kodu" :value="$values['code']" />{!! $crop('code') !!}</div>
            </div>

            <details class="rounded-xl border border-line p-4" @if ($item->product?->extra_specs || $item->product?->spec_visibility) open @endif>
                <summary class="cursor-pointer text-sm font-semibold">Müşteriye gösterilen bilgiler ve ek bilgiler</summary>
                <div class="mt-4">@include('admin.products._specs', ['product' => $item->product])</div>
            </details>

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
