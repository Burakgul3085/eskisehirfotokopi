@php
    use App\Models\Page;
    use App\Support\MapEmbed;
    use App\Support\Settings;

    $social = Settings::socialLinks();
    $whatsapp = Settings::whatsappUrl();
    $footerLogo = Settings::fileUrl('logo_path');
    $pageColumns = Page::links()['columns'];
    $toLinks = fn (array $pages) => array_map(fn ($l) => [$l['title'], $l['url']], $pages);

    // Dört sütun: Kurumsal ve Yardım bilgi sayfalarından (Sayfalar › alt bilgi sütunu), Üyelik ve Alışveriş sabit site bölümleri
    $linkColumns = array_values(array_filter([
        ['title' => $settings['footer_title_corporate'], 'links' => $toLinks($pageColumns['corporate'] ?? [])],
        ['title' => $settings['footer_title_account'], 'links' => auth('web')->check()
            ? [['Hesabım', route('account.index')], ['Siparişlerim', route('account.orders')], ['Favorilerim', route('account.favorites')], ['Adreslerim', route('account.addresses.index')]]
            : [['Üye ol', route('register')], ['Giriş yap', route('login')], ['Şifremi unuttum', route('password.request')], ['Sipariş takibi', route('orders.track')]]],
        ['title' => $settings['footer_title_help'], 'links' => array_merge($toLinks($pageColumns['help'] ?? []), [
            ['Sıkça sorulan sorular', route('home').'#sss'], ['Sipariş takibi', route('orders.track')], ['İletişim', route('contact')],
        ])],
        ['title' => $settings['footer_title_shop'], 'links' => [
            ['Tüm kitaplar', route('products.index')], ['Yayınevleri', route('publishers.index')], ['Fotokopi ve baskı', route('print.index')], ['Sepetim', route('cart.index')],
        ]],
    ], fn ($c) => $c['title'] && $c['links']));
    // Aynı adlı bağlantı iki kez görünmesin (ör. "Sıkça Sorulan Sorular" bilgi sayfası varsa sabit bağlantı eklenmez)
    $linkColumns = array_map(function ($c) {
        $seen = [];
        $c['links'] = array_values(array_filter($c['links'], function ($l) use (&$seen) {
            $k = \App\Support\Text::key($l[0]);
            return ! isset($seen[$k]) && ($seen[$k] = true);
        }));

        return $c;
    }, $linkColumns);

    $map = MapEmbed::url();
    $hours = Settings::workingHours();
    $newsletter = Settings::flag('newsletter_enabled');
    $legal = [];
