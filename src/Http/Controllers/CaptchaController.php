<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Captcha\ImageCaptcha;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** The picture of the built-in captcha's question. */
final class CaptchaController extends Controller
{
    public function image(Request $request, ImageCaptcha $captcha): Response
    {
        $png = $captcha->image($request->query('t'));

        abort_if($png === null, 404);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
