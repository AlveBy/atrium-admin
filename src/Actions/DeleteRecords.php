<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Actions;

use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Hooks\Refused;
use Atrium\Admin\Support\RecordDeleter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * "Delete" over the selected records: every module has it for those who may
 * delete there. A record that refuses (canDelete(), beforeAdminDelete(), a
 * Refused from `atrium.record.deleting`) stays, and the message says how
 * many did and the reasons the listeners gave.
 */
final class DeleteRecords extends BulkAction
{
    protected string $permission = 'delete';

    public function handle(Collection $records): ActionResult
    {
        $deleter = app(RecordDeleter::class);
        $refusals = [];
        $deleted = $records->filter(static function (Model&Administrable $record) use ($deleter, &$refusals): bool {
            try {
                return $record->canDelete() && $deleter->delete($record);
            } catch (Refused $refused) {
                $refusals[] = $refused->getMessage();

                return false;
            }
        })->count();
        $kept = $records->count() - $deleted;
        // Each reason a listener gave once, after the count of what stayed.
        $keptMessage = implode(' ', [trans_choice('atrium::actions.delete.kept', $kept), ...array_unique($refusals)]);

        if ($deleted === 0) {
            return ActionResult::error($keptMessage);
        }

        $message = trans_choice('atrium::actions.delete.done', $deleted);

        return ActionResult::success($kept === 0 ? $message : $message.' '.$keptMessage);
    }
}
