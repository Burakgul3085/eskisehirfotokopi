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

    <div class="mb-6 max-w-4xl"><x-admin.print-colors :settings="$s" /></div>

    <form method="post" action="{{ route('admin.print.update') }}" class="max-w-4xl space-y-6">
        @csrf @method('put')

        <section class="card space-y-4 p-6">
            <h2 class="font-bold">Genel</h2>
            <div class="flex flex-wrap gap-3">
                @foreach (['enabled' => 'Fotokopi siparişi açık', 'a3_enabled' => 'A3 baskı'] as $key => $label)
                    <label class="flex items-center gap-2 rounded-xl border border-line px-4 py-2.5 text-sm has-checked:border-primary has-checked:bg-primary-soft">
                        <input type="checkbox" name="{{ $key }}" value="1" @checked($check($key)) class="accent-primary"> {{ $label }}
                    </label>
                @endforeach
            </div>
            <p class="field-help">Fotokopi kapalıyken /fotokopi sayfası ve sepetteki baskılar gizlenir.</p>
            <x-field name="intro" type="textarea" rows="2" label="Sayfa tanıtım metni" :value="$s['intro']" />
            <x-field name="prep_text" label="Hazırlık bilgisi" :value="$s['prep_text']" maxlength="200" />
        </section>

        {{-- /fotokopi sayfasındaki yazılar (sitede sabit metin yok) --}}
        <section class="card space-y-4 p-6">
            <h2 class="font-bold">Sayfa metinleri</h2>
            <p class="field-help">Belge Yükle sayfasındaki yazılar. Başlıklar boş bırakılırsa varsayılan yazılır; diğer alanlar boşsa gösterilmez.</p>
            <div class="grid gap-5 sm:grid-cols-3">
                <x-field name="page_kicker" label="Üst etiket" :value="$s['page_kicker']" maxlength="60" />
                <x-field name="page_title" label="Başlık (1. satır)" :value="$s['page_title']" maxlength="80" />
                <x-field name="page_title_accent" label="Başlık (2. satır, renkli)" :value="$s['page_title_accent']" maxlength="80" />
            </div>
            <x-field name="steps" type="textarea" rows="3" label="Adımlar" :value="$s['steps']"
                     help="Her satır: simge|Başlık|Açıklama (en fazla 4). Simgeler: upload, settings, cart, printer, truck, check, clock, shield, layers, book." />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="upload_title" label="Yükleme kartı başlığı" :value="$s['upload_title']" maxlength="80" />
                <x-field name="upload_subtitle" label="Yükleme kartı açıklaması" :value="$s['upload_subtitle']" maxlength="200" />
                <x-field name="drop_title" label="Sürükle-bırak alanı başlığı" :value="$s['drop_title']" maxlength="80" />
                <x-field name="drop_pick" label="“Seç” bağlantısı" :value="$s['drop_pick']" maxlength="60" />
            </div>
            <x-field name="consent_text" type="textarea" rows="2" label="Onay kutusu metni" :value="$s['consent_text']" maxlength="300" />
            <x-field name="consent_hint" label="Onay verilmeden gösterilen ipucu" :value="$s['consent_hint']" maxlength="200" />
            <x-field name="tip_text" type="textarea" rows="2" label="İpucu kutusu" :value="$s['tip_text']" maxlength="400" />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="price_title" label="Fiyat listesi başlığı" :value="$s['price_title']" maxlength="60" />
                <x-field name="price_subtitle" label="Fiyat listesi açıklaması" :value="$s['price_subtitle']" maxlength="200" />
                <x-field name="price_badge" label="Üst alandaki fiyat rozetinin alt yazısı" :value="$s['price_badge']" maxlength="80" />
                <x-field name="price_best_label" label="En ucuz kademe etiketi" :value="$s['price_best_label']" maxlength="40" />
            </div>
        </section>

        <section class="card overflow-x-auto">
            <div class="p-6 pb-0">
                <h2 class="font-bold">Yaprak başı fiyat kademeleri (₺)</h2>
                <p class="mt-1 text-sm text-muted">Kademe, kopya başı yaprak sayısına göre seçilir: önlü arkalı 1'e 1'de yaprak = sayfa ÷ 2, 2'ye 2'de yaprak = sayfa ÷ 4. Ör. 100 sayfa 1'e 1 = 50 yaprak → ilk kademe. İlk kademe 1. yapraktan başlamalı. Satırı silmek için başlangıcı boş bırakın.</p>
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
