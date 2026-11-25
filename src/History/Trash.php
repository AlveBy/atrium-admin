<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\History;

use Atrium\Admin\Api\ApiResources;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Hooks\Refused;
use Atrium\Admin\Models\TrashedRecord;
use Atrium\Admin\Scopes\TrashScope;
use Atrium\Admin\Support\RecordDeleter;
use Atrium\Admin\Support\Sites;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * The trash: what is deleted in the panel, through the API, the MCP tools or
 * a bulk action can be brought back.
 *
 * A deleted record stays in its own table, whole: its files, SEO, links and
 * the records that point to it stay too. A row in `admin_trash` hides it
 * everywhere (TrashScope), and to the rest of the site it is gone: Eloquent's
 * `deleted` is heard, as with Laravel's soft deletes, so caches, the sitemap
 * and the search forget it. Restoring it is heard as `restored` and `saved`.
 * Deleting it for good is the panel's delete of old (RecordDeleter), which
 * takes its files, SEO and versions along. What has been in the trash longer
 * than `atrium.trash.days` goes for good on its own (atrium:trash:empty).
 *
 * A module deletes for good at once with `usesAdminTrash(): false`.
 */
final class Trash
{
    public function __construct(private readonly ActivityLog $log, private readonly ApiResources $resources, private readonly Sites $sites) {}

    /**
     * Whether the module's records go to the trash when deleted.
     *
     * @param  class-string<Model>  $model
     */
    public function enabledFor(string $model): bool
    {
        return method_exists($model, 'usesAdminTrash') && $model::usesAdminTrash();
    }

    /**
     * Puts $record in the trash, in RecordDeleter's transaction.
     *
     * @param  Model&Administrable  $record
     */
    public function put(Model $record): TrashedRecord
    {
        $actor = $this->log->actor();

        $entry = TrashedRecord::query()->create([
            'module' => $record::getAdminRoutePrefix(),
            'record_id' => $record->getKey(),
            'site_id' => ActivityLog::siteOf($record),
            'record_title' => Str::limit(ActivityLog::titleOf($record), 250),
            'admin_user_id' => $actor?->getKey(),
            'admin_name' => $actor?->name,
        ]);

        Event::dispatch('eloquent.deleted: '.$record::class, $record);

        return $entry;
    }

    /**
     * The entries of the site the panel works with and of the modules all
     * sites share, newest first.
     *
     * @return Builder<TrashedRecord>
     */
    public function entries(): Builder
    {
        $site = $this->sites->current()->id;

        return TrashedRecord::query()
            ->where(static fn (Builder $query) => $query->whereNull('site_id')->orWhere('site_id', $site))
            ->orderByDesc('id');
    }

    /**
     * The record an entry hides; null when it is not there any more (its
     * module was removed, or the row deleted past the panel).
     *
     * @return (Model&Administrable)|null
     */
    public function record(TrashedRecord $entry): ?Model
    {
        $model = $this->resources->modules()[$entry->module] ?? null;

        return $model === null ? null : $model::query()->withoutGlobalScope(TrashScope::class)->find($entry->record_id);
    }

    /**
     * Brings the record back where it was, as it was.
     *
     * @param  Model&Administrable  $record  the record of the entry
     */
    public function restore(TrashedRecord $entry, Model $record): void
    {
        DB::transaction(function () use ($entry, $record): void {
            $entry->delete();

            if ($this->log->enabledFor($record::class)) {
                $this->log->record($record, ActivityLog::RESTORED);
            }
        });

        Event::dispatch('eloquent.restored: '.$record::class, $record);
        Event::dispatch('eloquent.saved: '.$record::class, $record);
    }

    /**
     * Deletes the record of the entry for good; false when beforeAdminDelete()
     * refuses. An entry whose record is gone already just goes.
     *
     * @throws Refused
     */
    public function purge(TrashedRecord $entry): bool
    {
        $record = $this->record($entry);

        if ($record === null) {
            $entry->delete();

            return true;
        }

        return app(RecordDeleter::class)->delete($record, forever: true);
    }

    /**
     * Deletes for good what went to the trash before $before; how many. What
     * a module refuses to delete stays.
     */
    public function emptyOlderThan(CarbonInterface $before): int
    {
        $count = 0;

        foreach (TrashedRecord::query()->where('created_at', '<', $before)->lazyById() as $entry) {
            try {
                if ($this->purge($entry)) {
                    $count++;
                }
            } catch (Refused) {
                // Kept, as the module wants.
            }
        }

        return $count;
    }

    /**
     * Takes $record out of the trash, if it is there: it is deleted for good.
     *
     * @param  Model&Administrable  $record
     */
    public function forget(Model $record): void
    {
        TrashedRecord::query()->where('module', $record::getAdminRoutePrefix())->where('record_id', $record->getKey())->delete();
    }
}
