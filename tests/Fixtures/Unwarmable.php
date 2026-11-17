<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Atrium\Admin\Contracts\Cacheable;

/**
 * Says it is cacheable but is not a model: there is no table to read cards from.
 */
final class Unwarmable implements Cacheable
{
    public function cacheRelations(): array
    {
        return [];
    }
}
