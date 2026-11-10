@props(['title' => 'Yönetim'])
@php
    $admin = auth('admin')->user();
    // Menü grupları: [başlık => [[etiket, ikon, rota, eşleşme]]]. Yeni modüller buraya eklenir.
    $navGroups = [
        '' => [
            ['Gösterge paneli', 'home', 'admin.dashboard', 'admin.dashboard'],
        ],
        'Katalog' => [
            ['Ürünler', 'book', 'admin.products.index', 'admin.products.*'],
            ['Kategoriler', 'layers', 'admin.categories.index', 'admin.categories.*'],
            ['Yayınevleri', 'list', 'admin.publishers.index', 'admin.publishers.*'],
            ['Yazarlar', 'user', 'admin.authors.index', 'admin.authors.*'],
            ['Toplu yükleme', 'upload', 'admin.imports.index', 'admin.imports.*'],
        ],
    ];
    $settingsNav = collect(\App\Http\Controllers\Admin\SettingsController::GROUPS)
        ->map(fn ($label, $group) => ['label' => $label, 'url' => route('admin.settings.edit', $group), 'active' => request()->routeIs('admin.settings.*') && request()->route('group') === $group]);
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — Yönetim</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>{!! \App\Support\Settings::themeCss() !!}</style>
</head>
<body class="min-h-screen bg-surface" x-data="toggle">
    <div x-show="open" x-cloak class="fixed inset-0 z-30 bg-ink/40 lg:hidden" x-on:click="close"></div>

    <aside class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-ink transition lg:translate-x-0"
           x-bind:class="slideInClass">
        <div class="flex items-center gap-2.5 px-5 py-5">
            <span class="flex size-9 items-center justify-center rounded-xl bg-primary text-white"><x-icon name="printer" class="size-5" /></span>
            <div class="min-w-0">
                <p class="truncate font-display text-sm font-bold text-white">{{ $settings['site_name'] }}</p>
                <p class="text-xs text-slate-400">Yönetim paneli</p>
            </div>
        </div>

        <nav class="flex-1 space-y-6 overflow-y-auto px-3 pb-6">
            @foreach ($navGroups as $groupLabel => $items)
                <div>
                    @if ($groupLabel)<p class="px-3 pb-2 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">{{ $groupLabel }}</p>@endif
                    <div class="space-y-1">
                        @foreach ($items as [$label, $icon, $route, $match])
                            @if (\Illuminate\Support\Facades\Route::has($route))
                                <a href="{{ route($route) }}" class="nav-admin-link" @if (request()->routeIs($match)) aria-current="page" @endif>
                                    <x-icon :name="$icon" class="size-[18px]" />{{ $label }}
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div>
                <p class="px-3 pb-2 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Ayarlar</p>
                <div class="space-y-1">
                    @foreach ($settingsNav as $item)
                        <a href="{{ $item['url'] }}" class="nav-admin-link" @if ($item['active']) aria-current="page" @endif>
                            <x-icon name="settings" class="size-[18px]" />{{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            @if ($admin->isSuper())
                <div>
                    <p class="px-3 pb-2 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">Sistem</p>
                    <div class="space-y-1">
                        <a href="{{ route('admin.admins.index') }}" class="nav-admin-link" @if (request()->routeIs('admin.admins.*')) aria-current="page" @endif>
                            <x-icon name="users" class="size-[18px]" />Yöneticiler
                        </a>
                        <a href="{{ route('admin.activity') }}" class="nav-admin-link" @if (request()->routeIs('admin.activity')) aria-current="page" @endif>
                            <x-icon name="list" class="size-[18px]" />İşlem günlüğü
                        </a>
                    </div>
                </div>
            @endif
        </nav>

        <div class="border-t border-white/10 p-4">
            <p class="truncate text-sm font-semibold text-white">{{ $admin->name }}</p>
            <p class="text-xs text-slate-400">{{ $admin->roleLabel() }}</p>
            <div class="mt-3 flex gap-2">
                <a href="{{ route('home') }}" target="_blank" class="btn btn-sm flex-1 bg-white/5 text-slate-200 hover:bg-white/10"><x-icon name="external" class="size-3.5" />Siteyi aç</a>
                <form method="post" action="{{ route('admin.logout') }}">@csrf<button class="btn btn-sm bg-white/5 text-slate-200 hover:bg-white/10" aria-label="Çıkış"><x-icon name="logout" class="size-3.5" /></button></form>
            </div>
        </div>
    </aside>

    <div class="lg:pl-64">
        <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-line bg-white/90 px-4 py-3 backdrop-blur sm:px-8">
            <button type="button" x-on:click="toggle" class="rounded-lg p-1.5 hover:bg-surface lg:hidden" aria-label="Menü"><x-icon name="menu" /></button>
            <h1 class="truncate text-lg font-bold">{{ $title }}</h1>
            <div class="ml-auto flex items-center gap-2">{{ $actions ?? '' }}</div>
        </header>
        <main class="px-4 py-6 sm:px-8 sm:py-8">
            <x-flash class="mb-6" />
            {{ $slot }}
        </main>
    </div>
</body>
</html>
