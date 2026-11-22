<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Actions;

use Atrium\Admin\Contracts\Administrable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * A button over the records selected in a module's list:
 *
 *     final class Archive extends BulkAction
 *     {
 *         public function handle(Collection $records): ActionResult
 *         {
 *             $records->each->update(['archived' => true]);
 *
 *             return ActionResult::success(__('Archived.'));
 *         }
 *     }
 *
 * The records are the selected ones the administrator may see in the list,
 * of the site they work with: ids of another site or of hidden records are
 * dropped before handle() runs. An action that implements
 * Illuminate\Contracts\Queue\ShouldQueue runs on the queue; the
 * administrator is told it has started. Needs the module's `update`
 * permission unless told otherwise.
 */
abstract class BulkAction extends Action
{
    /**
     * @param  Collection<int,Model&Administrable>  $records
     */
    abstract public function handle(Collection $records): ActionResult;

    public function type(): string
    {
        return 'bulk';
    }
}
