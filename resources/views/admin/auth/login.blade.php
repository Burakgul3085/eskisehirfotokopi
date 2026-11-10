<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Yönetim girişi — {{ $settings['site_name'] }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>{!! \App\Support\Settings::themeCss() !!}</style>
</head>
<body class="flex min-h-screen items-center justify-center bg-ink p-4">
    <div class="w-full max-w-sm">
        <div class="mb-6 flex items-center justify-center gap-2.5 text-white">
            <span class="flex size-10 items-center justify-center rounded-xl bg-primary"><x-icon name="printer" class="size-5" /></span>
            <span class="font-display text-lg font-bold">{{ $settings['site_name'] }}</span>
        </div>
        <div class="card p-7">
            <h1 class="text-xl font-extrabold">Yönetim girişi</h1>
            <form method="post" action="{{ route('admin.login') }}" class="mt-6 space-y-4">
                @csrf
                <x-field name="email" type="email" label="E-posta" autocomplete="username" required autofocus />
                <x-password-field />
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1" class="size-4 rounded accent-primary"> Bu cihazda oturumu açık tut</label>
                <button class="btn-primary w-full py-3">Giriş yap</button>
            </form>
        </div>
    </div>
</body>
</html>
