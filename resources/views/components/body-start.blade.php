{{--
    This file is part of Atrium (https://github.com/AlveBy/atrium).
    Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
--}}
{{--
    Right after <body>: the no-JavaScript parts of the counters and the
    site's own code for the start of the page. See <x-atrium::head />.
--}}
@php
    $site = app(\Atrium\Admin\Support\Sites::class)->current();
    $counters = ! ($site->counters_skip_admins && auth()->guard(config('atrium.auth.guard', 'admin'))->check())
        && ! config('atrium.counters.consent', false);
@endphp
@if ($counters && $site->google_tag_id && str_starts_with($site->google_tag_id, 'GTM-'))
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $site->google_tag_id }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
@endif
@if ($counters && $site->yandex_metrika_id)
<noscript><div><img src="https://mc.yandex.ru/watch/{{ (int) $site->yandex_metrika_id }}" style="position:absolute;left:-9999px" alt=""></div></noscript>
@endif
@if ($site->body_start_code)
{!! $site->body_start_code !!}
@endif
