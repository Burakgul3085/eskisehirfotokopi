<x-layouts.store :title="$address->exists ? 'Adresi düzenle' : 'Yeni adres'" :noindex="true">
    <div class="container-page max-w-2xl py-10">
        <x-breadcrumb :items="[['Adreslerim', route('account.addresses.index')], [$address->exists ? 'Düzenle' : 'Yeni adres', null]]" />
        <form method="post" action="{{ $address->exists ? route('account.addresses.update', $address) : route('account.addresses.store') }}" class="card mt-4 space-y-4 p-6 sm:p-8">
            @csrf
            @if ($address->exists) @method('put') @endif
            <h1 class="text-2xl font-extrabold">{{ $address->exists ? 'Adresi düzenle' : 'Yeni adres' }}</h1>
            <x-field name="title" label="Adres başlığı" :value="$address->title" required placeholder="Ev, iş, yurt…" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="name" label="Teslim alacak kişi" :value="$address->name" required />
                <x-field name="phone" type="tel" label="Telefon" :value="\App\Rules\TurkishPhone::format($address->phone)" required />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="f_district_id" class="field-label">İlçe<span class="text-danger"> *</span></label>
                    <select id="f_district_id" name="district_id" required class="field-input">
                        <option value="">Seçin</option>
                        @foreach ($districts as $d)<option value="{{ $d->id }}" @selected((int) old('district_id', $address->district_id) === $d->id)>{{ $d->name }}</option>@endforeach
                    </select>
                    @error('district_id')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <x-field name="neighborhood" label="Mahalle" :value="$address->neighborhood" required list="hoods" />
                <datalist id="hoods">@foreach ($districts->flatMap->neighborhoods->unique('name') as $n)<option value="{{ $n->name }}"></option>@endforeach</datalist>
            </div>
            <x-field name="address" type="textarea" label="Açık adres" :value="$address->address" required />
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_default" value="1" class="accent-primary" @checked(old('is_default', $address->is_default))> Varsayılan adresim olsun</label>
            <div class="flex justify-end gap-3">
                <a href="{{ route('account.addresses.index') }}" class="btn-ghost">Vazgeç</a>
                <button class="btn-primary">Kaydet</button>
            </div>
        </form>
    </div>
</x-layouts.store>
