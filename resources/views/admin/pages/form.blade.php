@php use App\Models\Page; @endphp
<x-layouts.admin :title="$page->exists ? $page->title : 'Yeni sayfa'">
    <x-slot:actions>
        @if ($page->exists)<a href="{{ $page->url() }}" target="_blank" class="btn-ghost btn-sm"><x-icon name="external" class="size-4" />Sayfayı aç</a>@endif
        <a href="{{ route('admin.pages.index') }}" class="btn-ghost btn-sm">Tüm sayfalar</a>
    </x-slot:actions>

    <x-flash class="mb-5" />
    <form method="post" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}" class="grid gap-6 xl:grid-cols-[1fr_20rem]">
        @csrf
        @if ($page->exists) @method('put') @endif
        <div class="card space-y-5 p-6 sm:p-8">
            <x-field name="title" label="Başlık" :value="$page->title" required />
            <x-field name="body" type="textarea" rows="22" label="İçerik" :value="$page->body" class="font-mono"
                     help="Biçim: ## Ara başlık, **kalın**, - madde, [bağlantı](https://…). Yer tutucular: {site_adi}, {telefon}, {eposta}, {adres} — ayarlardaki bilgilerle dolar." />
        </div>
        <aside class="space-y-4">
            <div class="card space-y-5 p-5">
                <fieldset>
                    <legend class="field-label">Durum</legend>
                    <div class="flex gap-3">
                        @foreach (['1' => 'Yayında', '0' => 'Gizli'] as $v => $l)
                            <label class="flex items-center gap-2 rounded-xl border border-line px-4 py-2.5 text-sm has-checked:border-primary has-checked:bg-primary-soft">
                                <input type="radio" name="is_active" value="{{ $v }}" @checked((string) old('is_active', (int) $page->is_active) === (string) $v) class="accent-primary"> {{ $l }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div>
                    <label for="f_footer_column" class="field-label">Alt bilgide göster</label>
                    <select id="f_footer_column" name="footer_column" class="field-input">
                        <option value="">Gösterme</option>
                        @foreach (Page::COLUMNS as $k => $l)<option value="{{ $k }}" @selected(old('footer_column', $page->footer_column) === $k)>{{ $l }} sütunu</option>@endforeach
                    </select>
                </div>
                <x-field name="position" type="number" label="Sıra" :value="$page->position ?? 0" min="0" max="999" />
                <x-field name="slug" label="Adres" :value="$page->slug" help="Boşsa başlıktan üretilir: /sayfa/…" />
                <x-field name="meta_description" type="textarea" rows="3" label="Arama motoru açıklaması" :value="$page->meta_description" maxlength="170" />
                <button class="btn-primary w-full">Kaydet</button>
            </div>
            @if ($missing = $page->missingPlaceholders())
                <p class="rounded-xl bg-accent-soft px-4 py-3 text-sm text-accent-ink">Bu sayfada kullanılan {{ implode(', ', $missing) }} bilgisi ayarlarda boş; sitede “—” görünür.
                    <a href="{{ route('admin.settings.edit', 'iletisim') }}" class="font-semibold underline">İletişim ayarlarını doldurun.</a></p>
            @endif
            @if ($page->isSystem())
                <p class="rounded-xl bg-info-soft px-4 py-3 text-sm text-info">Bu sayfa sistem sayfasıdır; formlardan bağlanır ve silinemez.</p>
            @endif
        </aside>
    </form>
</x-layouts.admin>
