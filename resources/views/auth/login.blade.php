<x-auth-card title="Giriş yap" subtitle="Siparişlerinizi takip edin, adreslerinizi kaydedin.">
    <form method="post" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <x-field name="email" type="email" label="E-posta" autocomplete="email" required autofocus />
        <x-password-field />
        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" name="remember" value="1" class="size-4 rounded accent-primary"> Beni hatırla</label>
            <a href="{{ route('password.request') }}" class="font-semibold text-primary hover:underline">Parolamı unuttum</a>
        </div>
        <button class="btn-primary w-full py-3">Giriş yap</button>
    </form>
    <x-slot:below>Hesabınız yok mu? <a href="{{ route('register') }}" class="font-semibold text-primary hover:underline">Üye olun</a> · Üye olmadan da sipariş verebilirsiniz.</x-slot:below>
</x-auth-card>
