<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;

/**
 * A handbook with a scope of its own that hides the sections coded "secret":
 * what a site, a status or a soft delete does to a tree.
 */
final class ScopedHandbook extends Handbook
{
    protected static function booted(): void
    {
        self::addGlobalScope('hide-secret', static fn (Builder $query) => $query->where('code', '!=', 'secret'));
    }
}
