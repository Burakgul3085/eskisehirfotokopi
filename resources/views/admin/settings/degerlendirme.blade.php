@php
    use App\Domain\Reviews\NoticeTemplates;
    // Ürün sayfası ve Hesabım metinleri: [başlık => [anahtar => etiket]]
    $sections = [
        'Sekmeler ve özet' => [
            'reviews_tab' => 'Değerlendirmeler sekmesi', 'questions_tab' => 'Soru-cevap sekmesi', 'reviews_summary_title' => 'Özet başlığı',
            'reviews_count' => 'Değerlendirme sayısı (:n)', 'reviews_empty' => 'Değerlendirme yokken', 'reviews_empty_sub' => 'Değerlendirme yokken alt yazı',
            'reviews_verified' => 'Doğrulanmış rozeti', 'reviews_store_reply' => 'Mağaza cevabı başlığı',
            'reviews_filter_all' => 'Süzgeç: tümü', 'reviews_filter_photos' => 'Süzgeç: fotoğraflı', 'reviews_sort_new' => 'Sıralama: en yeni',
            'reviews_sort_high' => 'Sıralama: en yüksek', 'reviews_sort_low' => 'Sıralama: en düşük', 'reviews_no_match' => 'Süzgece uyan yokken',
        ],
        'Değerlendirme formu' => [
            'reviews_write' => 'Yaz butonu', 'reviews_edit' => 'Düzenle butonu', 'reviews_delete' => 'Sil butonu', 'reviews_delete_confirm' => 'Silme onayı',
            'reviews_form_title' => 'Form başlığı', 'reviews_form_note' => 'Form notu', 'reviews_rating_label' => 'Puan etiketi',
            'reviews_rating_labels' => 'Yıldız anlamları (5 satır, 1→5)', 'reviews_title_label' => 'Başlık etiketi', 'reviews_title_placeholder' => 'Başlık örneği',
            'reviews_body_label' => 'Metin etiketi', 'reviews_body_placeholder' => 'Metin örneği', 'reviews_photos_label' => 'Fotoğraf etiketi',
            'reviews_photos_note' => 'Fotoğraf notu', 'reviews_photo_pick' => 'Fotoğraf seç butonu', 'reviews_photo_remove' => 'Fotoğrafı kaldır', 'reviews_photo_limit' => 'Fazla fotoğraf uyarısı (:n)', 'reviews_submit' => 'Gönder butonu',
        ],
        'Kim değerlendirebilir · durum yazıları' => [
            'reviews_login_note' => 'Giriş yapmamış ziyaretçiye', 'reviews_login_button' => 'Giriş butonu', 'reviews_yours' => 'Kendi değerlendirmesi başlığı', 'reviews_not_bought' => 'Ürünü almamış üyeye', 'reviews_not_delivered' => 'Siparişi henüz teslim edilmemiş üyeye',
            'reviews_pending_note' => 'Onay bekleyen değerlendirme', 'reviews_pending_edit_note' => 'Onay bekleyen düzenleme', 'reviews_rejected_note' => 'Reddedilen değerlendirme',
            'reviews_thanks' => 'Gönderince (onaylı yayında)', 'reviews_thanks_auto' => 'Gönderince (onaysız yayında)', 'reviews_deleted' => 'Silince',
        ],
        'Soru-cevap' => [
            'questions_title' => 'Bölüm başlığı', 'questions_count' => 'Soru sayısı (:n)', 'questions_empty' => 'Soru yokken', 'questions_empty_sub' => 'Soru yokken alt yazı',
            'questions_form_title' => 'Form başlığı', 'questions_form_note' => 'Form notu', 'questions_name_label' => 'Ad etiketi', 'questions_email_label' => 'E-posta etiketi', 'questions_kvkk_link' => 'KVKK bağlantı yazısı', 'questions_body_label' => 'Soru etiketi', 'questions_body_placeholder' => 'Soru örneği',
            'questions_consent' => 'Onay kutusu', 'questions_submit' => 'Gönder butonu', 'questions_thanks' => 'Gönderince', 'questions_answer_label' => 'Cevap başlığı',
        ],
        'Hesabım › Değerlendirmelerim' => [
            'account_reviews_title' => 'Sayfa başlığı', 'account_reviews_text' => 'Açıklama', 'account_reviews_waiting' => 'Bekleyen ürünler başlığı',
            'account_reviews_mine' => 'Yazdıklarım başlığı', 'account_reviews_empty' => 'Hiç yokken', 'reviews_rate_cta' => 'Değerlendir butonu (e-postada da)',
        ],
    ];
    $long = ['reviews_form_note', 'reviews_login_note', 'reviews_not_bought', 'reviews_not_delivered', 'reviews_pending_note', 'reviews_pending_edit_note',
        'reviews_rejected_note', 'reviews_photos_note', 'reviews_body_placeholder', 'questions_form_note', 'questions_consent', 'questions_empty_sub', 'reviews_empty_sub', 'account_reviews_empty'];
    $flag = fn (string $key) => old($key, $s[$key]) === '1';
