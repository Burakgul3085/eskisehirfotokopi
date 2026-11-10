@php
    $money = fn ($v) => $v !== null ? \App\Support\Money::format($v, false) : null;
@endphp
<x-layouts.admin :title="$district->exists ? $district->name : 'Yeni ilçe'">
    <form method="post" action="{{ $district->exists ? route('admin.districts.update', $district) : route('admin.districts.store') }}" class="card max-w-3xl space-y-5 p-6 sm:p-8">
        @csrf
        @if ($district->exists) @method('put') @endif
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="name" label="İlçe adı" :value="$district->name" required />
            <fieldset>
                <legend class="field-label">Teslimat</legend>
                <div class="flex flex-wrap gap-3">
                    @foreach (['1' => 'Açık', '0' => 'Kapalı'] as $v => $l)
                        <label class="flex items-center gap-2 rounded-xl border border-line px-4 py-2.5 text-sm has-checked:border-primary has-checked:bg-primary-soft">
                            <input type="radio" name="is_active" value="{{ $v }}" @checked((string) old('is_active', (int) $district->is_active) === (string) $v) class="accent-primary"> {{ $l }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        </div>
        <div class="grid gap-5 sm:grid-cols-4">
            <x-field name="fee" label="Teslimat ücreti (₺)" :value="$money($district->fee)" required inputmode="decimal" />
            <x-field name="free_threshold" label="Ücretsiz eşiği (₺)" :value="$money($district->free_threshold)" inputmode="decimal" help="Boşsa genel ayar." />
            <x-field name="min_order" label="En düşük sipariş (₺)" :value="$money($district->min_order)" inputmode="decimal" help="Boşsa genel ayar." />
            <x-field name="extra_days" type="number" label="Ek gün" :value="$district->extra_days ?? 0" min="0" max="14" required help="Uzak ilçede teslim gecikmesi." />
        </div>
        <x-field name="note" label="Kısa not" :value="$district->note" help="Yalnız yönetimde görünür (ör. Salı ve Cuma gidiliyor)." />
        <x-field name="neighborhoods" type="textarea" rows="10" label="Mahalleler" :value="old('neighborhoods', $hoodText)"
                 help="İsteğe bağlı. Her satıra bir mahalle. Boşsa müşteri mahalleyi kendisi yazar; doluysa yalnız listedekiler seçilebilir. Mahalleye özel ücret: Mahalle|60,00 — geçici kapatmak: Mahalle|kapalı" />
        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.districts.index') }}" class="btn-ghost">Vazgeç</a>
            <button class="btn-primary">Kaydet</button>
        </div>
    </form>
</x-layouts.admin>
