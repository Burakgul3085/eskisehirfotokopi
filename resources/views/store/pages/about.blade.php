{{-- Hakkımızda: koyu karşılama (görsel ya da marka işareti), gerçek verilerden rakamlar, hikâye (sayfa metni),
     değerler, farkımız, kilometre taşları ve alt çağrı. Tüm başlık ve kartlar Ayarlar › Kurumsal sayfalar'dan. --}}
@php
    use App\Support\Settings;

    $image = Settings::fileUrl('about_image_path');
    $founded = (int) $settings['about_founded'];
    $years = $founded ? max(1, now()->year - $founded) : null;
    $num = fn (int $v) => number_format($v, 0, ',', '.');
    $statItems = array_values(array_filter([
        $years && $settings['about_stat_years'] ? [$num($years).'+', $settings['about_stat_years'], 'clock'] : null,
        $stats['products'] && $settings['about_stat_products'] ? [$num($stats['products']).'+', $settings['about_stat_products'], 'book'] : null,
        $stats['publishers'] && $settings['about_stat_publishers'] ? [$num($stats['publishers']).'+', $settings['about_stat_publishers'], 'layers'] : null,
        $stats['districts'] && $settings['about_stat_districts'] ? [$num($stats['districts']), $settings['about_stat_districts'], 'truck'] : null,
    ]));
    $values = Settings::iconLines('about_values');
    $features = Settings::iconLines('about_features');
    $milestones = Settings::iconLines('about_milestones'); // icon alanı = yıl
    $hours = Settings::workingHours();
    $story = trim((string) $page->body) !== '' ? $page->html() : null;
@endphp
<x-layouts.store :title="$page->title" :description="$page->meta_description ?: \Illuminate\Support\Str::limit($settings['about_lead'] ?: strip_tags($page->html()), 160)">
    {{-- Karşılama --}}
    <section class="info-hero surface-deep relative isolate overflow-hidden text-white">
        <div class="container-page grid items-center gap-10 py-12 sm:py-16 lg:grid-cols-[1.05fr_1fr] {{ $statItems ? 'pb-28 sm:pb-32' : '' }}">
            <div class="min-w-0">
                <x-breadcrumb class="info-breadcrumb" :items="[[$page->title, null]]" />
                <p class="mt-6 inline-flex items-center gap-2 text-xs font-bold tracking-[0.28em] text-accent uppercase">
                    <span class="flex gap-1" aria-hidden="true"><span class="size-1.5 rounded-full bg-accent"></span><span class="size-1.5 rounded-full bg-white/60"></span><span class="size-1.5 rounded-full bg-primary"></span></span>{{ $page->title }}
                </p>
                <h1 class="mt-4 max-w-xl font-display text-4xl leading-[1.08] font-extrabold tracking-tight sm:text-5xl">{{ $settings['about_title'] ?: $page->title }}</h1>
                @if ($settings['about_lead'])<p class="mt-5 max-w-xl text-lg leading-relaxed text-slate-300">{{ $settings['about_lead'] }}</p>@endif
                <div class="mt-8 flex flex-wrap gap-3">
                    @if ($settings['about_primary_button'])<a href="{{ route('products.index') }}" class="btn-accent">{{ $settings['about_primary_button'] }}<x-icon name="arrow-right" class="size-4" /></a>@endif
                    @if ($settings['about_secondary_button'])<a href="{{ route('contact') }}" class="btn border border-white/25 bg-white/5 text-white hover:bg-white/10">{{ $settings['about_secondary_button'] }}</a>@endif
                </div>
            </div>

            <div class="relative min-w-0">
                @if ($image)
                    <div class="about-photo relative overflow-hidden rounded-[2rem] shadow-[0_40px_80px_-30px_rgba(0,0,0,0.6)] ring-1 ring-white/15">
                        <img src="{{ $image }}" alt="{{ $settings['site_name'] }}" class="aspect-[4/3] w-full object-cover" loading="eager">
                    </div>
                @else
                    {{-- Görsel yoksa: büyük marka işareti ve yüzen bilgi kartları --}}
                    <div class="about-art relative mx-auto aspect-[4/3] max-w-lg">
                        <div class="absolute inset-[12%] rounded-[2.5rem] bg-white/[0.04] ring-1 ring-white/10"></div>
                        <svg viewBox="0 0 40 40" class="about-mark absolute inset-[22%] size-[56%]" aria-hidden="true">
                            <path class="brand-back" d="M14 3h15.5a4 4 0 0 1 4 4v22.5a4 4 0 0 1-4 4H14a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4Z"/>
                            <g class="brand-front">
                                <path class="brand-sheet" d="M8.5 9h14.2L29 15.3V33a4 4 0 0 1-4 4H8.5a4 4 0 0 1-4-4V13a4 4 0 0 1 4-4Z"/>
                                <path class="brand-fold" d="M22.7 9v4.3a2 2 0 0 0 2 2H29Z"/>
                                <rect class="brand-ink" x="10" y="19" width="3" height="13" rx="1.5"/><rect class="brand-ink" x="10" y="19" width="13" height="3" rx="1.5"/>
                                <rect class="brand-ink" x="10" y="24" width="9.5" height="3" rx="1.5"/><rect class="brand-ink" x="10" y="29" width="13" height="3" rx="1.5"/>
                            </g>
                        </svg>
                        @foreach (array_slice($features, 0, 2) as $i => $f)
                            <div class="about-float {{ $i ? 'right-0 bottom-[8%]' : 'top-[6%] left-0' }} absolute flex items-center gap-3 rounded-2xl bg-white p-3 pr-4 text-ink shadow-lift">
                                <span class="flex size-10 items-center justify-center rounded-xl {{ $i ? 'bg-accent-soft text-accent-ink' : 'bg-primary-soft text-primary' }}"><x-icon :name="$f['icon']" class="size-5" /></span>
                                <span class="text-sm font-bold">{{ $f['title'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
                @if ($years && $settings['about_badge'])
                    <div class="absolute -top-4 left-4 rounded-2xl bg-ink/90 px-5 py-3 shadow-lift ring-1 ring-white/10 backdrop-blur sm:left-6">
                        <p class="font-display text-3xl leading-none font-extrabold text-accent">{{ $years }}</p>
                        <p class="mt-1 text-xs font-semibold text-slate-300">{{ $settings['about_badge'] }}</p>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Rakamlar --}}
    @if ($statItems)
        <div class="container-page relative z-10 -mt-16">
            <div class="grid divide-line rounded-3xl bg-white shadow-[0_30px_60px_-30px_rgba(15,23,42,0.35)] ring-1 ring-line sm:divide-x {{ ['', 'sm:grid-cols-1', 'sm:grid-cols-2', 'sm:grid-cols-3', 'grid-cols-2 sm:grid-cols-4'][count($statItems)] }}">
                @foreach ($statItems as [$value, $label, $icon])
                    <div class="flex flex-col items-center px-4 py-7 text-center">
                        <span class="flex size-10 items-center justify-center rounded-xl bg-primary-soft text-primary"><x-icon :name="$icon" class="size-5" /></span>
                        <p class="mt-3 font-display text-3xl font-extrabold text-ink tabular-nums">{{ $value }}</p>
                        <p class="mt-0.5 text-sm text-muted">{{ $label }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="space-y-20 py-16 sm:space-y-24 sm:py-20">
        {{-- Hikâye --}}
        @if ($story)
            <section class="container-page grid items-start gap-10 lg:grid-cols-[1.25fr_1fr]">
                <div class="min-w-0">
                    @if ($settings['about_story_kicker'])<p class="about-kicker">{{ $settings['about_story_kicker'] }}</p>@endif
                    @if ($settings['about_story_title'])<h2 class="about-title">{{ $settings['about_story_title'] }}</h2>@endif
                    <div class="prose-page mt-5 text-base leading-relaxed">{{ $story }}</div>
                </div>
                <div class="surface-deep relative overflow-hidden rounded-[2rem] p-7 text-white sm:p-8">
                    <x-brand :name="$settings['site_name']" dark />
                    <ul class="mt-6 space-y-3.5 text-sm">
                        @if ($settings['address'])<li class="flex gap-3"><x-icon name="pin" class="size-4 shrink-0 translate-y-0.5 text-accent" /><span class="text-slate-300">{{ $settings['address'] }}</span></li>@endif
                        @if ($settings['phone'])<li class="flex gap-3"><x-icon name="phone" class="size-4 shrink-0 translate-y-0.5 text-accent" /><a href="tel:{{ preg_replace('/[^0-9+]/', '', $settings['phone']) }}" class="font-semibold hover:text-accent">{{ $settings['phone'] }}</a></li>@endif
                        @if ($settings['email'])<li class="flex gap-3"><x-icon name="mail" class="size-4 shrink-0 translate-y-0.5 text-accent" /><a href="mailto:{{ $settings['email'] }}" class="break-all hover:text-accent">{{ $settings['email'] }}</a></li>@endif
                    </ul>
                    @if ($hours)
                        <dl class="mt-6 space-y-2 border-t border-white/10 pt-5 text-sm">
                            @foreach ($hours as $row)
                                <div class="flex justify-between gap-4"><dt class="text-slate-400">{{ $row['day'] }}</dt><dd class="font-bold tabular-nums">{{ $row['time'] }}</dd></div>
                            @endforeach
                        </dl>
                    @endif
                </div>
            </section>
        @endif

        {{-- Değerler --}}
        @if ($values)
            <section class="bg-surface py-16 sm:py-20">
                <div class="container-page">
                    <div class="text-center">
                        @if ($settings['about_values_kicker'])<p class="about-kicker">{{ $settings['about_values_kicker'] }}</p>@endif
                        @if ($settings['about_values_title'])<h2 class="about-title">{{ $settings['about_values_title'] }}</h2>@endif
                    </div>
                    <div class="mt-10 grid gap-5 {{ [1 => 'md:grid-cols-1', 2 => 'md:grid-cols-2'][count($values)] ?? 'md:grid-cols-3' }}">
                        @foreach ($values as $i => $v)
                            <div class="about-value about-value-{{ $i % 3 }} relative overflow-hidden rounded-3xl p-7">
                                <span class="flex size-12 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/20"><x-icon :name="$v['icon']" class="size-6" /></span>
                                <h3 class="mt-5 font-display text-xl font-extrabold">{{ $v['title'] }}</h3>
                                @if ($v['text'])<p class="mt-2 leading-relaxed opacity-85">{{ $v['text'] }}</p>@endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- Farkımız --}}
        @if ($features)
            <section class="container-page">
                <div class="max-w-2xl">
                    @if ($settings['about_features_kicker'])<p class="about-kicker">{{ $settings['about_features_kicker'] }}</p>@endif
                    @if ($settings['about_features_title'])<h2 class="about-title">{{ $settings['about_features_title'] }}</h2>@endif
                </div>
                <div class="mt-10 grid gap-5 sm:grid-cols-2 {{ [1 => 'lg:grid-cols-1', 2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3'][count($features)] ?? 'lg:grid-cols-4' }}">
                    @foreach ($features as $f)
                        <div class="about-feature group rounded-3xl border border-line bg-white p-6">
                            <span class="flex size-12 items-center justify-center rounded-2xl bg-primary-soft text-primary transition group-hover:bg-primary group-hover:text-white"><x-icon :name="$f['icon']" class="size-6" /></span>
                            <h3 class="mt-5 font-display text-lg font-extrabold">{{ $f['title'] }}</h3>
                            @if ($f['text'])<p class="mt-1.5 text-sm leading-relaxed text-muted">{{ $f['text'] }}</p>@endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Kilometre taşları --}}
        @if ($milestones)
            <section class="container-page">
                <div class="text-center">
                    @if ($settings['about_milestones_kicker'])<p class="about-kicker">{{ $settings['about_milestones_kicker'] }}</p>@endif
                    @if ($settings['about_milestones_title'])<h2 class="about-title">{{ $settings['about_milestones_title'] }}</h2>@endif
                </div>
                <ol class="about-timeline mx-auto mt-12 max-w-3xl">
                    @foreach ($milestones as $m)
                        <li class="relative pb-10 pl-24 last:pb-0 sm:pl-32">
                            <span class="about-year absolute top-0 left-0">{{ $m['icon'] }}</span>
                            <h3 class="font-display text-lg font-extrabold">{{ $m['title'] }}</h3>
                            @if ($m['text'])<p class="mt-1 leading-relaxed text-muted">{{ $m['text'] }}</p>@endif
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        {{-- Alt çağrı --}}
        @if ($settings['about_cta_title'])
            <section class="container-page">
                <div class="surface-deep relative overflow-hidden rounded-[2rem] px-6 py-12 text-center text-white sm:px-12">
                    <h2 class="mx-auto max-w-2xl font-display text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $settings['about_cta_title'] }}</h2>
                    @if ($settings['about_cta_text'])<p class="mx-auto mt-3 max-w-xl text-slate-300">{{ $settings['about_cta_text'] }}</p>@endif
                    <div class="mt-7 flex flex-wrap justify-center gap-3">
                        @if ($settings['about_primary_button'])<a href="{{ route('products.index') }}" class="btn-accent">{{ $settings['about_primary_button'] }}<x-icon name="arrow-right" class="size-4" /></a>@endif
                        @if ($settings['about_secondary_button'])<a href="{{ route('contact') }}" class="btn border border-white/25 bg-white/5 text-white hover:bg-white/10">{{ $settings['about_secondary_button'] }}</a>@endif
                    </div>
                </div>
            </section>
        @endif
    </div>
</x-layouts.store>
