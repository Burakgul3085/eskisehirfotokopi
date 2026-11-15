<x-layouts.admin title="Ayarlar · E-posta">
    @include('admin.settings._layout')
    <form method="post" action="{{ route('admin.settings.update', $group) }}" class="card max-w-3xl space-y-5 p-6 sm:p-8">
        @csrf @method('put')
        <div class="rounded-xl bg-info-soft px-4 py-3 text-sm text-info">
            <p class="font-semibold">Yalnız iki bilgi yeterli: e-posta adresiniz ve 16 haneli uygulama şifresi.</p>
            <p class="mt-1">Sunucu, port ve şifreleme adresinize göre otomatik ayarlanır. Sipariş, mesaj ve hesap e-postaları bu adresten gönderilir.
                Gmail'de uygulama şifresi: Google Hesabı › Güvenlik › 2 Adımlı Doğrulama açık olmalı › “Uygulama şifreleri”.</p>
        </div>
        <x-field name="mail_username" type="email" label="E-posta adresi" :value="filter_var($s['mail_username'], FILTER_VALIDATE_EMAIL) ? $s['mail_username'] : $s['mail_from_address']" placeholder="ornek@gmail.com" autocomplete="off" required />
        <x-field name="mail_password" type="password" label="Uygulama şifresi (16 hane)" autocomplete="new-password" placeholder="abcd efgh ijkl mnop"
                 :help="$s['mail_password'] ? 'Kayıtlı bir uygulama şifresi var. Değiştirmek istemiyorsanız boş bırakın.' : 'Boşluklu ya da boşluksuz yapıştırabilirsiniz; şifrelenerek saklanır.'" />

        <details class="group rounded-xl border border-line">
            <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 text-sm font-semibold text-muted">
                İsteğe bağlı ayarlar
                <x-icon name="chevron-down" class="size-4 transition group-open:rotate-180" />
            </summary>
            <div class="space-y-5 border-t border-line p-4">
                <x-field name="mail_from_name" label="Gönderen adı" :value="$s['mail_from_name']" :placeholder="$settings['site_name'] ?? ''" help="Boşsa site adı kullanılır." />
                <x-field name="notify_emails" label="Yönetici bildirim adresleri" :value="$s['notify_emails']" help="Yeni sipariş ve mesaj bildirimleri. Boşsa yukarıdaki adrese gider. Birden fazlaysa virgülle ayırın." />
            </div>
        </details>
        <div class="flex justify-end"><button class="btn-primary">Kaydet</button></div>
    </form>

    <form method="post" action="{{ route('admin.settings.test-mail') }}" class="card mt-6 max-w-3xl p-6 sm:p-8">
        @csrf
        <h2 class="font-bold">Deneme e-postası</h2>
        <p class="mt-1 text-sm text-muted">Kaydettiğiniz ayarlarla hemen bir e-posta gönderir. Gönderilen e-postalar <a href="{{ route('admin.mail-logs') }}" class="font-semibold text-primary hover:underline">E-posta kayıtları</a> sayfasında görünür.</p>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start">
            <x-field name="to" type="email" label="Alıcı" :value="old('to', auth('admin')->user()->email)" class="sm:flex-1" />
            <button class="btn-ghost sm:mt-7"><x-icon name="mail" class="size-4" />Gönder</button>
        </div>
    </form>
</x-layouts.admin>
