<x-auth-card title="Yeni parola belirle">
    <form method="post" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-field name="email" type="email" label="E-posta" :value="$email" autocomplete="email" required />
        <x-password-field label="Yeni parola" autocomplete="new-password" help="En az 8 karakter; harf ve rakam içermeli." />
        <x-password-field name="password_confirmation" label="Yeni parola (tekrar)" autocomplete="new-password" />
        <button class="btn-primary w-full py-3">Parolamı değiştir</button>
    </form>
</x-auth-card>
