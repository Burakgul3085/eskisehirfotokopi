@php
    use App\Rules\TurkishPhone;
    use App\Support\MapEmbed;
    use App\Support\Settings;

    $hours = array_filter(preg_split('/\r?\n/', (string) $settings['working_hours']));
    $wa = Settings::whatsappUrl();
    $tel = $settings['phone'] ? 'tel:'.preg_replace('/[^0-9+]/', '', $settings['phone']) : null;
    $map = MapEmbed::url();
    $social = Settings::socialLinks();
    // Koyu üst alandaki hızlı iletişim kartları (yalnız tanımlı olanlar)
    $quick = array_values(array_filter([
        $tel ? ['icon' => 'phone', 'label' => 'Telefon', 'value' => $settings['phone'], 'url' => $tel, 'blank' => false] : null,
        $wa ? ['icon' => 'whatsapp', 'label' => 'WhatsApp', 'value' => 'Mesaj yazın', 'url' => $wa, 'blank' => true] : null,
        $settings['email'] ? ['icon' => 'mail', 'label' => 'E-posta', 'value' => $settings['email'], 'url' => 'mailto:'.$settings['email'], 'blank' => false] : null,
    ]));
@endphp
<x-layouts.store title="İletişim" :description="$settings['contact_intro']" :flush="true">
    {{--
        Koyu üst alan: solda başlık ve hızlı iletişim kartları, sağda 3D zarf sahnesi (resources/js/scenes/contact.js).
        Altta tonlu zeminde form ve iletişim bilgileri, en altta gömülü harita (Ayarlar › İletişim › Harita).
        Bütün yazılar Yönetim › Ayarlar › İletişim'den.
    --}}
    <section class="hero-3d surface-deep relative isolate overflow-hidden text-white">
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
            <div class="hero-grid absolute inset-0"></div>
            <div class="hero-spot absolute inset-0"></div>
        </div>
        <div class="container-page relative grid items-center gap-6 pt-8 pb-28 lg:grid-cols-[1fr_1.1fr] lg:pt-12 lg:pb-36">
            <div class="relative z-10">
                <x-breadcrumb :items="[['İletişim', null]]" class="hero-breadcrumb" />
                @if (Settings::get('contact_kicker'))
                    <p class="hero-reveal mt-6 flex items-center gap-3 text-xs font-bold tracking-[0.25em] text-accent uppercase" style="--d: 0">
                        <span class="relative flex size-2.5"><span class="absolute inline-flex size-full animate-ping rounded-full bg-accent opacity-60 motion-reduce:hidden"></span><span class="relative inline-flex size-2.5 rounded-full bg-accent"></span></span>
                        {{ Settings::get('contact_kicker') }}
                    </p>
                @endif
                <h1 class="hero-reveal mt-4 text-4xl leading-[1.05] font-extrabold tracking-tight sm:text-5xl lg:text-6xl" style="--d: 1">
                    <span class="block bg-gradient-to-br from-white via-white to-white/70 bg-clip-text text-transparent">{{ Settings::get('contact_title', 'İletişim') }}</span>
                    @if (Settings::get('contact_title_accent'))<span class="block text-accent">{{ Settings::get('contact_title_accent') }}</span>@endif
                </h1>
                @if ($settings['contact_intro'])<p class="hero-reveal mt-5 max-w-lg text-base leading-relaxed text-white/75 sm:text-lg" style="--d: 2">{{ $settings['contact_intro'] }}</p>@endif

                @if ($quick)
                    <ul class="hero-reveal mt-8 grid gap-3 sm:grid-cols-{{ min(3, count($quick)) }}" style="--d: 3">
                        @foreach ($quick as $i => $q)
                            <li class="hero-card" style="--depth: {{ 6 + $i * 4 }}">
                                <a href="{{ $q['url'] }}" @if ($q['blank']) target="_blank" rel="noopener" @endif
                                   class="group flex h-full items-center gap-3 rounded-2xl border border-white/12 bg-white/[0.07] p-3.5 backdrop-blur-md transition hover:border-accent/50 hover:bg-white/[0.12]">
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-accent to-accent/70 text-ink shadow-[0_8px_20px_-6px_var(--color-accent)] transition group-hover:scale-110 group-hover:-rotate-6"><x-icon :name="$q['icon']" class="size-5" /></span>
                                    <span class="min-w-0">
                                        <span class="block text-[11px] font-semibold tracking-wide text-white/60 uppercase">{{ $q['label'] }}</span>
                                        <span class="block truncate text-sm font-bold">{{ $q['value'] }}</span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="relative">
                <div data-scene="contact" class="relative h-[280px] sm:h-[360px] lg:h-[460px]">
                    <div class="hero-fallback pointer-events-none absolute inset-0 flex items-center justify-center transition-opacity duration-1000" aria-hidden="true">
                        <div class="size-56 rounded-full bg-gradient-to-br from-white/25 to-accent/20 blur-2xl md:size-72"></div>
                    </div>
                    <div class="hero-stage absolute inset-0">
                        <canvas data-scene-canvas class="block size-full" aria-hidden="true"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="page-tint relative pb-16">
        <div class="container-page relative -mt-20 lg:-mt-24">
            <div class="grid gap-6 lg:grid-cols-[1fr_24rem]">
                {{-- Form --}}
                <form method="post" action="{{ route('contact.store') }}" class="contact-form relative overflow-hidden rounded-[2rem] border border-white bg-white p-6 shadow-[0_40px_80px_-40px_rgba(15,23,42,0.45)] sm:p-9">
                    <span class="pointer-events-none absolute inset-x-10 top-0 h-[3px] rounded-full bg-gradient-to-r from-primary via-accent to-primary" aria-hidden="true"></span>
                    @csrf
                    <div class="mb-6 flex items-center gap-3">
                        <span class="track-card-icon flex size-12 items-center justify-center rounded-2xl text-white"><x-icon name="chat" class="size-5" /></span>
                        <h2 class="font-display text-xl font-extrabold sm:text-2xl">{{ Settings::get('contact_form_title', 'Mesaj gönderin') }}</h2>
                    </div>
                    <div class="space-y-5">
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
                        <label class="flex items-start gap-3 rounded-2xl bg-surface p-4 text-sm">
                            <input type="checkbox" name="kvkk" value="1" class="mt-0.5 size-4 rounded border-line text-primary" @checked(old('kvkk'))>
                            <span>{{ $settings['contact_consent'] ?: Settings::DEFAULTS['contact_consent'] }}</span>
                        </label>
                        <x-page-links :keys="['kvkk']" class="-mt-3 pl-4" />
                        @error('kvkk')<p class="field-error">{{ $message }}</p>@enderror
                        <div class="flex justify-end">
                            <button class="btn-primary group px-7 py-3.5 text-base shadow-[0_18px_40px_-16px_var(--color-primary)]">
                                <x-icon name="mail" class="size-5" />Gönder
                                <x-icon name="arrow-right" class="size-4 transition group-hover:translate-x-1" />
                            </button>
                        </div>
                    </div>
                </form>

                {{-- İletişim bilgileri --}}
                <aside class="space-y-4">
                    @if ($settings['phone'] || $wa || $settings['email'] || $settings['address'] || $hours)
                        <div class="relative overflow-hidden rounded-[2rem] border border-white bg-white p-6 shadow-[0_40px_80px_-40px_rgba(15,23,42,0.45)] sm:p-7">
                            <h2 class="mb-5 font-display text-lg font-extrabold">{{ Settings::get('contact_info_title', 'İletişim bilgileri') }}</h2>
                            <div class="space-y-2">
                                @if ($tel)
                                    <a href="{{ $tel }}" class="contact-row group flex items-center gap-3.5 rounded-2xl p-2.5">
                                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-primary-dark text-white shadow-md transition group-hover:scale-105"><x-icon name="phone" class="size-5" /></span>
                                        <span class="min-w-0 flex-1"><span class="block text-xs text-muted">Telefon</span><span class="block font-bold">{{ $settings['phone'] }}</span></span>
                                        <x-icon name="arrow-right" class="size-4 text-muted transition group-hover:translate-x-1 group-hover:text-primary" />
                                    </a>
                                @endif
                                @if ($wa)
                                    <a href="{{ $wa }}" target="_blank" rel="noopener" class="contact-row group flex items-center gap-3.5 rounded-2xl p-2.5">
                                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[#25d366] text-white shadow-md transition group-hover:scale-105"><x-icon name="whatsapp" class="size-5" /></span>
                                        <span class="min-w-0 flex-1"><span class="block text-xs text-muted">WhatsApp</span><span class="block font-bold">Mesaj yazın</span></span>
                                        <x-icon name="arrow-right" class="size-4 text-muted transition group-hover:translate-x-1 group-hover:text-primary" />
                                    </a>
                                @endif
                                @if ($settings['email'])
                                    <a href="mailto:{{ $settings['email'] }}" class="contact-row group flex items-center gap-3.5 rounded-2xl p-2.5">
                                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-sky-500 to-blue-700 text-white shadow-md transition group-hover:scale-105"><x-icon name="mail" class="size-5" /></span>
                                        <span class="min-w-0 flex-1"><span class="block text-xs text-muted">E-posta</span><span class="block truncate font-bold">{{ $settings['email'] }}</span></span>
                                        <x-icon name="arrow-right" class="size-4 text-muted transition group-hover:translate-x-1 group-hover:text-primary" />
                                    </a>
                                @endif
                                @if ($settings['address'])
                                    <div class="flex items-start gap-3.5 p-2.5">
                                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-accent to-amber-700 text-white shadow-md"><x-icon name="pin" class="size-5" /></span>
                                        <span class="min-w-0"><span class="block text-xs text-muted">Adres</span><span class="block font-bold leading-snug">{{ $settings['address'] }}</span>
                                            @if ($settings['maps_url'])<a href="{{ $settings['maps_url'] }}" target="_blank" rel="noopener" class="mt-1.5 inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline">Haritada aç<x-icon name="external" class="size-3.5" /></a>@endif</span>
                                    </div>
                                @endif
                                @if ($hours)
                                    <div class="flex items-start gap-3.5 p-2.5">
                                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-purple-800 text-white shadow-md"><x-icon name="clock" class="size-5" /></span>
                                        <span><span class="block text-xs text-muted">Çalışma saatleri</span>
                                            @foreach ($hours as $h)<span class="block font-bold">{{ $h }}</span>@endforeach</span>
                                    </div>
                                @endif
                            </div>
                            @if ($social)
                                <x-social-links :links="$social" class="mt-5 border-t border-dashed border-line pt-5" />
                            @endif
                        </div>
                    @endif
                    <a href="{{ route('orders.track') }}" class="surface-deep group relative flex items-center gap-3.5 overflow-hidden rounded-[1.5rem] p-5 text-white shadow-[0_30px_60px_-34px_rgba(2,6,23,0.8)]">
                        <span class="flex size-11 items-center justify-center rounded-xl bg-accent text-ink transition group-hover:scale-110"><x-icon name="truck" class="size-5" /></span>
                        <span class="font-display font-bold">Siparişimi takip et</span>
                        <x-icon name="arrow-right" class="ml-auto size-5 transition group-hover:translate-x-1" />
                    </a>
                </aside>
            </div>

            {{-- Harita: Ayarlar › İletişim › Harita bağlantısından (ya da adresten) --}}
            @if ($map)
                <section class="reveal mt-10">
                    <x-section-head :title="Settings::get('contact_map_title', 'Konum')" :subtitle="$settings['address']">
                        @if ($settings['maps_url'])
                            <a href="{{ $settings['maps_url'] }}" target="_blank" rel="noopener" class="group/all inline-flex items-center gap-1.5 rounded-full bg-primary-soft px-3.5 py-1.5 text-sm font-semibold text-primary-dark transition hover:bg-primary hover:text-white">Haritada aç<x-icon name="external" class="size-4" /></a>
                        @endif
                    </x-section-head>
                    <div class="contact-map relative overflow-hidden rounded-[2rem] border-4 border-white bg-white shadow-[0_40px_80px_-40px_rgba(15,23,42,0.5)]">
                        <iframe src="{{ $map }}" class="block h-[340px] w-full sm:h-[440px]" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen title="{{ Settings::get('contact_map_title', 'Konum') }}"></iframe>
                        @if ($settings['address'])
                            <div class="pointer-events-none absolute bottom-6 left-4 hidden max-w-xs items-start gap-3 rounded-2xl bg-white/95 p-4 shadow-lift backdrop-blur sm:flex">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-accent to-amber-700 text-white"><x-icon name="pin" class="size-5" /></span>
                                <span><span class="block font-display text-sm font-extrabold">{{ $settings['site_name'] }}</span><span class="mt-0.5 block text-xs leading-snug text-muted">{{ $settings['address'] }}</span></span>
                            </div>
                        @endif
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-layouts.store>
