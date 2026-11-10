@php
    $social = \App\Support\Settings::socialLinks();
    $pageColumns = \App\Models\Page::links()['columns'];
    $columnTitles = ['corporate' => $settings['footer_title_corporate'], 'help' => $settings['footer_title_help']];
    $gridCols = ['lg:grid-cols-[1.4fr_1fr_1fr]', 'lg:grid-cols-[1.4fr_1fr_1fr_1fr]', 'lg:grid-cols-[1.4fr_1fr_1fr_1fr_1fr]'][count($pageColumns)];
@endphp
<footer class="mt-16 bg-ink text-slate-300">
    @if ($trust = \App\Support\Settings::trustItems())
        <div class="border-b border-white/10">
            <div class="container-page grid gap-6 py-8 sm:grid-cols-2 {{ [1 => 'lg:grid-cols-1', 2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3'][count($trust)] ?? 'lg:grid-cols-4' }}">
                @foreach ($trust as $item)
                    <div class="flex items-center gap-4">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-white/5 text-accent"><x-icon :name="$item['icon']" class="size-6" /></span>
                        <div>
                            <p class="font-display font-semibold text-white">{{ $item['title'] }}</p>
                            @if ($item['text'])<p class="text-sm text-slate-400">{{ $item['text'] }}</p>@endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="container-page grid gap-10 py-12 sm:grid-cols-2 {{ $gridCols }}">
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

        @foreach (['corporate', 'help'] as $col)
            @if (! empty($pageColumns[$col]))
                <div>
                    <p class="text-sm font-semibold tracking-wide text-white uppercase">{{ $columnTitles[$col] }}</p>
                    <ul class="mt-4 space-y-2.5 text-sm">
                        @foreach ($pageColumns[$col] as $link)<li><a href="{{ $link['url'] }}" class="hover:text-white">{{ $link['title'] }}</a></li>@endforeach
                        @if ($col === 'help')
                            <li><a href="{{ route('orders.track') }}" class="hover:text-white">Sipariş takibi</a></li>
                            <li><a href="{{ route('contact') }}" class="hover:text-white">İletişim</a></li>
                        @endif
                    </ul>
                </div>
            @endif
        @endforeach

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

    @if ($settings['cookie_notice'])
        <div x-data="cookieNotice" x-show="visible" x-cloak class="fixed inset-x-3 bottom-3 z-50 mx-auto flex max-w-xl flex-wrap items-center gap-3 rounded-2xl bg-white p-4 text-sm text-ink shadow-lift sm:inset-x-6">
            <p class="min-w-0 flex-1">{{ $settings['cookie_notice'] }}
                @if ($cookiePage = \App\Models\Page::linkFor('cookies'))<a href="{{ $cookiePage['url'] }}" class="font-semibold text-primary hover:underline">{{ $cookiePage['title'] }}</a>@endif</p>
            <button type="button" x-on:click="accept" class="btn-primary btn-sm">Tamam</button>
        </div>
    @endif
</footer>