@endphp
<x-layouts.admin title="Ayarlar · Değerlendirme ve sorular">
    @include('admin.settings._layout')
    <form method="post" action="{{ route('admin.settings.update', $group) }}" class="max-w-4xl space-y-6">
        @csrf @method('put')

        <div class="card space-y-4 p-6 sm:p-8">
            <h2 class="font-bold">Genel</h2>
            <p class="text-sm text-muted">Değerlendirmeyi yalnız ürünü kendi üye hesabıyla sipariş edip <strong>teslim almış</strong> müşteriler yazabilir; ürün başına bir değerlendirme (düzenlenebilir). Soruyu herkes sorabilir.
                Onay bekleyenler <a href="{{ route('admin.reviews.index') }}" class="font-semibold text-primary hover:underline">Değerlendirmeler</a> ve <a href="{{ route('admin.questions.index') }}" class="font-semibold text-primary hover:underline">Sorular</a> sayfalarındadır.</p>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ([
                    'reviews_enabled' => ['Ürün sayfasında değerlendirmeler', 'Kapalıyken sekme, yıldızlar ve davet e-postası gizlenir.'],
                    'questions_enabled' => ['Ürün sayfasında soru-cevap', 'Kapalıyken soru formu ve cevaplar gizlenir.'],
                    'reviews_photos' => ['Değerlendirmeye fotoğraf eklenebilsin', 'En fazla 4 fotoğraf; konum bilgisi silinir.'],
                    'reviews_auto_publish' => ['Değerlendirmeleri onaysız yayınla', 'Önerilmez: uygunsuz yorum ve fotoğraflar hemen görünür.'],
                ] as $key => [$label, $help])
                    <label class="flex items-start gap-3 rounded-xl border border-line p-4 has-checked:border-primary has-checked:bg-primary-soft/50">
                        <input type="hidden" name="{{ $key }}" value="0">
                        <input type="checkbox" name="{{ $key }}" value="1" @checked($flag($key)) class="mt-0.5 size-4 accent-primary">
                        <span><span class="block text-sm font-semibold">{{ $label }}</span><span class="block text-xs text-muted">{{ $help }}</span></span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="card space-y-4 p-6 sm:p-8">
            <div>
                <h2 class="font-bold">E-postalar</h2>
                <p class="mt-1 text-sm text-muted">Her e-postayı açıp kapatabilir, konusunu ve metnini değiştirebilirsiniz. Yer tutucular: <code>:ad</code> (kişinin adı), <code>:urun</code> (ürün adı), <code>:puan</code> (yıldız), <code>:siparis</code> (sipariş no, davette), <code>:site</code>.
                    Metinde boş satır yeni paragraf başlatır. Soru, cevap ve değerlendirme metni e-postaya kendiliğinden eklenir. Yönetici e-postaları <a href="{{ route('admin.settings.edit', 'eposta') }}" class="font-semibold text-primary hover:underline">E-posta</a> ayarlarındaki bildirim adreslerine gider.</p>
            </div>
            @foreach (NoticeTemplates::LIST as $tpl => [$label, $audience])
                @php $k = fn ($f) => NoticeTemplates::key($tpl, $f); @endphp
                <details class="group rounded-xl border border-line" @if ($errors->hasAny(array_map($k, NoticeTemplates::FIELDS))) open @endif>
                    <summary class="flex cursor-pointer list-none items-center gap-3 px-4 py-3">
                        <span class="size-2 shrink-0 rounded-full {{ $flag($k('on')) ? 'bg-success' : 'bg-slate-300' }}"></span>
                        <span class="min-w-0 flex-1"><span class="block text-sm font-semibold">{{ $label }}</span><span class="block text-xs text-muted">{{ $audience }} · {{ $s[$k('subject')] }}</span></span>
                        <x-icon name="chevron-down" class="size-4 text-muted transition group-open:rotate-180" />
                    </summary>
                    <div class="space-y-4 border-t border-line p-4">
                        <label class="flex items-center gap-2 text-sm font-semibold">
                            <input type="hidden" name="{{ $k('on') }}" value="0">
                            <input type="checkbox" name="{{ $k('on') }}" value="1" @checked($flag($k('on'))) class="size-4 accent-primary"> Bu e-posta gönderilsin
                        </label>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field :name="$k('subject')" label="Konu" :value="$s[$k('subject')]" />
                            <x-field :name="$k('heading')" label="Başlık" :value="$s[$k('heading')]" />
                        </div>
                        <x-field :name="$k('body')" type="textarea" rows="3" label="Metin" :value="$s[$k('body')]" />
                        <x-field :name="$k('button')" label="Buton yazısı" :value="$s[$k('button')]" class="sm:max-w-xs" />
                    </div>
                </details>
            @endforeach
        </div>

        @foreach ($sections as $title => $fields)
            <details class="card group" @if ($errors->hasAny(array_keys($fields))) open @endif>
                <summary class="flex cursor-pointer list-none items-center justify-between px-6 py-4 font-bold sm:px-8">
                    {{ $title }}
                    <x-icon name="chevron-down" class="size-4 text-muted transition group-open:rotate-180" />
                </summary>
                <div class="grid gap-4 border-t border-line p-6 sm:grid-cols-2 sm:p-8">
                    @foreach ($fields as $key => $label)
                        @if (in_array($key, $long, true) || $key === 'reviews_rating_labels')
                            <x-field :name="$key" type="textarea" :rows="$key === 'reviews_rating_labels' ? 5 : 2" :label="$label" :value="$s[$key]" class="sm:col-span-2" />
                        @else
                            <x-field :name="$key" :label="$label" :value="$s[$key]" />
                        @endif
                    @endforeach
                </div>
            </details>
        @endforeach

        <div class="flex justify-end"><button class="btn-primary">Kaydet</button></div>
    </form>
</x-layouts.admin>
