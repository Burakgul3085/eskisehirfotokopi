<x-layouts.admin title="Ayarlar · E-posta">
    @include('admin.settings._layout')
    <form method="post" action="{{ route('admin.settings.update', $group) }}" class="card max-w-3xl space-y-5 p-6 sm:p-8">
        @csrf @method('put')
        <p class="rounded-xl bg-info-soft px-4 py-3 text-sm text-info">Sipariş, mesaj ve hesap e-postaları bu hesaptan gönderilir. Gmail için “uygulama şifresi” kullanın (sunucu smtp.gmail.com, port 587, TLS).</p>
        <div class="grid gap-5 sm:grid-cols-3">
            <x-field name="mail_host" label="SMTP sunucusu" :value="$s['mail_host']" placeholder="smtp.gmail.com" class="sm:col-span-2" />
            <x-field name="mail_port" type="number" label="Port" :value="$s['mail_port']" />
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="field-label" for="f_mail_encryption">Şifreleme</label>
                <select id="f_mail_encryption" name="mail_encryption" class="field-input">
                    @foreach (['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', 'none' => 'Yok'] as $v => $l)
                        <option value="{{ $v }}" @selected(old('mail_encryption', $s['mail_encryption']) === (string) $v)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <x-field name="mail_username" label="Kullanıcı adı" :value="$s['mail_username']" autocomplete="off" />
        </div>
        <x-field name="mail_password" type="password" label="Parola" autocomplete="new-password"
                 :help="$s['mail_password'] ? 'Kayıtlı bir parola var. Değiştirmek istemiyorsanız boş bırakın.' : 'Parola şifrelenerek saklanır.'" />
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="mail_from_address" type="email" label="Gönderen adresi" :value="$s['mail_from_address']" help="Boşsa kullanıcı adı kullanılır." />
            <x-field name="mail_from_name" label="Gönderen adı" :value="$s['mail_from_name']" help="Boşsa site adı kullanılır." />
        </div>
        <x-field name="notify_emails" label="Yönetici bildirim adresleri" :value="$s['notify_emails']" help="Yeni sipariş ve mesaj bildirimleri bu adreslere gider. Virgülle ayırın." />
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
