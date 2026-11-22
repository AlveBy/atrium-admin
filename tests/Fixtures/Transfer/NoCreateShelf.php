<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\Transfer;

/**
 * Records come from elsewhere: nothing is created in the admin, so nothing is imported.
 */
final class NoCreateShelf extends Shelf
{
    public static function isCreatableInAdmin(): bool
    {
        return false;
    }
}
