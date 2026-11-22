<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\Actions;

use Atrium\Admin\Actions\ActionResult;
use Atrium\Admin\Actions\RecordAction;
use Illuminate\Database\Eloquent\Model;

/**
 * Only reads: what the record is called.
 */
final class Inspect extends RecordAction
{
    public function handle(Model $record): ActionResult
    {
        return ActionResult::success('It is '.$record->getAttribute('name').'.');
    }
}
