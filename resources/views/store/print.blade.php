@php
    use App\Domain\Print\PrintOptions;
    use App\Support\Money;
    $tiers = collect($print['tiers'])->sortBy('from')->values();
    $tierLabel = fn ($i) => $tiers[$i]['from'].(isset($tiers[$i + 1]) ? '–'.($tiers[$i + 1]['from'] - 1) : '+').' yaprak';
@endphp
<x-layouts.store title="Fotokopi ve belge baskısı" :description="$print['intro']">
    <div class="container-page py-6 sm:py-10" x-data="printOrder">
        <script type="application/json" data-print-config>@json($config)</script>
        <x-breadcrumb :items="[['Fotokopi', null]]" />

        <div class="mt-4 grid items-start gap-6 lg:grid-cols-[1fr_340px]">
            <div class="min-w-0 space-y-6">
                <div>
                    <h1 class="text-2xl font-extrabold sm:text-3xl">Belgeni yükle, kapına getirelim</h1>
                    <p class="mt-2 max-w-2xl text-muted">{{ $print['intro'] }}</p>
                </div>

                <section class="card p-5 sm:p-7">
                    <label class="flex items-start gap-3 text-sm">
                        <input type="checkbox" x-model="consent" class="mt-0.5 accent-primary">
                        <span>Yüklediğim belgeleri çoğaltma hakkım olduğunu, telif hakkıyla korunan eserlerde yasal sınırlar içinde kaldığımı onaylıyorum.</span>
                    </label>

                    <label class="mt-5 flex cursor-pointer flex-col items-center gap-3 rounded-2xl border-2 border-dashed px-6 py-10 text-center transition"
                           x-bind:class="dropClass" x-on:dragover="dragOver" x-on:dragleave="dragLeave" x-on:drop="drop">
                        <span class="flex size-14 items-center justify-center rounded-2xl bg-primary-soft text-primary"><x-icon name="upload" class="size-7" /></span>
                        <span class="font-display text-lg font-bold">Dosyaları buraya bırakın veya seçin</span>
                        <span class="text-sm text-muted">PDF, Word, PowerPoint, Excel, JPG, PNG · en fazla {{ $print['max_mb'] }} MB · birden çok dosya seçebilirsiniz</span>
                        <input type="file" multiple accept="{{ $accept }}" class="sr-only" x-on:change="pick">
                    </label>
                    <p x-show="hasMessage" x-cloak x-text="message" class="mt-4 rounded-xl bg-danger-soft px-4 py-3 text-sm font-medium text-danger" role="alert"></p>
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

            <aside class="space-y-4 lg:sticky lg:top-24">
                <section class="card p-5">
                    <h2 class="font-bold">Fiyatlar (yaprak başı)</h2>
                    <table class="mt-3 w-full text-sm">
                        <thead><tr class="text-left text-xs text-muted"><th class="pb-2 font-medium">Yaprak</th>
                            @foreach ($colors as $c)<th class="pb-2 text-right font-medium">{{ PrintOptions::COLORS[$c] }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach ($tiers as $i => $t)
                                <tr class="border-t border-line"><td class="py-2">{{ $tierLabel($i) }}</td>
                                    @foreach ($colors as $c)<td class="py-2 text-right font-semibold">{{ Money::format($t[$c]) }}</td>@endforeach</tr>
                            @endforeach
                        </tbody>
                    </table>
                    <ul class="mt-3 space-y-1 text-xs text-muted">
                        <li>Kademe yaprak sayısına göre seçilir. Önlü arkalı baskıda yaprak sayısı yarıya iner (100 sayfa = 50 yaprak).</li>
                        <li>Spiral cilt: kopya başı {{ Money::format($print['spiral_price']) }}.</li>
                        @if ($print['a3_enabled'])<li>A3 baskı: yaprak fiyatı × {{ str_replace('.', ',', rtrim(rtrim($print['a3_multiplier'], '0'), '.')) }}.</li>@endif
                    </ul>
                </section>
                <section class="card space-y-3 p-5 text-sm">
                    <p class="flex gap-2"><x-icon name="clock" class="size-4 shrink-0 text-primary" />{{ $print['prep_text'] }}</p>
                    <p class="flex gap-2"><x-icon name="truck" class="size-4 shrink-0 text-primary" />{{ $settings['delivery_info'] }}</p>
                    <p class="flex gap-2"><x-icon name="shield" class="size-4 shrink-0 text-primary" />Belgeleriniz yalnız baskı için kullanılır ve {{ $print['ordered_days'] }} gün sonra sunucudan silinir.</p>
                </section>
            </aside>
        </div>
    </div>
</x-layouts.store>