@endphp
{{-- Alt bilgi: arkada dalgalanan 3D ışık noktaları (resources/js/scenes/footer-wave.js), güven kartları; marka ve iletişim,
     dört bağlantı sütunu, e-bülten formu (#bulten), mağaza haritası ve çalışma saatleri. Tüm metinler Ayarlar › Genel'den. --}}
<footer data-scene="footer-wave" class="footer-3d surface-deep relative isolate {{ ($flush ?? false) ? '' : 'mt-16' }} overflow-hidden text-slate-300">
    <canvas data-scene-canvas class="scene-canvas footer-wave pointer-events-none absolute inset-0 -z-10 block size-full" aria-hidden="true"></canvas>
    <div class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-px bg-gradient-to-r from-transparent via-primary/60 to-transparent" aria-hidden="true"></div>
    @if ($trust = \App\Support\Settings::trustItems())
        <div class="border-b border-white/10">
            <div class="container-page grid gap-4 py-8 sm:grid-cols-2 {{ [1 => 'lg:grid-cols-1', 2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3'][count($trust)] ?? 'lg:grid-cols-4' }}">
                @foreach ($trust as $item)
                    <div class="trust-tile group flex items-center gap-4 rounded-2xl border border-white/[0.07] bg-white/[0.03] p-4 backdrop-blur-sm">
                        <span class="trust-icon relative flex size-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-white/10 to-white/[0.02] text-accent ring-1 ring-white/10">
                            <x-icon :name="$item['icon']" class="size-6" />
                        </span>
                        <div>
                            <p class="font-display font-semibold text-white">{{ $item['title'] }}</p>
                            @if ($item['text'])<p class="text-sm text-slate-400">{{ $item['text'] }}</p>@endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="container-page grid gap-x-10 gap-y-12 py-14 lg:grid-cols-[1.1fr_2.5fr] xl:grid-cols-[1.05fr_2.4fr_1.15fr]">
        {{-- Marka, tanıtım, iletişim, sosyal medya --}}
        <div class="min-w-0">
            <a href="{{ route('home') }}" class="header-brand group/brand inline-flex items-center gap-3" aria-label="{{ $settings['site_name'] }} ana sayfa">
                <x-brand :logo="$footerLogo" :name="$settings['site_name']" dark />
            </a>
            @if ($settings['footer_about'])
                <p class="mt-5 max-w-md text-sm leading-relaxed text-slate-400">{{ $settings['footer_about'] }}</p>
            @endif
            <ul class="mt-6 space-y-3 text-sm">
                @if ($settings['phone'])
                    <li class="flex gap-3"><x-icon name="phone" class="size-4 shrink-0 translate-y-0.5 text-accent" /><a href="tel:{{ preg_replace('/[^0-9+]/', '', $settings['phone']) }}" class="font-semibold text-white hover:text-accent">{{ $settings['phone'] }}</a></li>
                @endif
                @if ($whatsapp)
                    <li class="flex gap-3"><x-icon name="whatsapp" class="size-4 shrink-0 translate-y-0.5 text-accent" /><a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="hover:text-white">WhatsApp</a></li>
                @endif
                @if ($settings['email'])
                    <li class="flex gap-3"><x-icon name="mail" class="size-4 shrink-0 translate-y-0.5 text-accent" /><a href="mailto:{{ $settings['email'] }}" class="break-all hover:text-white">{{ $settings['email'] }}</a></li>
                @endif
                @if ($settings['address'])
                    <li class="flex gap-3"><x-icon name="pin" class="size-4 shrink-0 translate-y-0.5 text-accent" /><span class="leading-relaxed">{{ $settings['address'] }}</span></li>
                @endif
            </ul>
            <x-social-links :links="$social" class="mt-6" />
        </div>

        {{-- Bağlantı sütunları ve e-bülten --}}
        <div class="min-w-0">
            <div class="grid grid-cols-2 gap-x-6 gap-y-10 md:grid-cols-4">
                @foreach ($linkColumns as $column)
                    <div class="min-w-0">
                        <p class="footer-heading">{{ $column['title'] }}</p>
                        <ul class="mt-5 space-y-3 text-sm">
                            @foreach ($column['links'] as [$label, $url])
                                <li><a href="{{ $url }}" class="footer-link">{{ $label }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>

            @if ($newsletter)
                <section id="bulten" class="footer-newsletter mt-12 scroll-mt-32 rounded-3xl p-6 sm:p-7">
                    <div class="grid items-center gap-6 lg:grid-cols-[1fr_1.15fr]">
                        <div class="flex gap-4">
                            <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-accent text-ink shadow-[0_12px_30px_-12px_var(--color-accent)]"><x-icon name="mail" class="size-6" /></span>
                            <div>
                                @if ($settings['newsletter_kicker'])<p class="text-xs font-bold tracking-[0.28em] text-accent uppercase">{{ $settings['newsletter_kicker'] }}</p>@endif
                                <h2 class="mt-1 font-display text-lg leading-snug font-extrabold text-white">{{ $settings['newsletter_title'] }}</h2>
                                @if ($settings['newsletter_text'])<p class="mt-1.5 text-sm text-slate-400">{{ $settings['newsletter_text'] }}</p>@endif
                            </div>
                        </div>

                        @if (session('newsletter_status'))
                            <p class="flex items-center gap-3 rounded-2xl bg-success/15 px-4 py-4 text-sm font-semibold text-emerald-300" role="status">
                                <x-icon name="check" class="size-5 shrink-0" />{{ session('newsletter_status') }}</p>
                        @else
                            <form method="post" action="{{ route('newsletter.subscribe') }}" class="min-w-0" novalidate>
                                @csrf
                                {{-- Bot tuzağı: insanlar görmez --}}
                                <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
                                <div class="footer-newsletter-field flex gap-2 rounded-2xl bg-white p-1.5">
                                    <label for="bulten-eposta" class="sr-only">{{ $settings['newsletter_placeholder'] }}</label>
                                    <input id="bulten-eposta" type="email" name="email" value="{{ old('email') && $errors->newsletter->any() ? old('email') : '' }}" required autocomplete="email"
                                           placeholder="{{ $settings['newsletter_placeholder'] }}" class="min-w-0 flex-1 rounded-xl border-0 bg-transparent px-3 text-sm text-ink placeholder:text-slate-400 focus:ring-0 focus:outline-none">
                                    <button class="btn-accent shrink-0 rounded-xl px-5 py-2.5 text-sm">{{ $settings['newsletter_button'] }}</button>
                                </div>
                                <label class="mt-3 flex items-start gap-2.5 text-xs leading-relaxed text-slate-400">
                                    <input type="checkbox" name="consent" value="1" required @checked(old('consent') && $errors->newsletter->any()) class="mt-0.5 size-4 shrink-0 rounded border-white/30 bg-white/10 text-accent focus:ring-accent">
                                    <span>{{ $settings['newsletter_consent'] }}
                                        @if ($kvkk = \App\Models\Page::linkFor('kvkk'))<a href="{{ $kvkk['url'] }}" class="font-semibold text-slate-200 underline decoration-white/30 underline-offset-2 hover:text-white">{{ $kvkk['title'] }}</a>@endif</span>
                                </label>
                                @if ($errors->newsletter->any())
                                    <p class="mt-2 text-xs font-semibold text-rose-300" role="alert">{{ $errors->newsletter->first() }}</p>
                                @endif
                            </form>
                        @endif
                    </div>
                </section>
            @endif
        </div>

        {{-- Mağaza konumu ve çalışma saatleri --}}
        @if ($map || $hours)
            <div class="grid min-w-0 content-start gap-5 sm:grid-cols-2 lg:col-span-2 xl:col-span-1 xl:grid-cols-1">
                @if ($map)
                    <div class="min-w-0">
                        @if ($settings['footer_map_title'])<p class="footer-heading">{{ $settings['footer_map_title'] }}</p>@endif
                        <div class="footer-card mt-5 overflow-hidden">
                            <iframe src="{{ $map }}" class="block h-44 w-full bg-slate-800" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="{{ $settings['footer_map_title'] ?: $settings['site_name'] }}"></iframe>
                            <div class="flex items-center gap-3 px-4 py-3">
                                <x-icon name="pin" class="size-4 shrink-0 text-accent" />
                                <span class="min-w-0 flex-1 truncate text-xs font-semibold tracking-wide text-white uppercase" title="{{ $settings['address'] }}">{{ $settings['address'] ?: $settings['site_name'] }}</span>
                                @if ($settings['maps_url'] && $settings['footer_directions'])
                                    <a href="{{ $settings['maps_url'] }}" target="_blank" rel="noopener" class="inline-flex shrink-0 items-center gap-1 text-sm font-bold text-accent hover:text-white">{{ $settings['footer_directions'] }}<x-icon name="external" class="size-3.5" /></a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
                @if ($hours)
                    <div class="footer-card min-w-0 p-5 {{ $map ? 'sm:mt-11 xl:mt-0' : '' }}">
                        <p class="flex items-center gap-2.5 text-sm font-bold tracking-wide text-white uppercase"><x-icon name="clock" class="size-4 text-accent" />{{ $settings['footer_hours_title'] }}</p>
                        <dl class="mt-4 space-y-2.5 text-sm">
                            @foreach ($hours as $row)
                                <div class="flex items-baseline justify-between gap-4">
                                    <dt class="text-slate-400">{{ $row['day'] }}</dt>
                                    @if ($row['time'] !== '')<dd class="shrink-0 font-bold text-white tabular-nums">{{ $row['time'] }}</dd>@endif
                                </div>
                            @endforeach
                        </dl>
                        @if ($settings['footer_contact_link'])
                            <a href="{{ route('contact') }}" class="mt-5 inline-flex items-center gap-1.5 text-sm font-bold text-accent hover:text-white">{{ $settings['footer_contact_link'] }}<x-icon name="arrow-right" class="size-4" /></a>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </div>

    <div class="border-t border-white/10">
        <div class="container-page flex flex-wrap items-center justify-between gap-x-6 gap-y-2 px-18 py-5 text-xs text-slate-500 sm:px-22">
            <span>{{ \App\Support\Settings::copyright() }}</span>
            @if ($legal)
                <span class="flex flex-wrap gap-x-5 gap-y-1">@foreach ($legal as $link)<a href="{{ $link['url'] }}" class="hover:text-slate-300">{{ $link['title'] }}</a>@endforeach</span>
            @endif
        </div>
    </div>

    @if ($settings['cookie_notice'])
        <div x-data="cookieNotice" x-show="visible" x-cloak class="fixed inset-x-3 bottom-3 z-50 mx-auto flex max-w-xl flex-wrap items-center gap-3 rounded-2xl bg-white p-4 text-sm text-ink shadow-lift sm:inset-x-6">
            <p class="min-w-0 flex-1">{{ $settings['cookie_notice'] }}
                @if ($cookiePage = \App\Models\Page::linkFor('cookies'))<a href="{{ $cookiePage['url'] }}" class="font-semibold text-primary hover:underline">{{ $cookiePage['title'] }}</a>@endif</p>
            <button type="button" x-on:click="accept" class="btn-primary btn-sm">Tamam</button>
        </div>
    @endif
</footer>
