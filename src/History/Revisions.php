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
 *
 * The same rows hold drafts: what the page editor saves of one field before
 * it is published. A record has one draft of a field at most, whether or not
 * its module keeps versions; a draft is not a version, is not counted with
 * them, and goes with the record too.
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
        return $this->rows($record)->whereNull('draft')->orderByDesc('id');
    }

    /**
     * The draft of a field of $record, or null: `values` holds it under the
     * field's name.
     *
     * @param  Model&Administrable  $record
     */
    public function draft(Model $record, string $field): ?Revision
    {
        return $this->rows($record)->where('draft', $field)->first();
    }

    /**
     * Keeps $value as the draft of a field of $record, in place of the one
     * before. $base is the fingerprint of the value it was started from.
     *
     * @param  Model&Administrable  $record
     */
    public function keepDraft(Model $record, string $field, mixed $value, string $base, ?string $author): Revision
    {
        $this->dropDraft($record, $field);

        return $this->store($record, null, [$field => $value], ['draft' => $field, 'base' => $base, 'author' => $author]);
    }

    /**
     * @param  Model&Administrable  $record
     */
    public function dropDraft(Model $record, string $field): void
    {
        $this->rows($record)->where('draft', $field)->delete();
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
     * Forgets the versions and the drafts of a record that is gone.
     *
     * @param  Model&Administrable  $record
     */
    public function forget(Model $record): void
    {
        $this->rows($record)->delete();
    }

    /**
     * Everything kept of a record: versions and drafts.
     *
     * @param  Model&Administrable  $record
     * @return Builder<Revision>
     */
    private function rows(Model $record): Builder
    {
        return Revision::query()
            ->where('module', $record::getAdminRoutePrefix())
            ->where('record_id', $record->getKey());
    }

    /**
     * @param  Model&Administrable  $record
     * @param  array<string,mixed>  $values
     * @param  array<string,string|null>  $draft  the columns of a draft
     */
    private function store(Model $record, ?Activity $entry, array $values, array $draft = []): Revision
    {
        return Revision::query()->create([
            'activity_id' => $entry?->id,
            'module' => $record::getAdminRoutePrefix(),
            'record_id' => $record->getKey(),
            'values' => $values,
            ...$draft,
        ]);
    }
}
