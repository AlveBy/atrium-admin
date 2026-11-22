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
 * Turns a record on; one already on is an error, one named "Away" sends the
 * administrator to another site.
 */
final class Publish extends RecordAction
{
    public function handle(Model $record): ActionResult
    {
        if ($record->getAttribute('name') === 'Away') {
            return ActionResult::success()->redirect('https://example.org/away');
        }

        if ($record->getAttribute('active')) {
            return ActionResult::error('Already published.');
        }

        $record->update(['active' => true]);

        return ActionResult::success('Published '.$record->getAttribute('name').'.');
    }
}
