@php
    $social = \App\Support\Settings::socialLinks();
@endphp
<footer class="mt-16 bg-ink text-slate-300">
    <div class="border-b border-white/10">
        <div class="container-page grid gap-6 py-8 sm:grid-cols-3">
            @foreach ([
                ['truck', 'Kapınıza teslim', 'Eskişehir içi belirlenen bölgelere'],
                ['cash', 'Kapıda ödeme', 'Nakit veya kredi kartı ile'],
                ['printer', 'Belge baskısı', 'Belgeni yükle, biz basalım'],
            ] as [$icon, $head, $text])
                <div class="flex items-center gap-4">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-white/5 text-accent"><x-icon :name="$icon" class="size-6" /></span>
                    <div>
                        <p class="font-display font-semibold text-white">{{ $head }}</p>
                        <p class="text-sm text-slate-400">{{ $text }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="container-page grid gap-10 py-12 md:grid-cols-[1.4fr_1fr_1fr]">
        <div>
            <p class="font-display text-xl font-extrabold text-white">{{ $settings['site_name'] }}</p>
            @if ($settings['footer_about'])
                <p class="mt-3 max-w-md text-sm leading-relaxed text-slate-400">{{ $settings['footer_about'] }}</p>
            @endif
            @if ($social)
                <div class="mt-5 flex flex-wrap gap-2">
                    @foreach ($social as $link)
                        <a href="{{ $link['url'] }}" target="_blank" rel="noopener" class="rounded-lg bg-white/5 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-white/10">{{ $link['label'] }}</a>
                    @endforeach
                </div>
            @endif
        </div>

        <div>
            <p class="text-sm font-semibold tracking-wide text-white uppercase">Hesap</p>
            <ul class="mt-4 space-y-2.5 text-sm">
                <li><a href="{{ route('login') }}" class="hover:text-white">Giriş yap</a></li>
                <li><a href="{{ route('register') }}" class="hover:text-white">Üye ol</a></li>
                <li><a href="{{ route('account.index') }}" class="hover:text-white">Hesabım</a></li>
            </ul>
        </div>

        <div>
            <p class="text-sm font-semibold tracking-wide text-white uppercase">İletişim</p>
            <ul class="mt-4 space-y-3 text-sm">
                @if ($settings['phone'])
                    <li class="flex gap-2.5"><x-icon name="phone" class="size-4 shrink-0 translate-y-0.5 text-accent" /><a href="tel:{{ preg_replace('/[^0-9+]/', '', $settings['phone']) }}" class="hover:text-white">{{ $settings['phone'] }}</a></li>
                @endif
                @if ($settings['email'])
                    <li class="flex gap-2.5"><x-icon name="mail" class="size-4 shrink-0 translate-y-0.5 text-accent" /><a href="mailto:{{ $settings['email'] }}" class="hover:text-white">{{ $settings['email'] }}</a></li>
                @endif
                @if ($settings['address'])
                    <li class="flex gap-2.5"><x-icon name="pin" class="size-4 shrink-0 translate-y-0.5 text-accent" /><span>{{ $settings['address'] }}</span></li>
                @endif
                @if ($settings['working_hours'])
                    <li class="flex gap-2.5"><x-icon name="clock" class="size-4 shrink-0 translate-y-0.5 text-accent" /><span class="whitespace-pre-line">{{ $settings['working_hours'] }}</span></li>
                @endif
            </ul>
        </div>
    </div>

    <div class="border-t border-white/10">
        <div class="container-page py-5 text-xs text-slate-500">{{ \App\Support\Settings::copyright() }}</div>
    </div>
</footer>
