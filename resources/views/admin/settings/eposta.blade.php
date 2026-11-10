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
                        <option value="{{ $v }}" @selected(old('mail_encryption', $s['mail_encryption']) === $v)>{{ $l }}</option>
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
</x-layouts.admin>
