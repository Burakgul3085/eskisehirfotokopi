<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $settings['site_name'] }}</title>
    @vite(['resources/css/app.css'])
    <style>{!! \App\Support\Settings::themeCss() !!}</style>
</head>
<body class="flex min-h-screen items-center justify-center p-6">
    <div class="card max-w-md p-10 text-center">
        <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-primary-soft text-primary"><x-icon name="settings" class="size-7" /></span>
        <h1 class="mt-5 text-2xl font-bold">{{ $settings['site_name'] }}</h1>
        <p class="mt-3 text-muted">{{ $settings['closed_message'] }}</p>
        @if ($settings['phone'])<p class="mt-5 text-sm font-semibold">{{ $settings['phone'] }}</p>@endif
    </div>
</body>
</html>
