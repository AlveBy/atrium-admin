<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\Transfer;

/**
 * Records cannot be changed in the admin, so a file cannot change them.
 */
final class FrozenShelf extends Shelf
{
    public static function isEditableInAdmin(): bool
    {
        return false;
    }
}
