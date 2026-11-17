<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

/**
 * A backed enum with labels, for the select field.
 */
enum Size: string
{
    case Small = 's';
    case Large = 'l';

    public function label(): string
    {
        return match ($this) {
            self::Small => 'Маленький',
            self::Large => 'Большой',
        };
    }
}
