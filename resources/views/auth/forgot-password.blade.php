<x-auth-card title="Parolamı unuttum" subtitle="E-posta adresinize parola sıfırlama bağlantısı gönderelim.">
    <form method="post" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <x-field name="email" type="email" label="E-posta" autocomplete="email" required autofocus />
        <button class="btn-primary w-full py-3">Bağlantı gönder</button>
    </form>
    <x-slot:below><a href="{{ route('login') }}" class="font-semibold text-primary hover:underline">Girişe dön</a></x-slot:below>
</x-auth-card>
