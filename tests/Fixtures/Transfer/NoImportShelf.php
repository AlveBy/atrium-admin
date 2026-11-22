<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\Transfer;

/**
 * A module that says no to the import and keeps the export.
 */
final class NoImportShelf extends Shelf
{
    public static function isImportableInAdmin(): bool
    {
        return false;
    }
}
