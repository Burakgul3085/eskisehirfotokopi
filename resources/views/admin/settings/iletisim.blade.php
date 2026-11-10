<x-layouts.admin title="Ayarlar · İletişim">
    @include('admin.settings._layout')
    <form method="post" action="{{ route('admin.settings.update', $group) }}" class="card max-w-3xl space-y-5 p-6 sm:p-8">
        @csrf @method('put')
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="phone" label="Telefon" :value="$s['phone']" placeholder="0222 123 45 67" />
            <x-field name="whatsapp" label="WhatsApp numarası" :value="$s['whatsapp']" placeholder="90532 123 45 67" help="Ülke koduyla. Doluysa sitede WhatsApp düğmesi görünür." />
            <x-field name="email" type="email" label="E-posta" :value="$s['email']" />
            <x-field name="maps_url" type="url" label="Google Haritalar bağlantısı" :value="$s['maps_url']" placeholder="https://" />
        </div>
        <x-field name="address" type="textarea" rows="2" label="Adres" :value="$s['address']" />
        <x-field name="working_hours" type="textarea" rows="3" label="Çalışma saatleri" :value="$s['working_hours']" help="Her satıra bir gün aralığı yazın (ör. Pzt–Cmt 09:00–20:00)." />

        <h2 class="border-t border-line pt-5 font-bold">İletişim sayfası</h2>
        <x-field name="contact_intro" type="textarea" rows="2" label="Sayfa açıklaması" :value="$s['contact_intro']" />
        <x-field name="contact_consent" label="Onay kutusu metni" :value="$s['contact_consent']" />

        <h2 class="border-t border-line pt-5 font-bold">Sosyal medya</h2>
        <div class="grid gap-5 sm:grid-cols-2">
            @foreach (['instagram' => 'Instagram', 'facebook' => 'Facebook', 'x' => 'X (Twitter)', 'youtube' => 'YouTube', 'tiktok' => 'TikTok'] as $key => $label)
                <x-field :name="$key" type="url" :label="$label" :value="$s[$key]" placeholder="https://" />
            @endforeach
        </div>
        <div class="flex justify-end"><button class="btn-primary">Kaydet</button></div>
    </form>
</x-layouts.admin>
