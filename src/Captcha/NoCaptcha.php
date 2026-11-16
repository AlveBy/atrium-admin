<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Captcha;

use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;

/** The captcha is off: nothing in the form, every request passes. */
final class NoCaptcha implements CaptchaDriver
{
    public function widget(): HtmlString
    {
        return new HtmlString('');
    }

    public function verify(Request $request): bool
    {
        return true;
    }
}
