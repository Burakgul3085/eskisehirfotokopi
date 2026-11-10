<x-auth-card title="Üye ol" subtitle="Bir dakikada hesabınızı oluşturun.">
    <form method="post" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <x-field name="name" label="Ad soyad" autocomplete="name" required autofocus />
        <x-field name="email" type="email" label="E-posta" autocomplete="email" required />
        <x-field name="phone" type="tel" label="Cep telefonu" autocomplete="tel" placeholder="0532 123 45 67" required help="Teslimatta size ulaşmak için kullanılır." />
        <x-password-field label="Parola" autocomplete="new-password" help="En az 8 karakter; harf ve rakam içermeli." />
        <x-password-field name="password_confirmation" label="Parola (tekrar)" autocomplete="new-password" />
        <label class="flex items-start gap-2.5 text-sm">
            <input type="checkbox" name="terms" value="1" @checked(old('terms')) class="mt-0.5 size-4 rounded accent-primary">
            <span>Üyelik koşullarını ve KVKK aydınlatma metnini okudum, kabul ediyorum.</span>
        </label>
        @error('terms')<p class="field-error">{{ $message }}</p>@enderror
        <button class="btn-primary w-full py-3">Hesabımı oluştur</button>
    </form>
    <x-slot:below>Zaten üye misiniz? <a href="{{ route('login') }}" class="font-semibold text-primary hover:underline">Giriş yapın</a></x-slot:below>
</x-auth-card>
