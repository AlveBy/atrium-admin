{{--
    Counters and verification codes of the current site, set in the admin
    (Sites → Counters and codes). Put it once inside <head> of the layout,
    together with <x-atrium::body-start /> and <x-atrium::body-end />.

    With config('atrium.counters.consent') on, the counters wait for the
    visitor's consent: the site's cookie banner calls window.atriumConsent().
--}}
@php
    $site = app(\Atrium\Admin\Support\Sites::class)->current();
    $counters = ! ($site->counters_skip_admins && auth()->guard(config('atrium.auth.guard', 'admin'))->check());
    $consent = (bool) config('atrium.counters.consent', false);
    $script = $consent ? 'type="text/plain" data-atrium-consent' : '';
    $tag = $site->google_tag_id;
    $metrika = $site->yandex_metrika_id;
@endphp
@if ($site->google_verification)
<meta name="google-site-verification" content="{{ $site->google_verification }}">
@endif
@if ($site->yandex_verification)
<meta name="yandex-verification" content="{{ $site->yandex_verification }}">
@endif
@if ($site->bing_verification)
<meta name="msvalidate.01" content="{{ $site->bing_verification }}">
@endif
@if ($counters && $consent && ($tag || $metrika))
<script>window.atriumConsent=function(){document.querySelectorAll('script[data-atrium-consent]').forEach(function(o){var s=document.createElement('script');for(var i=0;i<o.attributes.length;i++){var a=o.attributes[i];if(a.name!=='type'&&a.name!=='data-atrium-consent')s.setAttribute(a.name,a.value)}s.text=o.text;o.parentNode.replaceChild(s,o)})};</script>
@endif
@if ($counters && $tag && str_starts_with($tag, 'GTM-'))
<script {!! $script !!}>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',@js($tag));</script>
@elseif ($counters && $tag)
<script async {!! $script !!} src="https://www.googletagmanager.com/gtag/js?id={{ $tag }}"></script>
<script {!! $script !!}>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config',@js($tag));</script>
@endif
@if ($counters && $metrika)
<script {!! $script !!}>(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};m[i].l=1*new Date();for(var j=0;j<document.scripts.length;j++){if(document.scripts[j].src===r){return;}}k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})(window,document,'script','https://mc.yandex.ru/metrika/tag.js','ym');ym({{ (int) $metrika }},'init',{clickmap:true,trackLinks:true,accurateTrackBounce:true,webvisor:@js((bool) $site->yandex_webvisor)});</script>
@endif
@if ($site->head_code)
{!! $site->head_code !!}
@endif
