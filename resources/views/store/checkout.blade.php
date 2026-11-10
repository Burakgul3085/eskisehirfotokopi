@php
    use App\Support\Money;
    $initial = [
        'name' => old('name', $user?->name),
        'phone' => old('phone', $user ? \App\Rules\TurkishPhone::format($user->phone) : null),
        'district_id' => old('district_id'),
        'neighborhood' => old('neighborhood'),
        'address' => old('address'),
    ];
    if (! old('district_id') && ($default = $addresses->firstWhere('is_default', true) ?? $addresses->first())) {
        $initial = ['name' => $default->name, 'phone' => \App\Rules\TurkishPhone::format($default->phone), 'district_id' => $default->district_id,
            'neighborhood' => $default->neighborhood, 'address' => $default->address];
    }
    $payload = [
        'districts' => $districtData,
        'subtotal' => $subtotal,
        'closed' => ! $ordersOpen || $districts->isEmpty(),
        'initial' => $initial,
        'addresses' => $addresses->mapWithKeys(fn ($a) => [$a->id => ['name' => $a->name, 'phone' => \App\Rules\TurkishPhone::format($a->phone),
            'district_id' => $a->district_id, 'neighborhood' => $a->neighborhood, 'address' => $a->address]]),
    ];
@endphp
<x-layouts.store title="Siparişi tamamla" :noindex="true">
    <div class="container-page py-6 sm:py-10" x-data="checkout">
        <script type="application/json" data-checkout>@json($payload)</script>
        <x-breadcrumb :items="[['Sepetim', route('cart.index')], ['Siparişi tamamla', null]]" />
        <h1 class="mt-4 text-2xl font-extrabold sm:text-3xl">Siparişi tamamla</h1>

        @unless ($ordersOpen)
            <div class="mt-5 flex items-start gap-3 rounded-xl border border-accent/30 bg-accent-soft px-4 py-3 text-sm text-accent-ink" role="alert">
                <x-icon name="alert" class="mt-0.5 size-4 shrink-0" /><p class="font-medium">{{ $settings['orders_closed_message'] }}</p>
            </div>
        @endunless
        @if ($errors->any())
            <div class="mt-5 flex items-start gap-3 rounded-xl border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger" role="alert">
                <x-icon name="alert" class="mt-0.5 size-4 shrink-0" />
                <div class="font-medium">@if ($errors->has('cart')){{ $errors->first('cart') }}@else Lütfen işaretli alanları kontrol edin.@endif</div>
            </div>
        @endif

        <form method="post" action="{{ route('checkout.store') }}" class="mt-6 grid items-start gap-6 lg:grid-cols-[1fr_380px]">
            @csrf
            <div class="space-y-6">
                @guest('web')
                    <p class="rounded-xl bg-white px-4 py-3 text-sm text-muted shadow-card">
                        Üye olmadan sipariş verebilirsiniz. Hesabınız varsa <a href="{{ route('login') }}" class="font-semibold text-primary hover:underline">giriş yapın</a>; adresleriniz kayıtlı kalsın.
                    </p>
                @endguest

                <section class="card p-5 sm:p-7">
                    <h2 class="flex items-center gap-2 text-lg font-bold"><span class="flex size-7 items-center justify-center rounded-full bg-primary text-sm text-white">1</span> İletişim bilgileri</h2>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <x-field name="name" label="Ad soyad" required autocomplete="name" x-model="name" />
                        <x-field name="phone" type="tel" label="Cep telefonu" required autocomplete="tel" inputmode="tel" placeholder="05xx xxx xx xx" x-model="phone" />
                        <x-field class="sm:col-span-2" name="email" type="email" label="E-posta" :value="$user?->email" :required="! $user" autocomplete="email"
                                 help="Sipariş durumunu (alındı, hazırlanıyor, yolda, teslim edildi) bu adrese bildiririz." />
                    </div>
                </section>

                <section class="card p-5 sm:p-7">
                    <h2 class="flex items-center gap-2 text-lg font-bold"><span class="flex size-7 items-center justify-center rounded-full bg-primary text-sm text-white">2</span> Teslimat adresi</h2>
                    <p class="mt-1 text-sm text-muted">{{ $settings['delivery_info'] }}</p>

                    @if ($addresses->isNotEmpty())
                        <div class="mt-5 grid gap-2 sm:grid-cols-2">
                            @foreach ($addresses as $a)
                                <button type="button" x-on:click="useAddress" data-address="{{ $a->id }}" class="rounded-xl border border-line p-3 text-left text-sm hover:border-primary hover:bg-primary-soft">
                                    <span class="font-semibold">{{ $a->title }}</span>
                                    <span class="mt-0.5 line-clamp-2 block text-xs text-muted">{{ $a->oneLine() }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="f_district_id" class="field-label">İlçe<span class="text-danger"> *</span></label>
                            <select id="f_district_id" name="district_id" required class="field-input" x-model="districtId" x-on:change="pickDistrict" aria-invalid="{{ $errors->has('district_id') ? 'true' : 'false' }}">
                                <option value="">Seçin</option>
                                @foreach ($districts as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                            </select>
                            @error('district_id')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="f_neighborhood" class="field-label">Mahalle<span class="text-danger"> *</span></label>
                            <select id="f_neighborhood" name="neighborhood" class="field-input" x-ref="hood" x-show="hasHoods" x-bind:disabled="noHoods" x-model="neighborhood" x-on:change="refresh">
                                <option value="">Seçin</option>
                                <template x-for="h in hoods" x-bind:key="h.name"><option x-bind:value="h.name" x-text="h.name"></option></template>
                            </select>
                            <input name="neighborhood" class="field-input" x-show="noHoods" x-bind:disabled="hasHoods" x-model="neighborhood" maxlength="120" required placeholder="Mahalle adı">
                            @error('neighborhood')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <x-field class="sm:col-span-2" name="address" type="textarea" rows="3" label="Açık adres" required x-model="address"
                                 placeholder="Cadde/sokak, bina no, daire no, tarif" autocomplete="street-address" />
                    </div>
                    @if ($districts->isEmpty())
                        <p class="mt-4 text-sm text-danger">Şu anda teslimat yapılan bölge tanımlı değil.</p>
                    @endif
                    @auth('web')
                        <label class="mt-4 flex items-center gap-2 text-sm"><input type="checkbox" name="save_address" value="1" class="accent-primary" @checked(old('save_address', $addresses->isEmpty()))> Bu adresi hesabıma kaydet</label>
                    @endauth
                </section>

                <section class="card p-5 sm:p-7">
                    <h2 class="flex items-center gap-2 text-lg font-bold"><span class="flex size-7 items-center justify-center rounded-full bg-primary text-sm text-white">3</span> Teslimat ve ödeme</h2>
                    @if ($slots)
                        <fieldset class="mt-5">
                            <legend class="field-label">Teslimat saati<span class="text-danger"> *</span></legend>
                            <div class="grid gap-2 sm:grid-cols-3">
                                @foreach ($slots as $slot)
                                    <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-line px-3 py-2.5 text-sm has-checked:border-primary has-checked:bg-primary-soft">
                                        <input type="radio" name="slot" value="{{ $slot }}" required class="accent-primary" @checked(old('slot', $slots[0]) === $slot)> <x-icon name="clock" class="size-4 text-muted" />{{ $slot }}
                                    </label>
                                @endforeach
                            </div>
                            @error('slot')<p class="field-error">{{ $message }}</p>@enderror
                        </fieldset>
                    @endif
                    <fieldset class="mt-5">
                        <legend class="field-label">Kapıda ödeme<span class="text-danger"> *</span></legend>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($payments as $key => $label)
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line p-3.5 text-sm font-semibold has-checked:border-primary has-checked:bg-primary-soft">
                                    <input type="radio" name="payment_method" value="{{ $key }}" required class="accent-primary" @checked(old('payment_method', array_key_first($payments)) === $key)>
                                    <x-icon name="{{ $key === 'card' ? 'card' : 'cash' }}" class="size-5 text-primary" />{{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @error('payment_method')<p class="field-error">{{ $message }}</p>@enderror
                    </fieldset>
                    <x-field class="mt-5" name="note" type="textarea" rows="2" label="Sipariş notu" help="İsteğe bağlı: zil çalışmıyor, kapıcıya bırakın vb." />
                </section>
            </div>

            <aside class="card space-y-4 p-5 sm:p-6 lg:sticky lg:top-24">
                <h2 class="text-lg font-bold">Sipariş özeti</h2>
                <ul class="max-h-72 space-y-3 overflow-y-auto pr-1">
                    @foreach ($lines as $line)
                        <li class="flex gap-3 text-sm">
                            <span class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-surface">
                                @if ($line->image)<img src="{{ $line->image }}" alt="" class="max-h-full max-w-full object-contain">@else<x-icon name="book" class="size-5 text-slate-300" />@endif
                            </span>
                            <span class="min-w-0 flex-1"><span class="line-clamp-2 font-medium">{{ $line->title }}</span>
                                <span class="text-xs text-muted">{{ $line->quantity }} × {{ Money::format($line->unitPrice) }}{{ $line->printTypeLabel() ? ' · '.$line->printTypeLabel() : '' }}</span></span>
                            <span class="font-semibold whitespace-nowrap">{{ Money::format($line->total()) }}</span>
                        </li>
                    @endforeach
                </ul>
                <dl class="space-y-2 border-t border-line pt-4 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">Ara toplam</dt><dd class="font-semibold">{{ Money::format($subtotal) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Teslimat</dt><dd class="font-semibold" x-text="feeText">—</dd></div>
                    <div class="flex justify-between border-t border-line pt-2 text-base"><dt class="font-bold">Toplam</dt><dd class="font-display text-xl font-extrabold" x-text="totalText">{{ Money::format($subtotal) }}</dd></div>
                </dl>
                <p x-show="hasDistrict" x-cloak class="flex items-center gap-2 rounded-xl bg-success-soft px-3 py-2 text-sm text-success"><x-icon name="truck" class="size-4" />Tahmini teslimat: <strong x-text="etaText"></strong></p>
                <p x-show="freeText" x-cloak class="rounded-xl bg-primary-soft px-3 py-2 text-sm text-primary-dark" x-text="freeText"></p>
                <p x-show="belowMin" x-cloak class="rounded-xl bg-accent-soft px-3 py-2 text-sm text-accent-ink" x-text="minText"></p>
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="terms" value="1" required class="mt-0.5 accent-primary" @checked(old('terms'))>
                    <span>{{ $settings['checkout_consent'] ?: \App\Support\Settings::DEFAULTS['checkout_consent'] }}</span>
                </label>
                <x-page-links :keys="['sales', 'kvkk', 'delivery']" class="-mt-2 pl-6" />
                @error('terms')<p class="field-error">{{ $message }}</p>@enderror
                <button class="btn-primary w-full py-3 text-base" @disabled(! $ordersOpen || $districts->isEmpty()) x-bind:disabled="blocked">Siparişi onayla</button>
                <p class="text-center text-xs text-muted">Ödeme teslimatta yapılır; şimdi kart bilgisi istenmez.</p>
            </aside>
        </form>
    </div>
</x-layouts.store>
