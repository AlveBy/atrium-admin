<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\Actions;

use Atrium\Admin\Actions\ActionResult;
use Atrium\Admin\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * Fails halfway, as a real action may.
 */
final class Explode extends BulkAction
{
    public function handle(Collection $records): ActionResult
    {
        throw new RuntimeException('Boom.');
    }
}
