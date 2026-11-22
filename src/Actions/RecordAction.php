<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Actions;

use Illuminate\Database\Eloquent\Model;

/**
 * A button on a record that does something on the server:
 *
 *     final class Publish extends RecordAction
 *     {
 *         public function handle(Model $record): ActionResult
 *         {
 *             $record->update(['active' => true]);
 *
 *             return ActionResult::success(__('Published.'));
 *         }
 *     }
 *
 *     Publish::make('publish')->label('Publish')->confirm();
 *
 * The record is found the way the panel finds it, in the site the
 * administrator works with. Needs the module's `update` permission unless
 * told otherwise.
 */
abstract class RecordAction extends Action
{
    abstract public function handle(Model $record): ActionResult;

    public function type(): string
    {
        return 'record';
    }
}
