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
                 help="“Tüm kategoriler” düğmesinin yanındaki bağlantılar (telefonda menüde). Her satır: Etiket|/adres (ör. Yayınevleri|/yayinevleri)." />
        <fieldset class="space-y-3 rounded-xl border border-line p-4">
            <legend class="px-1 text-sm font-semibold">Üst bant ve header</legend>
            <x-field name="topbar_items" type="textarea" rows="5" label="Kayan üst bant" :value="$s['topbar_items']"
                     help="Header'ın üstünde kayan şerit. Her satır: simge|Metin|ROZET (ör. truck|Eskişehir içi|KAPIYA TESLİM). Simgeler: truck, cash, card, printer, book, shield, whatsapp, phone, clock, package, check. Boş bırakılırsa bant gizlenir; duyuru yazısı varsa başa eklenir." />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="header_welcome" label="Giriş düğmesi üst yazısı" :value="$s['header_welcome']" />
                <x-field name="header_support_label" label="WhatsApp destek düğmesi üst yazısı" :value="$s['header_support_label']" help="WhatsApp numarası girilmişse menü satırının sağında görünür." />
            </div>
        </fieldset>
        <fieldset class="space-y-3 rounded-xl border border-line p-4">
            <legend class="px-1 text-sm font-semibold">Kategori menüsü</legend>
            <p class="field-help">İçinde yayında ürün olmayan kategoriler vitrinde gösterilmez; 1 ürün bile yayına alınınca kendiliğinden görünür.</p>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="menu_all_label" label="Düğme yazısı" :value="$s['menu_all_label']" />
                <x-field name="menu_search_placeholder" label="Arama kutusu yazısı" :value="$s['menu_search_placeholder']" />
                <x-field name="menu_see_all" label="“Tümünü gör” düğmesi" :value="$s['menu_see_all']" />
                <x-field name="menu_no_match" label="Arama sonucu yoksa" :value="$s['menu_no_match']" />
            </div>
            <x-field name="category_empty" label="Boş kategori sayfasındaki yazı" :value="$s['category_empty']" help="Ürünü olmayan kategorinin adresi doğrudan açılırsa gösterilir." />
        </fieldset>
        <fieldset class="space-y-3 rounded-xl border border-line p-4">
            <legend class="px-1 text-sm font-semibold">Yayınevleri sayfası</legend>
            <p class="field-help">Öne çıkan yayınevlerini Yönetim › Yayınevleri'nden “Öne çıkan” işaretiyle seçin; hiçbiri seçili değilse en çok ürünü olanlar gösterilir.</p>
            <div class="grid gap-5 sm:grid-cols-3">
                <x-field name="publishers_kicker" label="Üst etiket" :value="$s['publishers_kicker']" />
                <x-field name="publishers_title" label="Başlık (1. satır)" :value="$s['publishers_title']" />
                <x-field name="publishers_title_accent" label="Başlık (2. satır, renkli)" :value="$s['publishers_title_accent']" />
            </div>
            <x-field name="publishers_intro" type="textarea" rows="2" label="Tanıtım metni" :value="$s['publishers_intro']" />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="publishers_featured" label="Öne çıkanlar başlığı" :value="$s['publishers_featured']" />
                <x-field name="publishers_all" label="Tam liste başlığı" :value="$s['publishers_all']" />
                <x-field name="publishers_all_sub" label="Tam liste açıklaması" :value="$s['publishers_all_sub']" />
                <x-field name="publishers_search" label="Arama kutusu yazısı" :value="$s['publishers_search']" />
            </div>
            <x-field name="publishers_no_match" label="Arama sonucu yoksa" :value="$s['publishers_no_match']" />
        </fieldset>
        <fieldset class="space-y-3 rounded-xl border border-line p-4">
            <legend class="px-1 text-sm font-semibold">Sipariş takibi sayfası</legend>
            <div class="grid gap-5 sm:grid-cols-3">
                <x-field name="track_kicker" label="Üst etiket" :value="$s['track_kicker']" />
                <x-field name="track_title" label="Başlık (1. satır)" :value="$s['track_title']" />
                <x-field name="track_title_accent" label="Başlık (2. satır, renkli)" :value="$s['track_title_accent']" />
            </div>
            <x-field name="track_intro" type="textarea" rows="2" label="Tanıtım metni" :value="$s['track_intro']" />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="track_form_title" label="Form başlığı" :value="$s['track_form_title']" />
                <x-field name="track_button" label="Düğme yazısı" :value="$s['track_button']" />
            </div>
            <x-field name="track_points" type="textarea" rows="3" label="Maddeler" :value="$s['track_points']"
                     help="Her satır: simge|Başlık|Açıklama. Simgeler: shield, mail, clock, truck, phone, check, package." />
        </fieldset>
        <x-field name="trust_items" type="textarea" rows="4" label="Güven şeridi" :value="$s['trust_items']"
                 help="Sayfa altındaki şerit. Her satır: simge|Başlık|Açıklama. Simgeler: truck, cash, printer, shield, clock, book, phone, check." />
        <x-field name="footer_about" type="textarea" rows="4" label="Alt bilgi metni" :value="$s['footer_about']" />
        <x-field name="copyright" label="Telif satırı" :value="$s['copyright']" help="Boş bırakılırsa “© yıl site adı” yazılır." />
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="footer_title_corporate" label="Alt bilgi: Kurumsal sütun başlığı" :value="$s['footer_title_corporate']" />
            <x-field name="footer_title_help" label="Alt bilgi: Yardım sütun başlığı" :value="$s['footer_title_help']" />
        </div>
        <x-field name="cookie_notice" label="Çerez bildirimi" :value="$s['cookie_notice']" help="İlk ziyarette altta gösterilir; Çerez Politikası sayfasına bağlanır. Boşsa gösterilmez." />
        <x-field name="checkout_consent" label="Sipariş onay kutusu metni" :value="$s['checkout_consent']" help="Altında Mesafeli Satış Sözleşmesi, KVKK ve Teslimat sayfalarının bağlantıları görünür." />
        <x-field name="register_consent" label="Üyelik onay kutusu metni" :value="$s['register_consent']" help="Altında Üyelik Koşulları ve KVKK sayfalarının bağlantıları görünür." />
        <div class="flex justify-end"><button class="btn-primary">Kaydet</button></div>
    </form>
</x-layouts.admin>
