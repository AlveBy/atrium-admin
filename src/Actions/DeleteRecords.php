<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Actions;

use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Support\RecordDeleter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * "Delete" over the selected records: every module has it for those who may
 * delete there. A record that refuses (canDelete(), beforeAdminDelete())
 * stays, and the message says how many did.
 */
final class DeleteRecords extends BulkAction
{
    protected string $permission = 'delete';

    public function handle(Collection $records): ActionResult
    {
        $deleter = app(RecordDeleter::class);
        $deleted = $records->filter(static fn (Model&Administrable $record): bool => $record->canDelete() && $deleter->delete($record))->count();
        $kept = $records->count() - $deleted;

        if ($deleted === 0) {
            return ActionResult::error(trans_choice('atrium::actions.delete.kept', $kept));
        }

        $message = trans_choice('atrium::actions.delete.done', $deleted);

        return ActionResult::success($kept === 0 ? $message : $message.' '.trans_choice('atrium::actions.delete.kept', $kept));
    }
}
