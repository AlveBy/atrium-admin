{{--
    This file is part of Atrium (https://github.com/AlveBy/atrium).
    Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title data-inertia data-brand="{{ config('atrium.branding.name', 'Atrium') }}">{{ config('atrium.branding.name', 'Atrium') }}</title>
    @if ($favicon = config('atrium.branding.favicon'))
        <link rel="icon" href="{{ $favicon }}">
    @endif
    @routes('atrium')
    {{ \Atrium\Admin\Support\Assets::tags() }}
    @inertiaHead
</head>
<body class="antialiased">
    @inertia
</body>
</html>
