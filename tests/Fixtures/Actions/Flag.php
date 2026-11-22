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

/**
 * Switches the records off and names them, in the order they came.
 */
class Flag extends BulkAction
{
    public function handle(Collection $records): ActionResult
    {
        $records->each->update(['active' => false]);

        return ActionResult::success('Hidden: '.$records->sortBy('id')->pluck('name')->implode(', ').'.');
    }
}
