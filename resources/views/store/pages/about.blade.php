{{-- Hakkımızda: 3D marka sahneli karşılama (resources/js/scenes/about.js), sayaçlı rakamlar (gerçek verilerden), hikâye
     (sayfa metni) ile fotoğraf, alıntı ve mağaza kartı, değerler, hizmetler, çalışma adımları, farkımız, yolculuk,
     müşteri yorumları ve alt çağrı. Tüm yazı ve kartlar Ayarlar › Kurumsal sayfalar'dan; boş bırakılan bölüm gösterilmez. --}}
@php
    use App\Support\Settings;

    $image = Settings::fileUrl('about_image_path');
    $scene = Settings::flag('about_scene');
    $founded = (int) $settings['about_founded'];
    $years = $founded ? max(1, now()->year - $founded) : null;
    $statItems = array_values(array_filter([
        $years && $settings['about_stat_years'] ? [$years, '+', $settings['about_stat_years'], 'clock'] : null,
        $stats['products'] && $settings['about_stat_products'] ? [$stats['products'], '+', $settings['about_stat_products'], 'book'] : null,
        $stats['publishers'] && $settings['about_stat_publishers'] ? [$stats['publishers'], '+', $settings['about_stat_publishers'], 'layers'] : null,
        $stats['districts'] && $settings['about_stat_districts'] ? [$stats['districts'], '', $settings['about_stat_districts'], 'truck'] : null,
    ]));
    $points = Settings::iconLines('about_hero_points');
    $values = Settings::iconLines('about_values');
    $features = Settings::iconLines('about_features');
    $steps = Settings::iconLines('about_steps');
    $milestones = Settings::iconLines('about_milestones'); // icon alanı = yıl
    // simge|Başlık|Açıklama|Bağlantı (bağlantı yalnız site içi ya da http(s))
    $services = collect(preg_split('/\R/u', (string) $settings['about_services']))
        ->map(fn ($l) => array_map('trim', explode('|', $l)) + [1 => '', 2 => '', 3 => ''])
        ->filter(fn ($p) => $p[1] !== '')
        ->map(fn ($p) => ['icon' => $p[0] ?: 'check', 'title' => $p[1], 'text' => $p[2], 'url' => preg_match('#^(/|https?://)#i', $p[3]) ? $p[3] : null])
        ->values()->all();
    // Ad|Bilgi|Yorum
    $testimonials = collect(preg_split('/\R/u', (string) $settings['about_testimonials']))
        ->map(fn ($l) => array_map('trim', explode('|', $l, 3)) + [1 => '', 2 => ''])
        ->filter(fn ($p) => $p[0] !== '' && $p[2] !== '')
        ->map(fn ($p) => ['name' => $p[0], 'role' => $p[1], 'text' => $p[2], 'initial' => mb_strtoupper(mb_substr($p[0], 0, 1))])
        ->values()->all();
    $hours = Settings::workingHours();
    $story = trim((string) $page->body) !== '' ? $page->html() : null;
    $showContact = Settings::flag('pages_show_contact');
    $hasInfo = $hours || ($showContact && ($settings['address'] || $settings['phone'] || $settings['email']));
    // Tailwind sınıfları derlemede bulunsun diye düz yazılır (birleştirilmez)
    $grid2to4 = fn (int $n) => [1 => '', 2 => 'md:grid-cols-2', 3 => 'md:grid-cols-3'][$n] ?? 'md:grid-cols-2 lg:grid-cols-4';
    $statCols = [1 => '', 2 => 'sm:grid-cols-2', 3 => 'sm:grid-cols-3'][count($statItems)] ?? 'grid-cols-2 lg:grid-cols-4';
@endphp
<x-layouts.store :title="$page->title" :description="$page->meta_description ?: \Illuminate\Support\Str::limit($settings['about_lead'] ?: strip_tags($page->html()), 160)">
    {{-- Karşılama --}}
    <section class="about-hero surface-deep relative isolate overflow-hidden text-white">
        <div class="info-hero-grid pointer-events-none absolute inset-0 -z-10" aria-hidden="true"></div>
        <div class="about-hero-glow pointer-events-none absolute -z-10" aria-hidden="true"></div>
        <div class="container-page grid items-center gap-10 py-12 sm:py-16 lg:grid-cols-[1.05fr_1fr] lg:gap-6 {{ $statItems ? 'pb-28 sm:pb-36' : '' }}">
            <div class="min-w-0">
                <x-breadcrumb class="info-breadcrumb" :items="[[$page->title, null]]" />
                @if ($settings['about_kicker'])
                    <p class="mt-7 inline-flex items-center gap-2 rounded-full bg-white/[0.07] px-3.5 py-1.5 text-xs font-bold tracking-[0.24em] text-accent uppercase ring-1 ring-white/10">
                        <span class="relative flex size-2"><span class="absolute inset-0 animate-ping rounded-full bg-accent/60"></span><span class="relative size-2 rounded-full bg-accent"></span></span>{{ $settings['about_kicker'] }}
                    </p>
                @endif
                <h1 class="mt-5 max-w-xl font-display text-4xl leading-[1.05] font-extrabold tracking-tight sm:text-5xl lg:text-[3.6rem]">{{ $settings['about_title'] ?: $page->title }}</h1>
                @if ($settings['about_lead'])<p class="mt-5 max-w-xl text-lg leading-relaxed text-slate-300">{{ $settings['about_lead'] }}</p>@endif
                @if ($points)
                    <ul class="mt-6 grid max-w-xl gap-2.5 sm:grid-cols-2">
                        @foreach ($points as $p)
                            <li class="flex items-center gap-2.5 text-sm font-semibold text-white/90"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-accent text-ink"><x-icon :name="$p['icon']" class="size-3.5" /></span>{{ $p['title'] }}</li>
                        @endforeach
                    </ul>
                @endif
                <div class="mt-8 flex flex-wrap gap-3">
                    @if ($settings['about_primary_button'])<a href="{{ route('products.index') }}" class="btn-accent">{{ $settings['about_primary_button'] }}<x-icon name="arrow-right" class="size-4" /></a>@endif
                    @if ($settings['about_secondary_button'])<a href="{{ route('contact') }}" class="btn border border-white/25 bg-white/5 text-white hover:bg-white/10">{{ $settings['about_secondary_button'] }}</a>@endif
                </div>
            </div>

            <div class="relative min-w-0">
                @if ($scene || ! $image)
                    {{-- 3D marka sahnesi; WebGL yoksa ya da hareket azaltılmışsa düz marka işareti kalır --}}
                    <div @if ($scene) data-scene="about" @endif class="about-stage relative mx-auto aspect-[5/4] w-full max-w-xl">
                        <div class="scene-fallback absolute inset-0 flex items-center justify-center" aria-hidden="true">
                            <div class="absolute inset-[14%] rounded-[2.5rem] bg-white/[0.04] ring-1 ring-white/10"></div>
                            <svg viewBox="0 0 40 40" class="about-mark relative size-[46%]">
                                <path class="brand-back" d="M14 3h15.5a4 4 0 0 1 4 4v22.5a4 4 0 0 1-4 4H14a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4Z"/>
                                <g class="brand-front">
                                    <path class="brand-sheet" d="M8.5 9h14.2L29 15.3V33a4 4 0 0 1-4 4H8.5a4 4 0 0 1-4-4V13a4 4 0 0 1 4-4Z"/>
                                    <path class="brand-fold" d="M22.7 9v4.3a2 2 0 0 0 2 2H29Z"/>
                                    <rect class="brand-ink" x="10" y="19" width="3" height="13" rx="1.5"/><rect class="brand-ink" x="10" y="19" width="13" height="3" rx="1.5"/>
                                    <rect class="brand-ink" x="10" y="24" width="9.5" height="3" rx="1.5"/><rect class="brand-ink" x="10" y="29" width="13" height="3" rx="1.5"/>
                                </g>
                            </svg>
                        </div>
                        <div class="pointer-events-none absolute inset-x-[24%] bottom-[7%] h-10 rounded-[100%] bg-black/45 blur-2xl" aria-hidden="true"></div>
                        @if ($scene)<canvas data-scene-canvas class="scene-canvas absolute inset-0 block size-full cursor-pointer" aria-hidden="true"></canvas>@endif
                        @foreach (array_slice($features, 0, 2) as $i => $f)
                            <div class="about-float {{ $i ? 'right-0 bottom-[10%]' : 'top-[8%] left-0' }} pointer-events-none absolute flex items-center gap-3 rounded-2xl bg-white/95 p-2.5 pr-4 text-ink shadow-lift backdrop-blur">
                                <span class="flex size-10 items-center justify-center rounded-xl {{ $i ? 'bg-accent-soft text-accent-ink' : 'bg-primary-soft text-primary' }}"><x-icon :name="$f['icon']" class="size-5" /></span>
                                <span class="min-w-0"><span class="block text-sm font-bold">{{ $f['title'] }}</span>@if ($f['text'])<span class="block max-w-[11rem] truncate text-[11px] text-muted">{{ $f['text'] }}</span>@endif</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <figure class="about-photo-tilt relative mx-auto max-w-xl">
                        <span class="about-photo-back" aria-hidden="true"></span>
                        <img src="{{ $image }}" alt="{{ $settings['site_name'] }}" class="relative aspect-[4/3] w-full rounded-[2rem] object-cover shadow-[0_40px_80px_-30px_rgba(0,0,0,0.6)] ring-1 ring-white/15" loading="eager">
                    </figure>
                @endif
                @if ($years && $settings['about_badge'])
                    <div class="about-badge absolute -top-2 right-4 sm:right-10">
                        <p class="font-display text-4xl leading-none font-extrabold text-ink tabular-nums">{{ $years }}</p>
                        <p class="mt-1 max-w-[7rem] text-[11px] leading-tight font-bold text-ink/70">{{ $settings['about_badge'] }}</p>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Rakamlar (ekrana gelince sayarak artar) --}}
    @if ($statItems)
        <div class="container-page relative z-10 -mt-20">
            <div class="about-stats grid {{ $statCols }}">
                @foreach ($statItems as [$value, $suffix, $label, $icon])
                    <div class="about-stat">
                        <span class="about-stat-icon"><x-icon :name="$icon" class="size-5" /></span>
                        <p class="mt-4 font-display text-4xl font-extrabold text-ink tabular-nums"><span data-count-to="{{ $value }}">{{ number_format($value, 0, ',', '.') }}</span>{{ $suffix }}</p>
                        <p class="mt-1 text-sm font-medium text-muted">{{ $label }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="space-y-24 py-20 sm:space-y-28 sm:py-24">
        {{-- Hikâye --}}
        @if ($story)
            <section class="container-page reveal grid items-start gap-12 {{ ($image && $scene) || $hasInfo ? 'lg:grid-cols-[1.15fr_1fr] lg:gap-16' : 'max-w-3xl' }}">
                <div class="min-w-0">
                    @if ($settings['about_story_kicker'])<p class="about-kicker">{{ $settings['about_story_kicker'] }}</p>@endif
                    @if ($settings['about_story_title'])<h2 class="about-title">{{ $settings['about_story_title'] }}</h2>@endif
                    <div class="about-story prose-page mt-6 text-base leading-relaxed">{{ $story }}</div>
                    @if ($settings['about_quote'])
                        <blockquote class="about-quote mt-8">
                            <p>{{ $settings['about_quote'] }}</p>
                            @if ($settings['about_quote_author'])<footer>— {{ $settings['about_quote_author'] }}, {{ $settings['site_name'] }}</footer>@endif
                        </blockquote>
                    @endif
                </div>
                @if (($image && $scene) || $hasInfo)
                    <div class="space-y-5 lg:sticky lg:top-36">
                        @if ($image && $scene)
                            <figure class="about-photo-tilt relative">
                                <span class="about-photo-back" aria-hidden="true"></span>
                                <img src="{{ $image }}" alt="{{ $settings['site_name'] }}" class="relative aspect-[4/3] w-full rounded-[2rem] object-cover shadow-[0_40px_80px_-35px_rgba(15,23,42,0.55)]" loading="lazy">
                            </figure>
                        @endif
                        @if ($hasInfo)
                            <div class="surface-deep relative isolate overflow-hidden rounded-[2rem] p-7 text-white sm:p-8">
                                <div class="info-hero-grid pointer-events-none absolute inset-0 -z-10" aria-hidden="true"></div>
                                <div class="flex items-center justify-between gap-4">
                                    <x-brand :name="$settings['site_name']" dark />
                                    @if ($settings['about_info_title'])<span class="rounded-full bg-white/10 px-3 py-1 text-xs font-bold text-accent ring-1 ring-white/15">{{ $settings['about_info_title'] }}</span>@endif
                                </div>
                                @if ($showContact)
                                    <ul class="mt-6 space-y-3.5 text-sm">
                                        @if ($settings['address'])<li class="flex gap-3"><x-icon name="pin" class="size-4 shrink-0 translate-y-0.5 text-accent" /><span class="text-slate-300">{{ $settings['address'] }}</span></li>@endif
                                        @if ($settings['phone'])<li class="flex gap-3"><x-icon name="phone" class="size-4 shrink-0 translate-y-0.5 text-accent" /><a href="tel:{{ preg_replace('/[^0-9+]/', '', $settings['phone']) }}" class="font-semibold hover:text-accent">{{ $settings['phone'] }}</a></li>@endif
                                        @if ($settings['email'])<li class="flex gap-3"><x-icon name="mail" class="size-4 shrink-0 translate-y-0.5 text-accent" /><a href="mailto:{{ $settings['email'] }}" class="break-all hover:text-accent">{{ $settings['email'] }}</a></li>@endif
                                    </ul>
                                @endif
                                @if ($hours)
                                    <dl class="mt-6 space-y-2.5 border-t border-white/10 pt-5 text-sm">
                                        @foreach ($hours as $row)
                                            <div class="flex items-center justify-between gap-4"><dt class="flex items-center gap-2 text-slate-400"><x-icon name="clock" class="size-3.5 text-accent" />{{ $row['day'] }}</dt><dd class="font-bold tabular-nums">{{ $row['time'] }}</dd></div>
                                        @endforeach
                                    </dl>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif
            </section>
        @endif

        {{-- Değerler --}}
        @if ($values)
            <section class="about-band reveal py-20 sm:py-24">
                <div class="container-page">
                    <div class="mx-auto max-w-2xl text-center">
                        @if ($settings['about_values_kicker'])<p class="about-kicker">{{ $settings['about_values_kicker'] }}</p>@endif
                        @if ($settings['about_values_title'])<h2 class="about-title">{{ $settings['about_values_title'] }}</h2>@endif
                    </div>
                    <div class="mt-12 grid gap-6 {{ [1 => '', 2 => 'md:grid-cols-2'][count($values)] ?? 'md:grid-cols-3' }}">
                        @foreach ($values as $i => $v)
                            <div class="about-tilt">
                                <div class="about-value about-value-{{ $i % 3 }} relative h-full overflow-hidden rounded-[1.75rem] p-8">
                                    <span class="about-value-num" aria-hidden="true">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                    <span class="relative flex size-14 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/25"><x-icon :name="$v['icon']" class="size-7" /></span>
                                    <h3 class="relative mt-6 font-display text-2xl font-extrabold">{{ $v['title'] }}</h3>
                                    @if ($v['text'])<p class="relative mt-2.5 leading-relaxed opacity-90">{{ $v['text'] }}</p>@endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- Hizmetler --}}
        @if ($services)
            <section class="container-page reveal">
                <div class="max-w-2xl">
                    @if ($settings['about_services_kicker'])<p class="about-kicker">{{ $settings['about_services_kicker'] }}</p>@endif
                    @if ($settings['about_services_title'])<h2 class="about-title">{{ $settings['about_services_title'] }}</h2>@endif
                </div>
                <div class="mt-10 grid gap-5 {{ $grid2to4(count($services)) }}">
                    @foreach ($services as $s)
                        @if ($s['url'])
                            <a href="{{ $s['url'] }}" class="about-service group">
                        @else
                            <div class="about-service group">
                        @endif
                            <span class="about-service-icon"><x-icon :name="$s['icon']" class="size-6" /></span>
                            <h3 class="mt-6 font-display text-lg font-extrabold">{{ $s['title'] }}</h3>
                            @if ($s['text'])<p class="mt-2 text-sm leading-relaxed text-muted">{{ $s['text'] }}</p>@endif
                            @if ($s['url'])<span class="mt-auto inline-flex items-center gap-1.5 pt-5 text-sm font-bold text-primary">İncele<x-icon name="arrow-right" class="size-4 transition group-hover:translate-x-1" /></span>@endif
                        @if ($s['url'])
                            </a>
                        @else
                            </div>
                        @endif
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Çalışma adımları --}}
        @if ($steps)
            <section class="container-page reveal">
                <div class="surface-deep relative isolate overflow-hidden rounded-[2.25rem] px-6 py-12 text-white sm:px-12 sm:py-16">
                    <div class="info-hero-grid pointer-events-none absolute inset-0 -z-10" aria-hidden="true"></div>
                    <div class="mx-auto max-w-2xl text-center">
                        @if ($settings['about_steps_kicker'])<p class="about-kicker !text-accent">{{ $settings['about_steps_kicker'] }}</p>@endif
                        @if ($settings['about_steps_title'])<h2 class="about-title !text-white">{{ $settings['about_steps_title'] }}</h2>@endif
                    </div>
                    <ol class="about-steps mt-12 grid gap-8 {{ $grid2to4(count($steps)) }}">
                        @foreach ($steps as $i => $st)
                            <li class="about-step">
                                <span class="about-step-icon"><x-icon :name="$st['icon']" class="size-6" /><span class="about-step-num">{{ $i + 1 }}</span></span>
                                <h3 class="mt-5 font-display text-lg font-extrabold">{{ $st['title'] }}</h3>
                                @if ($st['text'])<p class="mt-1.5 text-sm leading-relaxed text-white/65">{{ $st['text'] }}</p>@endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>
        @endif

        {{-- Farkımız: üçten fazla kartta ilki büyük --}}
        @if ($features)
            @php($bento = count($features) > 2)
            <section class="container-page reveal">
                <div class="mx-auto max-w-2xl text-center">
                    @if ($settings['about_features_kicker'])<p class="about-kicker">{{ $settings['about_features_kicker'] }}</p>@endif
                    @if ($settings['about_features_title'])<h2 class="about-title">{{ $settings['about_features_title'] }}</h2>@endif
                </div>
                <div class="mt-12 grid gap-5 {{ $bento ? 'md:grid-cols-3' : ([1 => '', 2 => 'md:grid-cols-2'][count($features)]) }}">
                    @foreach ($features as $i => $f)
                        @php($lead = $bento && $i === 0)
                        {{-- Büyük kartın yanında kalan kartlar ikişerli dizilir; tek kalan son kart iki sütun kaplar --}}
                        @php($wide = $bento && $i === count($features) - 1 && (count($features) - 1) % 2 === 1)
                        <div class="about-feature group {{ $lead ? 'about-feature-lead md:row-span-2' : '' }} {{ $wide ? 'md:col-span-2' : '' }}">
                            <span class="about-feature-icon"><x-icon :name="$f['icon']" class="size-6" /></span>
                            <h3 class="mt-5 font-display font-extrabold {{ $lead ? 'text-2xl sm:text-3xl' : 'text-lg' }}">{{ $f['title'] }}</h3>
                            @if ($f['text'])<p class="mt-2 leading-relaxed {{ $lead ? 'text-white/75' : 'text-sm text-muted' }}">{{ $f['text'] }}</p>@endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Yolculuk --}}
        @if ($milestones)
            <section class="container-page reveal">
                <div class="mx-auto max-w-2xl text-center">
                    @if ($settings['about_milestones_kicker'])<p class="about-kicker">{{ $settings['about_milestones_kicker'] }}</p>@endif
                    @if ($settings['about_milestones_title'])<h2 class="about-title">{{ $settings['about_milestones_title'] }}</h2>@endif
                </div>
                <ol class="about-journey mx-auto mt-14 max-w-4xl">
                    @foreach ($milestones as $i => $m)
                        <li class="about-milestone {{ $i % 2 ? 'is-right' : '' }}">
                            <span class="about-milestone-dot" aria-hidden="true"></span>
                            <div class="about-milestone-card">
                                <span class="about-milestone-year">{{ $m['icon'] }}</span>
                                <h3 class="mt-3 font-display text-lg font-extrabold">{{ $m['title'] }}</h3>
                                @if ($m['text'])<p class="mt-1 text-sm leading-relaxed text-muted">{{ $m['text'] }}</p>@endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        {{-- Müşteri yorumları --}}
        @if ($testimonials)
            <section class="about-band reveal py-20 sm:py-24">
                <div class="container-page">
                    <div class="mx-auto max-w-2xl text-center">
                        @if ($settings['about_testimonials_kicker'])<p class="about-kicker">{{ $settings['about_testimonials_kicker'] }}</p>@endif
                        @if ($settings['about_testimonials_title'])<h2 class="about-title">{{ $settings['about_testimonials_title'] }}</h2>@endif
                    </div>
                    <div class="mt-12 grid gap-5 {{ [1 => 'mx-auto max-w-2xl', 2 => 'md:grid-cols-2'][count($testimonials)] ?? 'md:grid-cols-3' }}">
                        @foreach ($testimonials as $t)
                            <figure class="about-testimonial">
                                <span class="about-testimonial-mark" aria-hidden="true">“</span>
                                <blockquote class="relative leading-relaxed text-ink/85">{{ $t['text'] }}</blockquote>
                                <figcaption class="mt-6 flex items-center gap-3">
                                    <span class="flex size-11 items-center justify-center rounded-full bg-primary font-display font-extrabold text-white">{{ $t['initial'] }}</span>
                                    <span><span class="block font-bold">{{ $t['name'] }}</span>@if ($t['role'])<span class="block text-xs text-muted">{{ $t['role'] }}</span>@endif</span>
                                </figcaption>
                            </figure>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- Alt çağrı --}}
        @if ($settings['about_cta_title'])
            <section class="container-page reveal">
                <div class="surface-deep relative isolate overflow-hidden rounded-[2.25rem] px-6 py-14 text-center text-white sm:px-12 sm:py-16">
                    <div class="info-hero-grid pointer-events-none absolute inset-0 -z-10" aria-hidden="true"></div>
                    <span class="about-cta-tile about-cta-tile-a" aria-hidden="true"></span>
                    <span class="about-cta-tile about-cta-tile-b" aria-hidden="true"></span>
                    <h2 class="relative mx-auto max-w-2xl font-display text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $settings['about_cta_title'] }}</h2>
                    @if ($settings['about_cta_text'])<p class="relative mx-auto mt-3 max-w-xl text-slate-300">{{ $settings['about_cta_text'] }}</p>@endif
                    <div class="relative mt-8 flex flex-wrap justify-center gap-3">
                        @if ($settings['about_primary_button'])<a href="{{ route('products.index') }}" class="btn-accent">{{ $settings['about_primary_button'] }}<x-icon name="arrow-right" class="size-4" /></a>@endif
                        @if ($settings['about_secondary_button'])<a href="{{ route('contact') }}" class="btn border border-white/25 bg-white/5 text-white hover:bg-white/10">{{ $settings['about_secondary_button'] }}</a>@endif
                    </div>
                </div>
            </section>
        @endif
    </div>
</x-layouts.store>
