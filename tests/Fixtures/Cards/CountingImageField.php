<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\Cards;

use Atrium\Admin\Fields\ImageField;

/**
 * A picture field that counts how often it is asked what it is: how often a
 * builder goes through the form of a class.
 */
final class CountingImageField extends ImageField
{
    public static int $typeCalls = 0;

    public function getType(): string
    {
        self::$typeCalls++;

        return parent::getType();
    }
}
