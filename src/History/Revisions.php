<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\History;

use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Models\Activity;
use Atrium\Admin\Models\Revision;
use Atrium\Admin\Transfer\RecordWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Versions of a record: what its form held after each save, to compare two
 * and put an old one back.
 *
 * Every save through the form, the API, the MCP tools or an import keeps one
 * (AdminController::saveItem()), next to its entry in the activity log,
 * which says who saved it, when and from where. The first save of a record
 * that has none yet keeps the record as it was before too. The newest
 * `atrium.history.revisions` of a record stay, older ones go, and all of
 * them go with the record.
 *
 * A module keeps none with `hasAdminRevisions(): false`, a site with
 * `atrium.history.revisions` at 0, and none is kept where there is no
 * activity log. A version holds no secrets: the form's values, without
 * passwords and keys.
 */
final class Revisions
{
    public function __construct(private readonly ActivityLog $log) {}

    /**
     * Whether the module's records keep versions.
     *
     * @param  class-string<Model>  $model
     */
    public function enabledFor(string $model): bool
    {
        return config()->integer('atrium.history.revisions') > 0
            && $this->log->enabledFor($model)
            && (! method_exists($model, 'hasAdminRevisions') || $model::hasAdminRevisions());
    }

    /**
     * Keeps $after as the newest version of $record, saved as $entry says.
     * $before is the record before that save: kept first when it has no
     * version yet; empty for a new record.
     *
     * @param  Model&Administrable  $record
     * @param  array<string,mixed>  $before
     * @param  array<string,mixed>  $after
     */
    public function keep(Model $record, Activity $entry, array $before, array $after): Revision
    {
        if ($before !== [] && ! $this->of($record)->exists()) {
            $this->store($record, null, $before);
        }

        $revision = $this->store($record, $entry, $after);

        Revision::query()->whereKey(array_slice($this->of($record)->pluck('id')->all(), config()->integer('atrium.history.revisions')))->delete();

        return $revision;
    }

    /**
     * The versions of $record, newest first.
     *
     * @param  Model&Administrable  $record
     * @return Builder<Revision>
     */
    public function of(Model $record): Builder
    {
        return Revision::query()
            ->where('module', $record::getAdminRoutePrefix())
            ->where('record_id', $record->getKey())
            ->orderByDesc('id');
    }

    /**
     * Puts $record back as $version holds it: saved as an import saves a
     * row, through the module's form (its validation, hooks and log), the
     * fields of that version only. The save keeps a version of its own.
     *
     * @param  Model&Administrable  $record
     *
     * @throws ValidationException when the form no longer takes those values
     */
    public function restore(Model $record, Revision $version): void
    {
        (new RecordWriter($record::class))->write($version->values, $record);
    }

    /**
     * Forgets the versions of a record that is gone.
     *
     * @param  Model&Administrable  $record
     */
    public function forget(Model $record): void
    {
        $this->of($record)->delete();
    }

    /**
     * @param  Model&Administrable  $record
     * @param  array<string,mixed>  $values
     */
    private function store(Model $record, ?Activity $entry, array $values): Revision
    {
        return Revision::query()->create([
            'activity_id' => $entry?->id,
            'module' => $record::getAdminRoutePrefix(),
            'record_id' => $record->getKey(),
            'values' => $values,
        ]);
    }
}
