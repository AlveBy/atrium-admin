{{--
    This file is part of Atrium (https://github.com/AlveBy/atrium).
    Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
--}}
@php
    $themeChoice = $page['props']['theme'] ?? app(\Atrium\Admin\Support\Themes::class)->default();
    $cspNonce = \Illuminate\Support\Facades\Vite::cspNonce();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ app(\Atrium\Admin\Support\Themes::class)->render($themeChoice) }}" data-theme-choice="{{ $themeChoice }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    {{-- "As the system": pick night before the first paint, so a dark system never sees a flash of graphite. --}}
    <script @if ($cspNonce) nonce="{{ $cspNonce }}" @endif>
        (function () {
            var root = document.documentElement;
            if (root.dataset.themeChoice === 'system' && window.matchMedia) {
                root.dataset.theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'night' : 'graphite';
            }
        })();
    </script>
    <title data-inertia data-brand="{{ config('atrium.branding.name', 'Atrium') }}">{{ config('atrium.branding.name', 'Atrium') }}</title>
    @if ($favicon = config('atrium.branding.favicon'))
        <link rel="icon" href="{{ $favicon }}">
    @else
        <link rel="icon" href="{{ asset(\Atrium\Admin\Support\Assets::BUILD_DIRECTORY.'/favicon-32.png') }}" sizes="32x32" type="image/png">
        <link rel="icon" href="{{ asset(\Atrium\Admin\Support\Assets::BUILD_DIRECTORY.'/favicon.svg') }}" type="image/svg+xml">
        <link rel="apple-touch-icon" href="{{ asset(\Atrium\Admin\Support\Assets::BUILD_DIRECTORY.'/apple-touch-icon.png') }}">
    @endif
    @routes('atrium')
    {{ \Atrium\Admin\Support\Assets::tags() }}
    @inertiaHead
</head>
<body class="antialiased bg-page text-fg">
    @inertia
</body>
</html>
