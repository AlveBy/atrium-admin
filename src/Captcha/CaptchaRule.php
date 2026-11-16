<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Captcha;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Fails when the captcha of the current request is not solved. Runs even
 * when the field is missing: a bot that leaves it out does not pass.
 */
final class CaptchaRule implements ValidationRule
{
    public bool $implicit = true;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! app(CaptchaManager::class)->driver()->verify(request())) {
            $fail('atrium::captcha.failed')->translate();
        }
    }
}
