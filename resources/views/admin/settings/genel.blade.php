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
        <x-field name="search_placeholder" label="Arama kutusu yazısı" :value="$s['search_placeholder']" help="Header'daki ve ana sayfadaki arama kutusu. Arama ürün, kategori, yayınevi, yazar, bilgi sayfaları, SSS ve site bölümlerini kapsar." />
        <fieldset class="space-y-3 rounded-xl border border-line p-4">
            <legend class="px-1 text-sm font-semibold">Sepet sayfası</legend>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="cart_empty_title" label="Boş sepet başlığı" :value="$s['cart_empty_title']" />
                <x-field name="cart_suggest_title" label="Öneri satırı başlığı" :value="$s['cart_suggest_title']" help="Sepetin altındaki ürün önerileri." />
                <x-field name="cart_print_title" label="Belge ekleme kartı başlığı" :value="$s['cart_print_title']" />
                <x-field name="cart_print_text" label="Belge ekleme kartı açıklaması" :value="$s['cart_print_text']" />
                <x-field name="cart_payment_title" label="Ödeme seçenekleri başlığı" :value="$s['cart_payment_title']" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="cart_steps" type="textarea" rows="3" label="Sipariş adımları" :value="$s['cart_steps']" help="Başlığın yanındaki adım göstergesi; her satır bir adım. Boşsa gizlenir." />
                <x-field name="cart_trust" type="textarea" rows="3" label="Güven rozetleri" :value="$s['cart_trust']" help="Özetin altında. Her satır: simge|Başlık|Açıklama. Boşsa gizlenir." />
            </div>
        </fieldset>
        <fieldset class="space-y-3 rounded-xl border border-line p-4">
            <legend class="px-1 text-sm font-semibold">Sipariş onayı sayfası</legend>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="done_title" label="Başlık" :value="$s['done_title']" />
                <x-field name="done_text" label="Başlık altı yazı" :value="$s['done_text']" />
            </div>
            <x-field name="done_note" label="Takip notu" :value="$s['done_note']" help="E-posta bilgisinin yanında görünür." />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="done_help_title" label="WhatsApp kartı başlığı" :value="$s['done_help_title']" />
                <x-field name="done_help_text" label="WhatsApp kartı yazısı" :value="$s['done_help_text']" help="WhatsApp numarası girilmişse görünür." />
            </div>
        </fieldset>
        <fieldset class="space-y-3 rounded-xl border border-line p-4">
            <legend class="px-1 text-sm font-semibold">Giriş ve üyelik paneli</legend>
            <p class="field-help">Giriş yap, üye ol ve parola sayfalarındaki 3D kilitli koyu panel.</p>
            <div class="grid gap-5 sm:grid-cols-3">
                <x-field name="auth_panel_kicker" label="Üst etiket" :value="$s['auth_panel_kicker']" />
                <x-field name="auth_panel_title" label="Başlık" :value="$s['auth_panel_title']" />
                <x-field name="auth_panel_accent" label="Başlığın renkli kısmı" :value="$s['auth_panel_accent']" />
            </div>
            <x-field name="auth_panel_text" label="Açıklama" :value="$s['auth_panel_text']" />
            <x-field name="auth_panel_points" type="textarea" rows="3" label="Avantajlar" :value="$s['auth_panel_points']"
                     help="Geniş ekranda panelin altında. Her satır: simge|Başlık|Açıklama (ör. truck|Kapınıza teslim|Eskişehir içi). Boşsa gizlenir." />
        </fieldset>
        <div class="flex justify-end"><button class="btn-primary">Kaydet</button></div>
    </form>
</x-layouts.admin>
