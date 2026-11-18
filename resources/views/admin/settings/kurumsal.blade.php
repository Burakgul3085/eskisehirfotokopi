<x-layouts.admin title="Ayarlar · Kurumsal sayfalar">
    @include('admin.settings._layout')
    <form method="post" action="{{ route('admin.settings.update', $group) }}" enctype="multipart/form-data" class="card max-w-4xl space-y-6 p-6 sm:p-8">
        @csrf @method('put')
        <p class="text-sm text-muted">Sayfaların metinleri <a href="{{ route('admin.pages.index') }}" class="font-semibold text-primary hover:underline">Vitrin › Sayfalar</a> bölümünden düzenlenir. Burada Hakkımızda sayfasının bölümleri ve tüm bilgi sayfalarında ortak görünen yazılar var. Boş bırakılan bölüm gösterilmez.</p>

        <fieldset class="space-y-4 rounded-xl border border-line p-4">
            <legend class="px-1 text-sm font-semibold">Hakkımızda › Üst bölüm</legend>
            <x-field name="about_title" label="Başlık" :value="$s['about_title']" />
            <x-field name="about_lead" type="textarea" rows="2" label="Kısa tanıtım" :value="$s['about_lead']" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="about_primary_button" label="Birinci buton (ürünlere gider)" :value="$s['about_primary_button']" />
                <x-field name="about_secondary_button" label="İkinci buton (iletişime gider)" :value="$s['about_secondary_button']" />
            </div>
            <div class="grid gap-4 sm:grid-cols-[1fr_1fr]">
                <div x-data="imagePreview" data-current="{{ \App\Support\Settings::fileUrl('about_image_path') }}">
                    <span class="field-label">Mağaza fotoğrafı</span>
                    <div class="flex h-32 items-center justify-center rounded-xl border border-dashed border-line bg-surface p-2">
                        <img x-show="hasImage" x-bind:src="url" alt="" class="max-h-full max-w-full rounded-lg object-contain">
                        <span x-show="noImage" class="text-xs text-muted">Görsel yok (yerine marka işareti gösterilir)</span>
                    </div>
                    <input type="file" name="about_image" accept="image/*" x-on:change="pick" class="mt-3 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-primary-soft file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-primary-dark">
                    @if ($s['about_image_path'])
                        <label class="mt-2 flex items-center gap-2 text-xs text-muted"><input type="checkbox" name="remove_about_image" value="1" class="accent-danger"> Mevcut görseli kaldır</label>
                    @endif
                    @error('about_image')<p class="field-error">{{ $message }}</p>@enderror
                    <p class="field-help">Yatay JPG/PNG/WebP, en fazla 4 MB.</p>
                </div>
                <div class="space-y-4">
                    <x-field name="about_founded" type="number" label="Kuruluş yılı" :value="$s['about_founded']" placeholder="2016" help="Girilirse “N yıldır hizmetinizde” rozeti ve yıl sayacı görünür." />
                    <x-field name="about_badge" label="Rozet yazısı" :value="$s['about_badge']" />
                </div>
            </div>
        </fieldset>

        <fieldset class="space-y-4 rounded-xl border border-line p-4">
            <legend class="px-1 text-sm font-semibold">Hakkımızda › Rakamlar</legend>
            <p class="field-help">Ürün, yayınevi ve teslimat ilçesi sayıları sitedeki gerçek verilerden hesaplanır. Etiketi boş bırakılan rakam gizlenir.</p>
            <div class="grid gap-4 sm:grid-cols-4">
                <x-field name="about_stat_products" label="Ürün" :value="$s['about_stat_products']" />
                <x-field name="about_stat_publishers" label="Yayınevi" :value="$s['about_stat_publishers']" />
                <x-field name="about_stat_districts" label="Teslimat ilçesi" :value="$s['about_stat_districts']" />
                <x-field name="about_stat_years" label="Yıl" :value="$s['about_stat_years']" />
            </div>
        </fieldset>

        <fieldset class="space-y-4 rounded-xl border border-line p-4">
            <legend class="px-1 text-sm font-semibold">Hakkımızda › Bölümler</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="about_story_kicker" label="Hikâye: üst yazı" :value="$s['about_story_kicker']" />
                <x-field name="about_story_title" label="Hikâye: başlık" :value="$s['about_story_title']" help="Metni, Sayfalar › Hakkımızda içeriğidir." />
                <x-field name="about_values_kicker" label="Değerler: üst yazı" :value="$s['about_values_kicker']" />
                <x-field name="about_values_title" label="Değerler: başlık" :value="$s['about_values_title']" />
            </div>
            <x-field name="about_values" type="textarea" rows="3" label="Değer kartları" :value="$s['about_values']" help="Her satır: simge|Başlık|Açıklama. Simgeler: target, eye, heart, star, shield, book, truck, printer, cash, users, chat, home." />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="about_features_kicker" label="Farkımız: üst yazı" :value="$s['about_features_kicker']" />
                <x-field name="about_features_title" label="Farkımız: başlık" :value="$s['about_features_title']" />
            </div>
            <x-field name="about_features" type="textarea" rows="4" label="Farkımız kartları" :value="$s['about_features']" help="Her satır: simge|Başlık|Açıklama." />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="about_milestones_kicker" label="Zaman çizelgesi: üst yazı" :value="$s['about_milestones_kicker']" />
                <x-field name="about_milestones_title" label="Zaman çizelgesi: başlık" :value="$s['about_milestones_title']" />
            </div>
            <x-field name="about_milestones" type="textarea" rows="3" label="Kilometre taşları" :value="$s['about_milestones']" placeholder="2016|Mağazamızı açtık|Odunpazarı'nda ilk mağazamızla kapılarımızı açtık." help="Her satır: yıl|Başlık|Açıklama. Boşsa bölüm gösterilmez." />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="about_cta_title" label="Alt çağrı: başlık" :value="$s['about_cta_title']" />
                <x-field name="about_cta_text" label="Alt çağrı: metin" :value="$s['about_cta_text']" />
            </div>
        </fieldset>

        <fieldset class="space-y-4 rounded-xl border border-line p-4">
            <legend class="px-1 text-sm font-semibold">Yasal metinler, yardım ve SSS sayfaları</legend>
            <div>
                <span class="field-label">İletişim bilgileri</span>
                <div class="flex flex-wrap gap-3">
                    @foreach (['0' => 'Gizle (yalnız İletişim sayfası bağlantısı)', '1' => 'Telefon, e-posta ve adresi göster'] as $v => $l)
                        <label class="flex items-center gap-2 rounded-xl border border-line px-4 py-2.5 text-sm has-checked:border-primary has-checked:bg-primary-soft">
                            <input type="radio" name="pages_show_contact" value="{{ $v }}" @checked(old('pages_show_contact', $s['pages_show_contact']) === (string) $v) class="accent-primary"> {{ $l }}
                        </label>
                    @endforeach
                </div>
                <p class="field-help">Hakkımızda, yasal metinler, Teslimat ve İade ve SSS sayfalarındaki yardım kartı ile metin içindeki {telefon}, {eposta}, {adres} alanları. Gizliyken bu alanların yerine aşağıdaki yazı çıkar; yazdırılan çıktıda da görünmez.</p>
            </div>
            <x-field name="pages_contact_placeholder" label="Gizliyken {telefon}/{eposta}/{adres} yerine yazılan" :value="$s['pages_contact_placeholder']" />
            <div class="grid gap-4 sm:grid-cols-3">
                <x-field name="pages_effective" label="Çıktıda “Yürürlük tarihi”" :value="$s['pages_effective']" />
                <x-field name="pages_copy_link" label="Bağlantı kopyalama butonu" :value="$s['pages_copy_link']" />
                <x-field name="pages_copied" label="Kopyalandı yazısı" :value="$s['pages_copied']" />
            </div>
            <x-field name="pages_print_note" type="textarea" rows="2" label="Çıktının altındaki not" :value="$s['pages_print_note']" help=":site, :date ve :url yerine site adı, çıktı tarihi ve sayfa adresi yazılır." />
            <div class="grid gap-4 sm:grid-cols-3">
                <x-field name="pages_updated" label="“Son güncelleme” yazısı" :value="$s['pages_updated']" />
                <x-field name="pages_toc_title" label="İçindekiler başlığı" :value="$s['pages_toc_title']" />
                <x-field name="pages_print" label="Yazdır butonu" :value="$s['pages_print']" />
                <x-field name="pages_legal_title" label="Yasal metinler menüsü" :value="$s['pages_legal_title']" />
                <x-field name="pages_help_nav_title" label="Yardım menüsü" :value="$s['pages_help_nav_title']" />
                <x-field name="pages_contact_link" label="İletişim bağlantısı" :value="$s['pages_contact_link']" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="pages_help_title" label="Yardım kartı başlığı" :value="$s['pages_help_title']" />
                <x-field name="pages_help_text" label="Yardım kartı metni" :value="$s['pages_help_text']" />
                <x-field name="faq_search_placeholder" label="SSS arama kutusu" :value="$s['faq_search_placeholder']" />
                <x-field name="faq_empty" label="SSS: sonuç yok" :value="$s['faq_empty']" />
                <x-field name="faq_more_title" label="SSS alt kart başlığı" :value="$s['faq_more_title']" />
                <x-field name="faq_more_text" label="SSS alt kart metni" :value="$s['faq_more_text']" />
                <x-field name="delivery_card_home" label="Teslimat sayfası: eve teslim kartı" :value="$s['delivery_card_home']" />
                <x-field name="delivery_card_home_text" label="Eve teslim kartı açıklaması" :value="$s['delivery_card_home_text']" />
                <x-field name="delivery_card_pay" label="Teslimat sayfası: ödeme kartı" :value="$s['delivery_card_pay']" />
                <x-field name="delivery_card_pay_text" label="Ödeme kartı açıklaması" :value="$s['delivery_card_pay_text']" />
            </div>
            <p class="field-help">SSS sayfasında “# Grup adı” satırı soru grubu, “## Soru” satırı soru başlatır; altına cevap yazılır.</p>
        </fieldset>

        <button class="btn-primary">Kaydet</button>
    </form>
</x-layouts.admin>
