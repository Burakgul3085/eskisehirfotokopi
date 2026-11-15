{{-- Kategori (en fazla iki kat), kitap türü, ders ve konu etiketleri. Değişkenler: $categories (CategoryTree::flat), $values --}}
@php
    $typeOptions = \App\Domain\Import\TitleCategory::types();
    $knownTypes = \App\Models\Product::whereNotNull('book_type')->distinct()->orderBy('book_type')->pluck('book_type')->all();
    $knownSubjects = \App\Models\Product::whereNotNull('subject')->distinct()->orderBy('subject')->limit(300)->pluck('subject')->all();
@endphp
<div>
    <label class="field-label" for="f_category_id">Kategori</label>
    <select id="f_category_id" name="category_id" class="field-input">
        <option value="">—</option>
        @foreach ($categories as $c)
            @php $selected = (int) old('category_id', $values['category_id'] ?? null) === $c['id']; @endphp
            @if ($c['depth'] <= 1 || $selected)
                <option value="{{ $c['id'] }}" @selected($selected)>{{ str_repeat('— ', $c['depth']) }}{{ $c['name'] }}</option>
            @endif
        @endforeach
    </select>
    @error('category_id')<p class="field-error">{{ $message }}</p>@enderror
    @if (! empty($help))<p class="field-help">{{ $help }}</p>@endif
</div>
<div class="grid gap-4 sm:grid-cols-2">
    <x-field name="book_type" label="Kitap türü" :value="$values['book_type'] ?? null" list="book-types" help="Soru Bankası, Deneme, Konu Anlatımı… (süzgeçte görünür)" />
    <x-field name="subject" label="Ders" :value="$values['subject'] ?? null" list="book-subjects" help="Matematik, Fizik… (süzgeçte görünür)" />
</div>
<datalist id="book-types">@foreach (array_unique([...$typeOptions, ...$knownTypes]) as $t)<option value="{{ $t }}"></option>@endforeach</datalist>
<datalist id="book-subjects">@foreach ($knownSubjects as $s)<option value="{{ $s }}"></option>@endforeach</datalist>
<x-field name="topics" label="Konular" :value="implode(', ', $values['topics'] ?? [])" help="Virgülle ayırın (ör. Parçacık Fiziği, Kozmoloji). Aramada bulunur, ürün sayfasında gösterilebilir." />
