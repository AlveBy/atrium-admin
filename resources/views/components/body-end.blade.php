{{--
    Right before </body>: the site's own code for the end of the page
    (chat widgets, pixels). See <x-atrium::head />.
--}}
@php($site = app(\Atrium\Admin\Support\Sites::class)->current())
@if ($site->body_end_code)
{!! $site->body_end_code !!}
@endif
