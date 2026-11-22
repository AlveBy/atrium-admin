<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Support;

use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Facades\Hook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a record the way the panel does: with what beforeAdminDelete()
 * takes with it, whole or not at all. The list's delete button, the API and
 * the bulk "Delete" all go through it.
 */
final class RecordDeleter
{
    /**
     * False when beforeAdminDelete() refuses. `atrium.record.deleted` is
     * heard after the commit only.
     */
    public function delete(Model&Administrable $item): bool
    {
        $deleted = DB::transaction(static function () use ($item): ?Model {
            // A retry after a deadlock starts again from the record as it came.
            $record = clone $item;

            if (! $record->beforeAdminDelete()) {
                return null;
            }

            $record->delete();

            return $record;
        }, attempts: 3);

        if ($deleted === null) {
            return false;
        }

        DB::afterCommit(static fn () => Hook::fire('atrium.record.deleted', $deleted));

        return true;
    }
}
