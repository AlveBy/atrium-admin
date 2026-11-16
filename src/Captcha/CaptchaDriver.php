<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Captcha;

use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;

/**
 * One kind of captcha: what goes into the form and how the answer is
 * checked. Add your own with app(CaptchaManager::class)->extend('name', ...).
 */
interface CaptchaDriver
{
    /** The HTML put into the form; empty when the captcha is off. */
    public function widget(): HtmlString;

    /** Whether the request carries a right answer. */
    public function verify(Request $request): bool;
}
