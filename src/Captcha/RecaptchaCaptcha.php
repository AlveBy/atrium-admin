<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Captcha;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\HtmlString;

/**
 * Google reCAPTCHA v2, the "I'm not a robot" box. Keys for the site's
 * domains: https://www.google.com/recaptcha/admin. The visitor's browser
 * loads Google's script.
 */
final class RecaptchaCaptcha implements CaptchaDriver
{
    /** The field Google's widget puts its answer in. */
    public const RESPONSE = 'g-recaptcha-response';

    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function __construct(private readonly string $siteKey, private readonly string $secretKey) {}

    public function widget(): HtmlString
    {
        $script = 'https://www.google.com/recaptcha/api.js?hl='.rawurlencode(str_replace('_', '-', app()->getLocale()));

        return new HtmlString(
            '<script src="'.e($script).'" async defer></script>'
            .'<div class="g-recaptcha" data-sitekey="'.e($this->siteKey).'"></div>'
        );
    }

    public function verify(Request $request): bool
    {
        $response = $request->input(self::RESPONSE);

        if (! is_string($response) || $response === '') {
            return false;
        }

        // Google not answering is not a pass: a bot would wait for that.
        try {
            return Http::asForm()->timeout(5)->post(self::VERIFY_URL, [
                'secret' => $this->secretKey,
                'response' => $response,
                'remoteip' => $request->ip(),
            ])->json('success') === true;
        } catch (ConnectionException) {
            return false;
        }
    }
}
