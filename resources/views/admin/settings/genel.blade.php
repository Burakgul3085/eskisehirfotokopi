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
        <x-field name="back_to_top_label" label="Yukarı çık butonu" :value="$s['back_to_top_label']" help="Sayfa aşağı kaydırılınca sol altta çıkan yuvarlak buton (ekran okuyucu ve ipucu metni). Boşsa buton gösterilmez." />
        <fieldset id="bulten-ayarlari" class="scroll-mt-24 space-y-3 rounded-xl border border-line p-4">
            <legend class="px-1 text-sm font-semibold">Alt bilgi: sütunlar, harita ve çalışma saatleri</legend>
            <p class="field-help">Harita ve yol tarifi Ayarlar › İletişim’deki Google Haritalar bağlantısından, saatler oradaki “Çalışma saatleri” alanından gelir (her satır: Gün 09:00–20:00 ya da Gün | Kapalı). Boş bırakılan başlığın kartı/sütunu gösterilmez.</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-field name="footer_title_account" label="Üyelik sütunu başlığı" :value="$s['footer_title_account']" />
                <x-field name="footer_title_shop" label="Alışveriş sütunu başlığı" :value="$s['footer_title_shop']" />
                <x-field name="footer_map_title" label="Harita kartı başlığı" :value="$s['footer_map_title']" />
                <x-field name="footer_directions" label="Yol tarifi bağlantısı" :value="$s['footer_directions']" />
                <x-field name="footer_hours_title" label="Çalışma saatleri başlığı" :value="$s['footer_hours_title']" />
                <x-field name="footer_contact_link" label="İletişim bağlantısı yazısı" :value="$s['footer_contact_link']" />
            </div>
        </fieldset>
        <fieldset class="space-y-3 rounded-xl border border-line p-4">
            <legend class="px-1 text-sm font-semibold">E-bülten</legend>
            <div class="flex flex-wrap gap-3">
                @foreach (['1' => 'Alt bilgide abonelik formu göster', '0' => 'Gizle'] as $v => $l)
                    <label class="flex items-center gap-2 rounded-xl border border-line px-4 py-2.5 text-sm has-checked:border-primary has-checked:bg-primary-soft">
                        <input type="radio" name="newsletter_enabled" value="{{ $v }}" @checked(old('newsletter_enabled', $s['newsletter_enabled']) === (string) $v) class="accent-primary"> {{ $l }}
                    </label>
                @endforeach
            </div>
            <p class="field-help">Aboneler ve toplu e-posta gönderimi: Müşteri › E-bülten.</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-field name="newsletter_kicker" label="Üst küçük yazı" :value="$s['newsletter_kicker']" />
                <x-field name="newsletter_title" label="Başlık" :value="$s['newsletter_title']" />
                <x-field name="newsletter_placeholder" label="E-posta kutusu yazısı" :value="$s['newsletter_placeholder']" />
                <x-field name="newsletter_button" label="Buton yazısı" :value="$s['newsletter_button']" />
            </div>
                <x-field name="newsletter_text" label="Açıklama" :value="$s['newsletter_text']" />
                <x-field name="newsletter_consent" type="textarea" rows="2" label="Onay kutusu metni" :value="$s['newsletter_consent']" help="Ticari elektronik ileti onayı. Yanına KVKK Aydınlatma Metni bağlantısı eklenir." />
                <x-field name="newsletter_thanks" label="Kayıt sonrası teşekkür mesajı" :value="$s['newsletter_thanks']" />
                <x-field name="newsletter_mail_footer" label="E-posta alt bilgisi" :value="$s['newsletter_mail_footer']" help="Toplu e-postaların en altında, abonelikten çıkış bağlantısının üstünde yazar." />
            <div class="grid gap-3 sm:grid-cols-2">
                <x-field name="newsletter_unsub_link" label="Abonelikten çık bağlantısı" :value="$s['newsletter_unsub_link']" />
                <x-field name="newsletter_unsub_title" label="Çıkış sayfası başlığı" :value="$s['newsletter_unsub_title']" />
                <x-field name="newsletter_unsub_button" label="Çıkış sayfası butonu" :value="$s['newsletter_unsub_button']" />
            </div>
                <x-field name="newsletter_unsub_text" type="textarea" rows="2" label="Çıkış sayfası sorusu" :value="$s['newsletter_unsub_text']" help=":email yerine abonenin adresi yazılır." />
                <x-field name="newsletter_unsub_done" type="textarea" rows="2" label="Çıkış sonrası mesaj" :value="$s['newsletter_unsub_done']" />
        </fieldset>
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
        <fieldset class="space-y-3 rounded-xl border border-line p-4">
            <legend class="px-1 text-sm font-semibold">Bulunamadı (404) sayfası</legend>
            <p class="field-help">Olmayan adreslerde ve hiçbir sonuç vermeyen aramalarda gösterilir (3D büyüteç sahneli).</p>
            <div class="grid gap-5 sm:grid-cols-3">
                <x-field name="notfound_kicker" label="Üst etiket" :value="$s['notfound_kicker']" />
                <x-field name="notfound_title" label="Başlık" :value="$s['notfound_title']" />
                <x-field name="notfound_accent" label="Başlığın renkli kısmı" :value="$s['notfound_accent']" />
            </div>
            <x-field name="notfound_text" label="Açıklama (olmayan adres)" :value="$s['notfound_text']" />
            <x-field name="notfound_search_text" label="Açıklama (sonuçsuz arama)" :value="$s['notfound_search_text']" help=":q yerine aranan kelime yazılır." />
        </fieldset>
        <fieldset class="space-y-3 rounded-xl border border-line p-4">
            <legend class="px-1 text-sm font-semibold">Hesabım sayfaları</legend>
            <p class="field-help">Üye girişinden sonra görülen sayfaların üst etiketi ve başlık altı açıklamaları. Boş bırakılan açıklama gizlenir.</p>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="account_kicker" label="Üst etiket" :value="$s['account_kicker']" />
                <x-field name="account_overview_text" label="Genel bakış" :value="$s['account_overview_text']" />
                <x-field name="account_orders_text" label="Siparişlerim" :value="$s['account_orders_text']" />
                <x-field name="account_addresses_text" label="Adreslerim" :value="$s['account_addresses_text']" />
                <x-field name="account_favorites_text" label="Favorilerim" :value="$s['account_favorites_text']" />
                <x-field name="account_messages_text" label="Mesajlarım" :value="$s['account_messages_text']" />
                <x-field name="account_settings_text" label="Bilgilerim ve parola" :value="$s['account_settings_text']" />
            </div>
        </fieldset>
        <div class="flex justify-end"><button class="btn-primary">Kaydet</button></div>
    </form>
</x-layouts.admin>
