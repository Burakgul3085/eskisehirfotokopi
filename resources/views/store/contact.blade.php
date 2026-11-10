@php
    use App\Rules\TurkishPhone;
    $hours = array_filter(preg_split('/\r?\n/', (string) $settings['working_hours']));
@endphp
<x-layouts.store title="İletişim" :description="$settings['contact_intro']">
    <div class="container-page py-8 sm:py-12">
        <x-breadcrumb :items="[['İletişim', null]]" />
        <h1 class="mt-4 text-3xl font-extrabold">İletişim</h1>
        @if ($settings['contact_intro'])<p class="mt-2 max-w-2xl text-muted">{{ $settings['contact_intro'] }}</p>@endif

        <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_22rem]">
            <form method="post" action="{{ route('contact.store') }}" class="card space-y-5 p-6 sm:p-8">
                @csrf
                <x-flash />
                {{-- Bot tuzağı: gerçek kullanıcı görmez --}}
                <div class="hidden" aria-hidden="true"><label>Web sitesi <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field name="name" label="Adınız soyadınız" required :value="$user?->name" autocomplete="name" />
                    <x-field name="email" type="email" label="E-posta" required :value="$user?->email" autocomplete="email" help="Yanıtımız bu adrese gelir." />
                    <x-field name="phone" type="tel" label="Telefon" :value="$user?->phone ? TurkishPhone::format($user->phone) : null" inputmode="tel" placeholder="05xx xxx xx xx" />
                    <x-field name="order_number" label="Sipariş numarası" placeholder="Varsa" autocomplete="off" />
                </div>
                <x-field name="subject" label="Konu" required maxlength="160" />
                <x-field name="body" type="textarea" rows="6" label="Mesajınız" required maxlength="5000" />
                <label class="flex items-start gap-3 text-sm">
                    <input type="checkbox" name="kvkk" value="1" class="mt-0.5 size-4 rounded border-line text-primary" @checked(old('kvkk'))>
                    <span>{{ $settings['contact_consent'] ?: \App\Support\Settings::DEFAULTS['contact_consent'] }}</span>
                </label>
                <x-page-links :keys="['kvkk']" class="-mt-3 pl-7" />
                @error('kvkk')<p class="field-error">{{ $message }}</p>@enderror
                <div class="flex justify-end"><button class="btn-primary px-6 py-3"><x-icon name="mail" class="size-4" />Gönder</button></div>
            </form>

            <aside class="space-y-4">
                @if ($settings['phone'] || $settings['whatsapp'] || $settings['email'] || $settings['address'] || $hours)
                <div class="card space-y-4 p-6">
                    @if ($settings['phone'])
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $settings['phone']) }}" class="flex items-center gap-3 hover:text-primary">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary"><x-icon name="phone" class="size-5" /></span>
                            <span><span class="block text-xs text-muted">Telefon</span><span class="font-semibold">{{ $settings['phone'] }}</span></span>
                        </a>
                    @endif
                    @if ($wa = \App\Support\Settings::whatsappUrl())
                        <a href="{{ $wa }}" target="_blank" rel="noopener" class="flex items-center gap-3 hover:text-primary">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-success-soft text-success"><x-icon name="whatsapp" class="size-5" /></span>
                            <span><span class="block text-xs text-muted">WhatsApp</span><span class="font-semibold">Mesaj yazın</span></span>
                        </a>
                    @endif
                    @if ($settings['email'])
                        <a href="mailto:{{ $settings['email'] }}" class="flex items-center gap-3 hover:text-primary">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary"><x-icon name="mail" class="size-5" /></span>
                            <span class="min-w-0"><span class="block text-xs text-muted">E-posta</span><span class="block truncate font-semibold">{{ $settings['email'] }}</span></span>
                        </a>
                    @endif
                    @if ($settings['address'])
                        <div class="flex items-start gap-3">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary"><x-icon name="pin" class="size-5" /></span>
                            <span><span class="block text-xs text-muted">Adres</span><span class="font-semibold">{{ $settings['address'] }}</span>
                                @if ($settings['maps_url'])<a href="{{ $settings['maps_url'] }}" target="_blank" rel="noopener" class="mt-1 block text-sm font-semibold text-primary hover:underline">Haritada aç</a>@endif</span>
                        </div>
                    @endif
                    @if ($hours)
                        <div class="flex items-start gap-3">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary"><x-icon name="clock" class="size-5" /></span>
                            <span><span class="block text-xs text-muted">Çalışma saatleri</span>
                                @foreach ($hours as $h)<span class="block font-semibold">{{ $h }}</span>@endforeach</span>
                        </div>
                    @endif
                </div>
                @endif
                <a href="{{ route('orders.track') }}" class="card flex items-center gap-3 p-5 hover:text-primary">
                    <x-icon name="truck" class="size-5 text-primary" /><span class="font-semibold">Siparişimi takip et</span><x-icon name="chevron-right" class="ml-auto size-4" />
                </a>
            </aside>
        </div>
    </div>
</x-layouts.store>
