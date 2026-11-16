<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Captcha;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Manager;

/**
 * The captcha of the public forms, one for every module: set
 * `atrium.captcha.driver` and each form that draws <x-atrium::captcha />
 * and validates with rules() asks it.
 *
 *     $rules = [...$rules, ...app(CaptchaManager::class)->rules()];
 *
 * Drivers: none (off), image (built in, no keys), recaptcha (Google
 * reCAPTCHA v2; without keys the image one is used). Others come with
 * extend('name', fn ($app) => new MyCaptcha).
 *
 * @method HtmlString widget()
 * @method bool verify(\Illuminate\Http\Request $request)
 */
final class CaptchaManager extends Manager
{
    /** The field the error goes to, whatever the driver. */
    public const FIELD = 'captcha';

    /** Fields a form sends back to the captcha; not to be put back into the form. */
    public const INPUTS = [self::FIELD, ImageCaptcha::TOKEN, RecaptchaCaptcha::RESPONSE];

    public function getDefaultDriver(): string
    {
        $driver = $this->config->get('atrium.captcha.driver');

        return is_string($driver) && $driver !== '' ? $driver : 'none';
    }

    public function enabled(): bool
    {
        return ! $this->driver() instanceof NoCaptcha;
    }

    /**
     * Validation rules that check the captcha; none when it is off.
     *
     * @return array<string,list<CaptchaRule>>
     */
    public function rules(): array
    {
        return $this->enabled() ? [self::FIELD => [new CaptchaRule]] : [];
    }

    protected function createNoneDriver(): CaptchaDriver
    {
        return new NoCaptcha;
    }

    protected function createImageDriver(): CaptchaDriver
    {
        return $this->container->make(ImageCaptcha::class);
    }

    protected function createRecaptchaDriver(): CaptchaDriver
    {
        $siteKey = (string) $this->config->get('atrium.captcha.recaptcha.site_key');
        $secretKey = (string) $this->config->get('atrium.captcha.recaptcha.secret_key');

        // No keys yet: the forms are still protected, by the built-in one.
        if ($siteKey === '' || $secretKey === '') {
            return $this->createImageDriver();
        }

        return new RecaptchaCaptcha($siteKey, $secretKey);
    }
}
