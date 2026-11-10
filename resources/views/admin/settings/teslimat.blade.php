@php
    $closed = array_map('intval', explode(',', (string) $s['delivery_closed_days']));
    $days = [1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba', 4 => 'Perşembe', 5 => 'Cuma', 6 => 'Cumartesi', 7 => 'Pazar'];
    $money = fn ($v) => filled($v) ? \App\Support\Money::format($v, false) : null;
@endphp
<x-layouts.admin title="Ayarlar · Teslimat ve sipariş">
    @include('admin.settings._layout')
    <form method="post" action="{{ route('admin.settings.update', $group) }}" class="card max-w-3xl space-y-6 p-6 sm:p-8">
        @csrf @method('put')
        <fieldset>
            <legend class="field-label">Sipariş alımı</legend>
            <div class="flex flex-wrap gap-3">
                @foreach (['1' => 'Açık', '0' => 'Geçici olarak kapalı'] as $v => $l)
                    <label class="flex items-center gap-2 rounded-xl border border-line px-4 py-2.5 text-sm has-checked:border-primary has-checked:bg-primary-soft">
                        <input type="radio" name="orders_open" value="{{ $v }}" @checked(old('orders_open', $s['orders_open']) === (string) $v) class="accent-primary"> {{ $l }}
                    </label>
                @endforeach
            </div>
            <p class="field-help">Kapalıyken site ve sepet çalışır, yalnız sipariş onaylanamaz.</p>
        </fieldset>
        <x-field name="orders_closed_message" label="Sipariş kapalı mesajı" :value="$s['orders_closed_message']" />

        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="delivery_free_threshold" label="Ücretsiz teslimat eşiği (₺)" :value="$money($s['delivery_free_threshold'])" inputmode="decimal" help="Bu tutar ve üstü ücretsiz. Boşsa her siparişte ücret alınır." />
            <x-field name="delivery_min_order" label="En düşük sipariş tutarı (₺)" :value="$money($s['delivery_min_order'])" inputmode="decimal" help="Boşsa sınır yok." />
        </div>
        <p class="-mt-2 text-xs text-muted">Teslimat ücretleri ilçe bazında <a href="{{ route('admin.districts.index') }}" class="text-primary hover:underline">Teslimat bölgeleri</a> sayfasından girilir; ilçeye özel eşik bu genel değerleri ezer.</p>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="delivery_cutoff" type="time" label="Aynı gün teslimat için son saat" :value="$s['delivery_cutoff']" required />
            <fieldset>
                <legend class="field-label">Aynı gün teslimat</legend>
                <div class="flex gap-3">
                    @foreach (['1' => 'Var', '0' => 'Yok (ertesi gün)'] as $v => $l)
                        <label class="flex items-center gap-2 rounded-xl border border-line px-4 py-2.5 text-sm has-checked:border-primary has-checked:bg-primary-soft">
                            <input type="radio" name="delivery_same_day" value="{{ $v }}" @checked(old('delivery_same_day', $s['delivery_same_day']) === (string) $v) class="accent-primary"> {{ $l }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        </div>

        <fieldset>
            <legend class="field-label">Teslimat yapılmayan günler</legend>
            <div class="flex flex-wrap gap-2">
                @foreach ($days as $n => $label)
                    <label class="flex items-center gap-2 rounded-xl border border-line px-3 py-2 text-sm has-checked:border-danger has-checked:bg-danger-soft">
                        <input type="checkbox" name="delivery_closed_days[]" value="{{ $n }}" @checked(in_array($n, old('delivery_closed_days', $closed))) class="accent-danger"> {{ $label }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        <x-field name="delivery_slots" type="textarea" rows="4" label="Teslimat saat aralıkları" :value="$s['delivery_slots']" help="Her satıra bir aralık (ör. 10:00 - 13:00). Boş bırakılırsa müşteriye saat sorulmaz." />

        <fieldset>
            <legend class="field-label">Kapıda ödeme yöntemleri</legend>
            <div class="flex flex-wrap gap-3">
                @foreach (\App\Models\Order::PAYMENT_METHODS as $key => $label)
                    <label class="flex items-center gap-2 rounded-xl border border-line px-4 py-2.5 text-sm has-checked:border-primary has-checked:bg-primary-soft">
                        <input type="checkbox" name="payment_{{ $key }}" value="1" @checked(old('payment_'.$key, $s['payment_'.$key]) === '1') class="accent-primary"> {{ $label }}
                    </label>
                @endforeach
            </div>
            @error('payment_cash')<p class="field-error">{{ $message }}</p>@enderror
        </fieldset>

        <x-field name="delivery_info" type="textarea" rows="3" label="Teslimat bilgi metni" :value="$s['delivery_info']" help="Sepet ve sipariş sayfasında gösterilir." />
        <div class="flex justify-end"><button class="btn-primary">Kaydet</button></div>
    </form>
</x-layouts.admin>
