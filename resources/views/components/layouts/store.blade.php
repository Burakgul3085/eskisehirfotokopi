@props(['title' => null, 'description' => null, 'noindex' => false, 'image' => null])
@php
    $siteName = $settings['site_name'] ?: 'Eskişehir Fotokopi';
    $pageTitle = $title ? $title.' — '.$siteName : ($settings['meta_title'] ?: $siteName);
    $metaDescription = $description ?: ($settings['meta_description'] ?: $settings['site_tagline']);
    $favicon = \App\Support\Settings::fileUrl('favicon_path');
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }}</title>
    @if ($metaDescription)<meta name="description" content="{{ $metaDescription }}">@endif
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    @if ($image)<meta property="og:image" content="{{ $image }}">@endif
    @if ($noindex)<meta name="robots" content="noindex, follow">@else<link rel="canonical" href="{{ url()->current() }}{{ request('page') > 1 ? '?page='.(int) request('page') : '' }}">@endif
    @if ($favicon)<link rel="icon" href="{{ $favicon }}">@endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>{!! \App\Support\Settings::themeCss() !!}</style>
    {{ $head ?? '' }}
</head>
<body class="flex min-h-screen flex-col" x-data="toggle">
    <a href="#icerik" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2">İçeriğe geç</a>

    @include('store.partials.header')
    @include('store.partials.mobile-menu')

    <main id="icerik" class="flex-1">
        {{ $slot }}
    </main>

    @include('store.partials.footer')

    @if ($wa = \App\Support\Settings::whatsappUrl())
        <a href="{{ $wa }}" target="_blank" rel="noopener" aria-label="WhatsApp ile yazın"
           class="fixed right-4 bottom-4 z-40 flex size-13 items-center justify-center rounded-full bg-[#25d366] text-white shadow-lift transition hover:scale-105 sm:right-6 sm:bottom-6">
            <x-icon name="whatsapp" class="size-7" />
        </a>
    @endif
</body>
</html>
