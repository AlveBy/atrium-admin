<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Actions;

use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\History\ActivityLog;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\Sites;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

/**
 * A bulk action that implements ShouldQueue, run by a queue worker in the
 * site it was started in. What it returns is not shown to anybody: an
 * action that has something to say sends it itself (mail, a notification).
 */
final class RunBulkAction implements ShouldQueue
{
    use Queueable;

    /**
     * @param  class-string<Model&Administrable>  $model
     * @param  array<int,int|string>  $ids
     */
    public function __construct(
        public readonly BulkAction $action,
        public readonly string $model,
        public readonly array $ids,
        public readonly int $site,
        // Who started it and from where: what the action changes is logged as theirs.
        public readonly ?int $admin = null,
        public readonly string $source = ActivityLog::ADMIN,
    ) {}

    public function handle(Sites $sites, ModuleActions $actions, ActivityLog $history): void
    {
        $sites->setCurrent(Site::query()->findOrFail($this->site));

        try {
            $history->during($this->source, AdminUser::query()->find($this->admin), fn (): ActionResult => $history->watch(
                fn (): ActionResult => $this->action->handle($actions->selected($this->model, $this->ids)),
            ));
        } finally {
            // A worker runs the next job in the same process.
            $sites->forgetCurrent();
        }
    }
}
