<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Support;

use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Facades\Hook;
use Atrium\Admin\History\ActivityLog;
use Atrium\Admin\History\Revisions;
use Atrium\Admin\History\Trash;
use Atrium\Admin\Hooks\Refused;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a record the way the panel does: with what beforeAdminDelete()
 * takes with it, whole or not at all. The list's delete button, the API and
 * the bulk "Delete" all go through it, and the deletion goes to the activity
 * log with the record. A module with the trash (Atrium\Admin\History\Trash)
 * has its records put there; they are deleted for good from there.
 */
final class RecordDeleter
{
    /**
     * False when beforeAdminDelete() refuses. A listener of
     * `atrium.record.deleting` refuses by throwing Refused, which leaves
     * here after the rollback. `atrium.record.deleted` is heard after the
     * commit only: both when the record goes to the trash and when it is
     * deleted for good ($forever).
     *
     * @throws Refused
     */
    public function delete(Model&Administrable $item, bool $forever = false): bool
    {
        $deleted = DB::transaction(static function () use ($item, $forever): ?Model {
            // A retry after a deadlock starts again from the record as it came.
            $record = clone $item;

            // Inside the transaction: what a listener writes goes or stays
            // with the record.
            Hook::fire('atrium.record.deleting', $record);

            if (! $record->beforeAdminDelete()) {
                return null;
            }

            $trash = app(Trash::class);

            if (! $forever && $trash->enabledFor($record::class)) {
                $trash->put($record);
            } else {
                $record->delete();
                $trash->forget($record);
                // Gone for good: so are its versions.
                app(Revisions::class)->forget($record);
            }

            $history = app(ActivityLog::class);

            if ($history->enabledFor($record::class)) {
                $history->record($record, $forever ? ActivityLog::PURGED : ActivityLog::DELETED);
            }

            return $record;
        }, attempts: 3);

        if ($deleted === null) {
            return false;
        }

        DB::afterCommit(static fn () => Hook::fire('atrium.record.deleted', $deleted));

        return true;
    }
}
