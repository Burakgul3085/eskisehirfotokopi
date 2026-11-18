<x-account-layout title="Bilgilerim ve parola" :text="$settings['account_settings_text']">
    <div class="grid gap-6 xl:grid-cols-2">
        <section class="card p-6 sm:p-7">
            <h2 class="flex items-center gap-3 text-lg font-extrabold"><span class="flex size-9 items-center justify-center rounded-xl bg-primary-soft text-primary"><x-icon name="user" class="size-4" /></span>Bilgilerim</h2>
            <form method="post" action="{{ route('account.profile') }}" class="mt-5 space-y-4">
                @csrf @method('put')
                <x-field name="name" label="Ad soyad" :value="$user->name" required />
                <x-field name="email" type="email" label="E-posta" :value="$user->email" required />
                <x-field name="phone" type="tel" label="Cep telefonu" :value="\App\Rules\TurkishPhone::format($user->phone)" required />
                <button class="btn-primary">Kaydet</button>
            </form>
        </section>

        <section class="card p-6 sm:p-7">
            <h2 class="flex items-center gap-3 text-lg font-extrabold"><span class="flex size-9 items-center justify-center rounded-xl bg-accent-soft text-accent-ink"><x-icon name="lock" class="size-4" /></span>Parola değiştir</h2>
            <form method="post" action="{{ route('account.password') }}" class="mt-5 space-y-4">
                @csrf @method('put')
                <x-password-field name="current_password" label="Mevcut parola" />
                <x-password-field label="Yeni parola" autocomplete="new-password" help="En az 8 karakter; harf ve rakam içermeli." />
                <x-password-field name="password_confirmation" label="Yeni parola (tekrar)" autocomplete="new-password" />
                <button class="btn-primary">Parolayı değiştir</button>
            </form>
        </section>
    </div>
</x-account-layout>
