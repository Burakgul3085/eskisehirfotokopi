<x-layouts.store title="Hesabım">
    <div class="container-page py-10">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm text-muted">Hoş geldiniz</p>
                <h1 class="text-3xl font-extrabold">{{ $user->name }}</h1>
            </div>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="btn-ghost"><x-icon name="logout" class="size-4" /> Çıkış yap</button></form>
        </div>

        @include('account._nav')
        <x-flash class="mt-6" />

        <div class="mt-8 grid gap-6 lg:grid-cols-2">
            <section class="card p-6 sm:p-7">
                <h2 class="text-lg font-bold">Bilgilerim</h2>
                <form method="post" action="{{ route('account.profile') }}" class="mt-5 space-y-4">
                    @csrf @method('put')
                    <x-field name="name" label="Ad soyad" :value="$user->name" required />
                    <x-field name="email" type="email" label="E-posta" :value="$user->email" required />
                    <x-field name="phone" type="tel" label="Cep telefonu" :value="\App\Rules\TurkishPhone::format($user->phone)" required />
                    <button class="btn-primary">Kaydet</button>
                </form>
            </section>

            <section class="card p-6 sm:p-7">
                <h2 class="text-lg font-bold">Parola değiştir</h2>
                <form method="post" action="{{ route('account.password') }}" class="mt-5 space-y-4">
                    @csrf @method('put')
                    <x-password-field name="current_password" label="Mevcut parola" />
                    <x-password-field label="Yeni parola" autocomplete="new-password" help="En az 8 karakter; harf ve rakam içermeli." />
                    <x-password-field name="password_confirmation" label="Yeni parola (tekrar)" autocomplete="new-password" />
                    <button class="btn-primary">Parolayı değiştir</button>
                </form>
            </section>
        </div>

    </div>
</x-layouts.store>
