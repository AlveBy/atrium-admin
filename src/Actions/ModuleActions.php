<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Actions;

use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Facades\Hook;
use Atrium\Admin\History\ActivityLog;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Support\Sites;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * The buttons of a module, its own and the ones other modules add, and
 * running them. The panel and the API both ask here, so a button is shown,
 * refused and run the same way in both.
 */
final class ModuleActions
{
    /** Ids one bulk action takes at most: a few pages of a list. */
    public const MAX_SELECTED = 1000;

    public function __construct(private readonly Sites $sites) {}

    /**
     * Buttons on a record: getAdministrationActions() and the
     * `atrium.module.actions` hook.
     *
     * @param  class-string<Model&Administrable>  $model
     * @return array<int,CreateFromRecord|Action>
     */
    public function forRecords(string $model): array
    {
        $declared = method_exists($model, 'getAdministrationActions') ? $model::getAdministrationActions() : [];

        return array_filter(
            (array) Hook::apply('atrium.module.actions', $declared, $model),
            static fn (mixed $action): bool => $action instanceof CreateFromRecord || $action instanceof LinkAction || $action instanceof RecordAction,
        );
    }

    /**
     * Buttons over the selected records: "Delete", then
     * getAdministrationBulkActions() and the `atrium.module.bulk_actions` hook.
     *
     * @param  class-string<Model&Administrable>  $model
     * @return array<int,BulkAction>
     */
    public function forSelection(string $model): array
    {
        $declared = method_exists($model, 'getAdministrationBulkActions') ? $model::getAdministrationBulkActions() : [];

        return array_filter(
            (array) Hook::apply('atrium.module.bulk_actions', [DeleteRecords::make('delete')->label(__('atrium::actions.delete.label'))->confirm(__('atrium::actions.delete.confirm')), ...$declared], $model),
            static fn (mixed $action): bool => $action instanceof BulkAction,
        );
    }

    /**
     * Whether this administrator may see and run the action in the module.
     * Someone signed in who is not an administrator of the panel (an app's
     * own guard) may do everything, as on the rest of a module's page.
     *
     * @param  class-string<Model&Administrable>  $model
     */
    public function allows(mixed $user, Action $action, string $model): bool
    {
        return ! $user instanceof AdminUser || $user->hasAdminPermission($action->permissionIn($model::getAdminRoutePrefix()));
    }

    /**
     * The action of this name and kind, if the administrator may run it:
     * 404 when there is none, 403 when it is not theirs.
     *
     * @template T of Action
     *
     * @param  array<int,mixed>  $actions
     * @param  class-string<T>  $type
     * @param  class-string<Model&Administrable>  $model
     * @return T
     */
    public function find(array $actions, string $name, string $type, mixed $user, string $model): Action
    {
        foreach ($actions as $action) {
            if ($action instanceof $type && $action->getName() === $name) {
                abort_unless($this->allows($user, $action, $model), 403, 'This action is not allowed.');

                return $action;
            }
        }

        abort(404, 'No such action.');
    }

    /**
     * The ids of the selected records, as sent.
     *
     * @return array<int,int|string>
     */
    public function selectedIds(Request $request): array
    {
        $request->validate([
            'ids' => ['required', 'array', 'max:'.self::MAX_SELECTED],
            'ids.*' => ['integer'],
        ]);

        return $request->array('ids');
    }

    /**
     * The selected records the list would show: of the current site, through
     * the module's own scopeAdminList().
     *
     * @param  class-string<Model&Administrable>  $model
     * @param  array<int,int|string>  $ids
     * @return Collection<int,Model&Administrable>
     */
    public function selected(string $model, array $ids): Collection
    {
        return (new $model)->scopeAdminList($model::query())->whereKey($ids)->get();
    }

    /**
     * Runs a server button on a record. What it changes goes to the activity
     * log.
     */
    public function runRecord(RecordAction $action, Model $record): ActionResult
    {
        return app(ActivityLog::class)->watch(static fn (): ActionResult => $action->handle($record));
    }

    /**
     * Runs a bulk action over the selected records, or puts it on the queue.
     *
     * @param  class-string<Model&Administrable>  $model
     * @param  array<int,int|string>  $ids
     */
    public function runBulk(BulkAction $action, string $model, array $ids): ActionResult
    {
        $records = $this->selected($model, $ids);

        if ($records->isEmpty()) {
            return ActionResult::error(__('atrium::actions.nothing_selected'));
        }

        $history = app(ActivityLog::class);

        if (! $action instanceof ShouldQueue) {
            return $history->watch(static fn (): ActionResult => $action->handle($records));
        }

        dispatch(new RunBulkAction($action, $model, $records->modelKeys(), $this->sites->current()->id, $history->actor()?->getKey(), $history->source()));

        return ActionResult::success(trans_choice('atrium::actions.queued', $records->count()));
    }
}
