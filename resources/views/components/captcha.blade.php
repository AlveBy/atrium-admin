{{--
    This file is part of Atrium (https://github.com/AlveBy/atrium).
    Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
--}}
{{--
    The captcha of a public form: <x-atrium::captcha /> inside the <form>.
    Nothing when atrium.captcha.driver is none. The form validates with
    app(\Atrium\Admin\Captcha\CaptchaManager::class)->rules() and shows the
    error of the "captcha" field itself.
--}}
@php($captcha = app(\Atrium\Admin\Captcha\CaptchaManager::class))
@if ($captcha->enabled())
    <div {{ $attributes->merge(['class' => 'atrium-captcha']) }}>{{ $captcha->widget() }}</div>
@endif
