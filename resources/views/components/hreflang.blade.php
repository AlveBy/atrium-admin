{{--
    Alternate-language links for search engines.

    <x-atrium::hreflang :urls="['en' => $enUrl, 'de' => $deUrl]" />

    Pass only the languages the page really exists in. x-default points to
    the default content language.
--}}
@props(['urls' => []])
@php
    $languages = app(\Atrium\Admin\Support\ContentLanguages::class);
    $default = $urls[$languages->default()] ?? null;
@endphp
@if (count($urls) > 1)
@foreach ($urls as $code => $url)
@php $language = $languages->find((string) $code); @endphp
@if ($language)
<link rel="alternate" hreflang="{{ $language->hreflang() }}" href="{{ $url }}">
@endif
@endforeach
@if ($default)
<link rel="alternate" hreflang="x-default" href="{{ $default }}">
@endif
@endif
