@php
    use App\Domain\Print\PrintOptions;
    use App\Support\Money;
    $tiers = collect($print['tiers'])->sortBy('from')->values();
    $tierRange = fn ($i) => $tiers[$i]['from'].(isset($tiers[$i + 1]) ? ' – '.($tiers[$i + 1]['from'] - 1) : ' +');
    $p = app(\App\Domain\Print\PrintSettings::class);
    $steps = $p->steps();
    // Üst alandaki rozet: ilk açık baskı türünün en düşük yaprak fiyatı
    $badgeColor = $colors[0] ?? 'bw';
    $lowest = $tiers->min(fn ($t) => (float) $t[$badgeColor]);
    $formats = [['PDF', 'bg-danger-soft text-danger'], ['Word', 'bg-info-soft text-info'], ['PowerPoint', 'bg-accent-soft text-accent-ink'], ['Excel', 'bg-success-soft text-success'], ['JPG · PNG', 'bg-primary-soft text-primary-dark']];
@endphp
<x-layouts.store title="Fotokopi ve belge baskısı" :description="$print['intro']">
    {{--
        Üst alan: koyu zemin + ana sayfadaki 3D fotokopi makinesi sahnesi (resources/js/hero-scene.js, WebGL yoksa parlayan
        yedek görünür); altında yüzen adım şeridi. Bütün yazılar Yönetim › Fotokopi › Sayfa metinleri'nden.
    --}}
    <section class="hero-3d surface-deep print-hero relative isolate overflow-hidden text-white">
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
            <div class="hero-grid absolute inset-0"></div>
            <div class="hero-spot absolute inset-0"></div>
        </div>
        <div class="container-page relative grid items-center gap-4 pt-6 pb-20 md:grid-cols-[1.1fr_1fr] md:pb-28 lg:pt-8">
            <div class="relative z-10">
                <x-breadcrumb :items="[['Fotokopi', null]]" class="print-breadcrumb" />
                @if ($p->text('page_kicker'))
                    <p class="hero-reveal mt-6 flex items-center gap-3 text-xs font-bold tracking-[0.25em] text-accent uppercase" style="--d: 0">
                        <span class="flex gap-1" aria-hidden="true"><span class="size-2 rounded-full bg-danger"></span><span class="size-2 rounded-full bg-accent"></span><span class="size-2 rounded-full bg-info"></span><span class="size-2 rounded-full bg-success"></span></span>
                        {{ $p->text('page_kicker') }}
                    </p>
                @endif
                <h1 class="hero-reveal mt-4 text-4xl leading-[1.04] font-extrabold tracking-tight sm:text-5xl lg:text-6xl" style="--d: 1">
                    <span class="block bg-gradient-to-br from-white via-white to-white/70 bg-clip-text text-transparent">{{ $p->text('page_title') }}</span>
                    @if ($p->text('page_title_accent'))<span class="block text-accent">{{ $p->text('page_title_accent') }}</span>@endif
                </h1>
                @if ($print['intro'])<p class="hero-reveal mt-5 max-w-xl text-base leading-relaxed text-white/75 sm:text-lg" style="--d: 2">{{ $print['intro'] }}</p>@endif
                <div class="hero-reveal mt-7 flex flex-wrap items-center gap-3" style="--d: 3">
                    <a href="#yukle" class="btn-accent px-6 py-3"><x-icon name="upload" class="size-5" />{{ $p->text('upload_title') }}</a>
                    <a href="#fiyatlar" class="inline-flex items-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold text-white ring-1 ring-white/25 backdrop-blur transition hover:bg-white/10">{{ $p->text('price_title') }}<x-icon name="chevron-down" class="size-4" /></a>
                </div>
            </div>

            <div class="relative">
                <div data-hero-scene class="relative h-[260px] sm:h-[340px] md:h-[400px] lg:h-[430px]">
                    <div class="hero-fallback pointer-events-none absolute inset-0 flex items-center justify-center transition-opacity duration-1000" aria-hidden="true">
                        <div class="size-56 rounded-full bg-gradient-to-br from-white/25 to-accent/20 blur-2xl md:size-72"></div>
                    </div>
                    <div class="hero-stage absolute inset-0">
                        <canvas data-hero-canvas class="block size-full" aria-hidden="true"></canvas>
                        <div data-hero-tip class="pointer-events-none absolute top-0 left-0" role="presentation"></div>
                    </div>
                    <script type="application/json" data-hero-books>[]</script>
                </div>
                @if ($lowest !== null)
                    <div class="print-price-chip hero-card absolute top-2 right-0 z-10 sm:right-2 md:top-6" style="--depth: 14">
                        <div class="hero-card-float rounded-2xl border border-white/15 bg-white/[0.09] px-5 py-4 shadow-[0_24px_50px_-24px_rgba(0,0,0,0.6)] backdrop-blur-xl" style="--d: 1">
                            <p class="text-xs font-semibold text-white/70">{{ PrintOptions::COLORS[$badgeColor] }}</p>
                            <p class="font-display text-3xl font-extrabold text-white">{{ Money::format($lowest) }}</p>
                            @if ($p->text('price_badge'))<p class="text-xs text-white/60">{{ $p->text('price_badge') }}</p>@endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>

    <div class="container-page relative z-10 pb-10" x-data="printOrder">
        <script type="application/json" data-print-config>@json($config)</script>

        @if ($steps)
            {{-- Adım şeridi: üst alanın alt kenarına biner --}}
            <ol class="print-steps -mt-16 grid gap-3 rounded-3xl border border-line/70 bg-white p-3 shadow-[0_30px_60px_-30px_rgba(15,23,42,0.35)] {{ [1 => 'sm:grid-cols-1', 2 => 'sm:grid-cols-2', 3 => 'sm:grid-cols-3', 4 => 'sm:grid-cols-2 lg:grid-cols-4'][count($steps)] }} sm:p-4">
                @foreach ($steps as $i => $step)
                    <li class="print-step flex items-center gap-4 rounded-2xl p-3 sm:p-4">
                        <span class="print-step-icon relative flex size-12 shrink-0 items-center justify-center rounded-2xl {{ ['bg-accent-soft text-accent-ink', 'bg-primary-soft text-primary-dark', 'bg-info-soft text-info', 'bg-success-soft text-success'][$i % 4] }}">
                            <x-icon :name="$step['icon']" class="size-6" />
                            <span class="absolute -top-2 -right-2 flex size-6 items-center justify-center rounded-full bg-ink text-xs font-bold text-white ring-2 ring-white">{{ $i + 1 }}</span>
                        </span>
                        <span class="min-w-0">
                            <span class="block font-display font-bold text-ink">{{ $step['title'] }}</span>
                            @if ($step['text'])<span class="block text-sm text-muted">{{ $step['text'] }}</span>@endif
                        </span>
                    </li>
                @endforeach
            </ol>
        @endif

        <div class="mt-8 grid items-start gap-6 lg:grid-cols-[1fr_360px]">
            <div class="min-w-0 space-y-6">
                <section id="yukle" class="card scroll-mt-28 p-5 sm:p-8">
                    <h2 class="font-display text-2xl font-extrabold">{{ $p->text('upload_title') }}</h2>
                    @if ($p->text('upload_subtitle'))<p class="mt-1 text-muted">{{ $p->text('upload_subtitle') }}</p>@endif

                    <label class="mt-5 flex cursor-pointer items-start gap-3 rounded-2xl bg-surface px-4 py-4 text-sm leading-relaxed has-checked:bg-primary-soft">
                        <input type="checkbox" x-model="consent" class="mt-0.5 size-5 shrink-0 accent-primary">
                        <span>{{ $p->text('consent_text') }}</span>
                    </label>

                    <label class="print-drop group mt-5 flex cursor-pointer flex-col items-center gap-3 rounded-3xl border-2 border-dashed px-6 py-12 text-center transition sm:py-14"
                           x-bind:class="dropClass" x-on:dragover="dragOver" x-on:dragleave="dragLeave" x-on:drop="drop">
                        <span class="print-drop-icon flex size-20 items-center justify-center rounded-3xl text-white" aria-hidden="true"><x-icon name="upload" class="size-9" /></span>
                        <span class="mt-2 font-display text-xl font-bold text-ink">{{ $p->text('drop_title') }}</span>
                        <span class="text-sm text-muted"><span class="font-semibold text-primary underline decoration-2 underline-offset-4">{{ $p->text('drop_pick') }}</span> · {{ $print['max_mb'] }} MB</span>
                        <span class="mt-2 flex flex-wrap justify-center gap-2">
                            @foreach ($formats as [$fmt, $cls])<span class="rounded-full px-3 py-1 text-xs font-bold {{ $cls }}">{{ $fmt }}</span>@endforeach
                        </span>
                        <input type="file" multiple accept="{{ $accept }}" class="sr-only" x-on:change="pick">
                    </label>
                    @if ($p->text('consent_hint'))<p x-show="noConsent" class="mt-3 text-center text-sm text-muted">{{ $p->text('consent_hint') }}</p>@endif
                    <p x-show="hasMessage" x-cloak x-text="message" class="mt-4 rounded-xl bg-danger-soft px-4 py-3 text-sm font-medium text-danger" role="alert"></p>
                    @if ($p->text('tip_text'))
                        <p class="mt-5 flex gap-3 rounded-2xl bg-accent-soft px-4 py-3 text-sm leading-relaxed text-accent-ink"><x-icon name="alert" class="mt-0.5 size-4 shrink-0" />{{ $p->text('tip_text') }}</p>
                    @endif
                </section>

                <template x-for="f in files" x-bind:key="f.uid">
                    <article class="card overflow-hidden" x-bind:data-uid="f.uid">
                        <div class="flex items-center gap-4 border-b border-line p-4 sm:p-5">
                            <span class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-surface">
                                <img x-show="f.preview" x-bind:src="f.preview" alt="" class="max-h-full max-w-full object-contain">
                                <span x-show="f.isBusy" class="size-6 animate-spin rounded-full border-2 border-primary border-t-transparent"></span>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="flex items-center gap-2">
                                    <span class="rounded px-1.5 py-0.5 text-[10px] font-bold ring-1" x-bind:class="f.extClass" x-text="f.extLabel"></span>
                                    <span class="truncate font-semibold" x-text="f.name"></span>
                                </p>
                                <p class="mt-0.5 text-xs text-muted"><span x-text="f.sizeText"></span> · <span x-text="f.statusText"></span></p>
                                <div x-show="f.isUploading" class="mt-2 h-1.5 overflow-hidden rounded-full bg-surface"><div class="h-full rounded-full bg-primary transition-all" x-bind:style="f.progressStyle"></div></div>
                            </div>
                            <button type="button" x-on:click="remove" class="rounded-lg p-2 text-muted hover:bg-danger-soft hover:text-danger" aria-label="Belgeyi kaldır"><x-icon name="trash" class="size-4" /></button>
                        </div>

                        <p x-show="f.hasError" x-text="f.error" class="mx-4 mt-4 rounded-xl bg-danger-soft px-4 py-3 text-sm text-danger sm:mx-5"></p>

                        <div x-show="f.isReady" class="grid gap-5 p-4 sm:p-5 xl:grid-cols-[1fr_260px]">
                            <div class="space-y-4">
                                @if (count($colors) > 1)
                                    <div>
                                        <p class="mb-1.5 text-xs font-semibold text-muted uppercase">Renk</p>
                                        <div class="grid grid-cols-2 gap-2">
                                            @foreach ($colors as $c)
                                                <button type="button" data-key="color" data-value="{{ $c }}" x-on:click="choose" x-bind:class="f.cls.color_{{ $c }}" class="rounded-xl border px-3 py-2.5 text-sm font-semibold transition">{{ PrintOptions::COLORS[$c] }}</button>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    {{-- Tek baskı türü açık (ör. renkli yazıcı arızası): seçim yerine ortalı bilgi rozeti --}}
                                    <div>
                                        <p class="mb-1.5 text-xs font-semibold text-muted uppercase">Renk</p>
                                        <div class="flex justify-center">
                                            <span class="inline-flex items-center gap-2 rounded-xl border border-primary bg-primary-soft px-6 py-2.5 text-sm font-semibold text-primary">
                                                <x-icon name="check" class="size-4" />{{ PrintOptions::COLORS[$colors[0] ?? 'bw'] }} baskı
                                            </span>
                                        </div>
                                    </div>
                                @endif
                                <div>
                                    <p class="mb-1.5 text-xs font-semibold text-muted uppercase">Baskı düzeni</p>
                                    <div class="grid grid-cols-2 gap-2">
                                        @foreach (PrintOptions::LAYOUTS as $l => $label)
                                            <button type="button" data-key="layout" data-value="{{ $l }}" x-on:click="choose" x-bind:class="f.cls.layout_{{ $l }}" class="flex flex-col items-center gap-1.5 rounded-xl border px-3 py-3 text-center transition">
                                                {{-- Kâğıt simgesi: ön ve arka yüz, her yüzde $l sayfa --}}
                                                <span class="flex gap-1" aria-hidden="true">
                                                    @foreach ([0, 1] as $side)
                                                        <span class="flex h-9 w-7 gap-0.5 rounded-sm border border-current/40 bg-white p-0.5">
                                                            @for ($i = 0; $i < $l; $i++)<span class="flex-1 rounded-[1px] bg-current/25"></span>@endfor
                                                        </span>
                                                    @endforeach
                                                </span>
                                                <span class="text-sm font-semibold">{{ $label }}</span>
                                                <span class="text-xs leading-snug opacity-75">{{ PrintOptions::LAYOUT_HINTS[$l] }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    @if ($print['a3_enabled'])
                                        <div>
                                            <p class="mb-1.5 text-xs font-semibold text-muted uppercase">Kağıt</p>
                                            <div class="grid grid-cols-2 gap-2">
                                                @foreach (PrintOptions::SIZES as $size)
                                                    <button type="button" data-key="size" data-value="{{ $size }}" x-on:click="choose" x-bind:class="f.cls.size_{{ $size }}" class="rounded-xl border px-3 py-2.5 text-sm font-semibold transition">{{ $size }}</button>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                    <div>
                                        <p class="mb-1.5 text-xs font-semibold text-muted uppercase">Cilt</p>
                                        <div class="grid grid-cols-2 gap-2">
                                            <button type="button" data-key="binding" data-value="none" x-on:click="choose" x-bind:class="f.cls.binding_none" class="rounded-xl border px-3 py-2.5 text-sm font-semibold transition">Ciltsiz</button>
                                            <button type="button" data-key="binding" data-value="spiral" x-on:click="choose" x-bind:class="f.cls.binding_spiral" class="rounded-xl border px-3 py-2.5 text-sm font-semibold transition">Spiral</button>
                                        </div>
                                    </div>
                                    <div>
                                        <p class="mb-1.5 text-xs font-semibold text-muted uppercase">Kopya</p>
                                        <div class="flex items-center gap-2">
                                            <button type="button" data-delta="-1" x-on:click="step" class="flex size-10 items-center justify-center rounded-xl border border-line hover:border-primary" aria-label="Azalt"><x-icon name="minus" class="size-4" /></button>
                                            <input type="number" min="1" max="{{ $print['max_copies'] }}" data-key="copies" x-bind:value="f.opts.copies" x-on:change="setText" class="field-input w-20 text-center" aria-label="Kopya">
                                            <button type="button" data-delta="1" x-on:click="step" class="flex size-10 items-center justify-center rounded-xl border border-line hover:border-primary" aria-label="Artır"><x-icon name="plus" class="size-4" /></button>
                                        </div>
                                    </div>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <label class="block"><span class="mb-1.5 block text-xs font-semibold text-muted uppercase">Sayfa aralığı</span>
                                        <input type="text" data-key="range" x-bind:value="f.opts.range" x-on:change="setText" placeholder="Tümü (ör. 1-10, 15)" class="field-input"></label>
                                    <label class="block"><span class="mb-1.5 block text-xs font-semibold text-muted uppercase">Not</span>
                                        <input type="text" data-key="note" x-bind:value="f.opts.note" x-on:change="setText" maxlength="300" placeholder="İsteğe bağlı" class="field-input"></label>
                                </div>
                            </div>

                            <div class="flex flex-col rounded-2xl bg-surface p-4">
                                <div x-show="f.hasTotal" class="flex flex-1 flex-col">
                                    <ul class="space-y-1 text-xs text-muted">
                                        <template x-for="l in f.lineItems" x-bind:key="l.key">
                                            <li class="flex justify-between gap-3"><span x-text="l.label"></span><span class="whitespace-nowrap" x-text="l.amount"></span></li>
                                        </template>
                                    </ul>
                                    <p class="mt-3 flex items-end justify-between border-t border-line pt-3"><span class="text-sm font-semibold">Tutar</span><span class="font-display text-2xl font-extrabold" x-text="f.total"></span></p>
                                    <button type="button" x-on:click="addToCart" x-bind:disabled="f.cannotAdd" class="btn-primary mt-4 w-full py-3"><x-icon name="cart" class="size-5" /><span x-text="f.addLabel"></span></button>
                                    <a x-show="f.added" x-bind:href="cartUrl" class="mt-2 text-center text-sm font-semibold text-primary hover:underline">Sepete git</a>
                                </div>
                            </div>
                        </div>
                    </article>
                </template>

                <div x-show="hasAdded" x-cloak class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-success-soft px-5 py-4 text-success">
                    <p class="font-semibold"><span x-text="addedCount"></span> belge sepette.</p>
                    <a x-bind:href="cartUrl" class="btn-primary">Sepete git <x-icon name="chevron-right" class="size-4" /></a>
                </div>
            </div>

            <aside id="fiyatlar" class="scroll-mt-28 space-y-4 lg:sticky lg:top-24">
                <section class="card overflow-hidden">
                    <div class="p-5 pb-4">
                        <h2 class="font-display text-xl font-extrabold">{{ $p->text('price_title') }}</h2>
                        @if ($p->text('price_subtitle'))<p class="mt-1 text-sm text-muted">{{ $p->text('price_subtitle') }}</p>@endif
                    </div>
                    <table class="w-full text-sm">
                        <thead><tr class="border-y border-line bg-surface text-left text-[11px] tracking-wider text-muted uppercase">
                            <th class="px-5 py-2.5 font-semibold">{{ $tiers->count() ? 'Yaprak' : '' }}</th>
                            @foreach ($colors as $c)<th class="px-5 py-2.5 text-right font-semibold">{{ PrintOptions::COLORS[$c] }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach ($tiers as $i => $t)
                                @php($best = $i === $tiers->count() - 1 && $tiers->count() > 1)
                                <tr class="border-b border-line {{ $best ? 'bg-accent-soft/60' : '' }}">
                                    <td class="px-5 py-3 font-semibold">{{ $tierRange($i) }}
                                        @if ($best && $p->text('price_best_label'))<span class="ml-1 rounded-full bg-accent px-2 py-0.5 text-[10px] font-bold text-ink">{{ $p->text('price_best_label') }}</span>@endif
                                    </td>
                                    @foreach ($colors as $c)<td class="px-5 py-3 text-right font-semibold whitespace-nowrap">{{ Money::format($t[$c]) }}</td>@endforeach
                                </tr>
                            @endforeach
                            <tr class="border-b border-line"><td class="px-5 py-3 text-muted">Spiral cilt <span class="text-xs">(kopya başına)</span></td>
                                <td colspan="{{ count($colors) }}" class="px-5 py-3 text-right font-display text-base font-extrabold">{{ Money::format($print['spiral_price']) }}</td></tr>
                        </tbody>
                    </table>
                    <ul class="space-y-1 p-5 pt-4 text-xs leading-relaxed text-muted">
                        <li>Kademe yaprak sayısına göre seçilir. Önlü arkalı baskıda yaprak sayısı yarıya iner (100 sayfa = 50 yaprak).</li>
                        @if ($print['a3_enabled'])<li>A3 baskı: yaprak fiyatı × {{ str_replace('.', ',', rtrim(rtrim($print['a3_multiplier'], '0'), '.')) }}.</li>@endif
                    </ul>
                </section>
                <section class="card space-y-4 p-5 text-sm">
                    @foreach ([['clock', 'bg-accent-soft text-accent-ink', $print['prep_text']], ['truck', 'bg-info-soft text-info', $settings['delivery_info']], ['shield', 'bg-success-soft text-success', 'Belgeleriniz yalnız baskı için kullanılır ve '.$print['ordered_days'].' gün sonra sunucudan silinir.']] as [$icon, $cls, $text])
                        @if ($text)
                            <p class="flex items-center gap-3"><span class="flex size-10 shrink-0 items-center justify-center rounded-xl {{ $cls }}"><x-icon :name="$icon" class="size-5" /></span><span>{{ $text }}</span></p>
                        @endif
                    @endforeach
                </section>
            </aside>
        </div>
    </div>
</x-layouts.store>
