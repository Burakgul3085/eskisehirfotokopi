<x-layouts.admin title="Ayarlar · Genel">
    @include('admin.settings._layout')
    <form method="post" action="{{ route('admin.settings.update', $group) }}" class="card max-w-3xl space-y-5 p-6 sm:p-8">
        @csrf @method('put')
        <x-field name="site_name" label="Site adı" :value="$s['site_name']" required />
        <x-field name="site_tagline" label="Slogan" :value="$s['site_tagline']" help="Ana sayfa başlığında ve arama sonuçlarında görünür." />
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="announcement" label="Duyuru şeridi" :value="$s['announcement']" help="Sayfanın en üstündeki şerit. Boş bırakılırsa gösterilmez." />
            <x-field name="announcement_link" type="url" label="Duyuru bağlantısı" :value="$s['announcement_link']" placeholder="https://" />
        </div>
        <fieldset>
            <legend class="field-label">Site durumu</legend>
            <div class="flex flex-wrap gap-3">
                @foreach (['1' => 'Açık', '0' => 'Bakımda (ziyaretçilere kapalı)'] as $v => $l)
                    <label class="flex items-center gap-2 rounded-xl border border-line px-4 py-2.5 text-sm has-checked:border-primary has-checked:bg-primary-soft">
                        <input type="radio" name="store_open" value="{{ $v }}" @checked(old('store_open', $s['store_open']) === (string) $v) class="accent-primary"> {{ $l }}
                    </label>
                @endforeach
            </div>
            <p class="field-help">Bakımdayken yönetici olarak giriş yaptıysanız siteyi görmeye devam edersiniz.</p>
        </fieldset>
        <x-field name="closed_message" type="textarea" label="Bakım mesajı" :value="$s['closed_message']" />
        <x-field name="header_links" type="textarea" rows="4" label="Menü bağlantıları" :value="$s['header_links']"
                 help="Kategori menüsünün sağındaki bağlantılar. Her satır: Etiket|/adres (ör. Yayınevleri|/yayinevleri)." />
        <x-field name="trust_items" type="textarea" rows="4" label="Güven şeridi" :value="$s['trust_items']"
                 help="Sayfa altındaki şerit. Her satır: simge|Başlık|Açıklama. Simgeler: truck, cash, printer, shield, clock, book, phone, check." />
        <x-field name="footer_about" type="textarea" rows="4" label="Alt bilgi metni" :value="$s['footer_about']" />
        <x-field name="copyright" label="Telif satırı" :value="$s['copyright']" help="Boş bırakılırsa “© yıl site adı” yazılır." />
        <div class="flex justify-end"><button class="btn-primary">Kaydet</button></div>
    </form>
</x-layouts.admin>
