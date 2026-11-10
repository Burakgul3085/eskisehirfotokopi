@php
    $tiers = array_values(old('tiers', $s['tiers']));
    $tiers = array_pad($tiers, max(5, count($tiers) + 2), ['from' => '', 'bw' => '', 'color' => '']);
    $money = fn ($v) => filled($v) && is_numeric($v) ? \App\Support\Money::format($v, false) : $v;
    $check = fn (string $key) => (bool) old($key, $s[$key]);
@endphp
<x-layouts.admin title="Fotokopi ayarları">
    <x-slot:actions>
        <a href="{{ route('print.index') }}" target="_blank" class="btn-ghost btn-sm"><x-icon name="external" class="size-4" />Fotokopi sayfası</a>
    </x-slot:actions>

    <x-flash class="mb-5" />
    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-danger/20 bg-danger-soft px-4 py-3 text-sm font-medium text-danger" role="alert">Lütfen işaretli alanları düzeltin.</div>
    @endif

    <form method="post" action="{{ route('admin.print.update') }}" class="max-w-4xl space-y-6">
        @csrf @method('put')

        <section class="card space-y-4 p-6">
            <h2 class="font-bold">Genel</h2>
            <div class="flex flex-wrap gap-3">
                @foreach (['enabled' => 'Fotokopi siparişi açık', 'bw_enabled' => 'Siyah-beyaz baskı', 'color_enabled' => 'Renkli baskı', 'a3_enabled' => 'A3 baskı'] as $key => $label)
                    <label class="flex items-center gap-2 rounded-xl border border-line px-4 py-2.5 text-sm has-checked:border-primary has-checked:bg-primary-soft">
                        <input type="checkbox" name="{{ $key }}" value="1" @checked($check($key)) class="accent-primary"> {{ $label }}
                    </label>
                @endforeach
            </div>
            @error('color_enabled')<p class="field-error">{{ $message }}</p>@enderror
            <p class="field-help">Makine arızasında bir renk geçici kapatılabilir. Fotokopi kapalıyken /fotokopi sayfası ve sepetteki baskılar gizlenir.</p>
            <x-field name="intro" type="textarea" rows="2" label="Sayfa tanıtım metni" :value="$s['intro']" />
            <x-field name="prep_text" label="Hazırlık bilgisi" :value="$s['prep_text']" maxlength="200" />
        </section>

        <section class="card overflow-x-auto">
            <div class="p-6 pb-0">
                <h2 class="font-bold">Yaprak başı fiyat kademeleri (₺)</h2>
                <p class="mt-1 text-sm text-muted">Kademe, kopya başı yaprak sayısına göre seçilir: tek yüzde yaprak = sayfa, önlü arkalıda yaprak = sayfa ÷ 2. Ör. 100 sayfa önlü arkalı = 50 yaprak → ilk kademe. İlk kademe 1. yapraktan başlamalı. Satırı silmek için başlangıcı boş bırakın.</p>
            </div>
            @error('tiers')<p class="px-6 pt-3 text-sm text-danger">{{ $message }}</p>@enderror
            @foreach ($warnings as $warning)
                <p class="mx-6 mt-3 flex items-start gap-2 rounded-lg bg-warning-soft px-3 py-2 text-sm text-warning"><x-icon name="alert" class="mt-0.5 size-4 shrink-0" />{{ $warning }}</p>
            @endforeach
            <table class="table-panel mt-3">
                <thead><tr><th>Başlangıç yaprağı</th><th>Siyah-beyaz yaprak</th><th>Renkli yaprak</th></tr></thead>
                <tbody>
                    @foreach ($tiers as $i => $row)
                        <tr>
                            <td><x-field :name="'tiers['.$i.'][from]'" type="number" min="1" :value="$row['from']" aria-label="Başlangıç yaprağı" /></td>
                            <td><x-field :name="'tiers['.$i.'][bw]'" inputmode="decimal" :value="$money($row['bw'] ?? '')" aria-label="Siyah-beyaz yaprak fiyatı" /></td>
                            <td><x-field :name="'tiers['.$i.'][color]'" inputmode="decimal" :value="$money($row['color'] ?? '')" aria-label="Renkli yaprak fiyatı" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="card grid gap-5 p-6 sm:grid-cols-2">
            <x-field name="spiral_price" label="Spiral cilt (kopya başı, ₺)" :value="$money($s['spiral_price'])" inputmode="decimal" required />
            <x-field name="a3_multiplier" label="A3 fiyat çarpanı" :value="$money($s['a3_multiplier'])" inputmode="decimal" help="Ör. 2,00: A3 yaprak fiyatı A4'ün iki katı." />
            <x-field name="quote_threshold" type="number" min="2" label="Özel teklif eşiği (sayfa)" :value="$s['quote_threshold']" help="Ör. 1000: bu sayfa ve üzerinde fiyat verilmez, müşteri sizi arar. Boş: sınır yok." />
            <x-field name="quote_text" type="textarea" rows="2" label="Özel teklif metni" :value="$s['quote_text']" />
        </section>

        <section class="card grid gap-5 p-6 sm:grid-cols-2 lg:grid-cols-4">
            <x-field name="max_mb" type="number" min="1" max="500" label="Dosya başına en büyük (MB)" :value="$s['max_mb']" required />
            <x-field name="max_copies" type="number" min="1" max="1000" label="En fazla kopya" :value="$s['max_copies']" required />
            <x-field name="unordered_days" type="number" min="1" max="90" label="Siparişsiz belge (gün)" :value="$s['unordered_days']" required help="Bu süre sonunda silinir." />
            <x-field name="ordered_days" type="number" min="1" max="365" label="Siparişteki belge (gün)" :value="$s['ordered_days']" required help="Bu süre boyunca indirilebilir." />
        </section>

        <div class="flex justify-end"><button class="btn-primary">Kaydet</button></div>
    </form>
</x-layouts.admin>
