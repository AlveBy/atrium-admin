<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\History;

use Atrium\Admin\Api\ApiResources;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\DateTimeField;
use Atrium\Admin\Fields\Field;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\ScheduledRecord;
use Atrium\Admin\Models\TrashedRecord;
use Atrium\Admin\Transfer\RecordWriter;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Scheduled publishing: a record switches itself on at "Publish at" and off
 * at "Unpublish at".
 *
 * The two fields come next to the "Active" field of every form that has one
 * (a module opts out with `usesAdminSchedule(): false`), so the form, the
 * API, the MCP tools and an import plan it the same way. The times live in
 * `admin_schedules`, not in the module's table. The scheduler checks every
 * minute (atrium:publish) and switches the record through the form's
 * own save: the activity log says it was the scheduler on behalf of who
 * planned it, a version is kept, caches and the search hear of it.
 */
final class PublishingSchedule
{
    public const PUBLISH_AT = 'publish_at';

    public const UNPUBLISH_AT = 'unpublish_at';

    /** The form field the schedule switches. */
    public const ACTIVE = 'active';

    public function __construct(private readonly ActivityLog $log, private readonly ApiResources $resources) {}

    /**
     * Whether the module's records may be scheduled; its form needs an
     * "Active" field too.
     *
     * @param  class-string<Model>  $model
     */
    public function enabledFor(string $model): bool
    {
        return method_exists($model, 'usesAdminSchedule') && $model::usesAdminSchedule();
    }

    /**
     * The form's fields with "Publish at" and "Unpublish at" right after
     * its "Active" field, on the same tab (`atrium.module.fields`).
     *
     * @param  array<int,Field>  $fields
     * @param  class-string<Model>  $model
     * @return array<int,Field>
     */
    public function withFields(array $fields, string $model): array
    {
        $at = $this->enabledFor($model) ? $this->positionOfActive($fields) : null;

        if ($at === null) {
            return $fields;
        }

        $active = $fields[$at];
        $added = [
            DateTimeField::make(self::PUBLISH_AT)
                ->label(__('atrium::schedule.publish_at'))
                ->hint(__('atrium::schedule.publish_at_hint')),
            DateTimeField::make(self::UNPUBLISH_AT)
                ->label(__('atrium::schedule.unpublish_at'))
                ->hint(__('atrium::schedule.unpublish_at_hint')),
        ];

        foreach ($added as $field) {
            $field->tab($active->getTab())->tabOrder($active->getTabOrder());
        }

        array_splice($fields, $at + 1, 0, $added);

        return $fields;
    }

    /**
     * Takes the schedule out of what a form sends (by reference): it is not
     * a column of the record. Only what was sent; nothing when the form has
     * no schedule.
     *
     * @param  array<int,Field>  $fields  the fields of the form that was sent
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    public function extract(array $fields, array &$data): array
    {
        if (! $this->scheduled($fields)) {
            return [];
        }

        $values = Arr::only($data, [self::PUBLISH_AT, self::UNPUBLISH_AT]);
        unset($data[self::PUBLISH_AT], $data[self::UNPUBLISH_AT]);

        return $values;
    }

    /**
     * Keeps the times a form sent for $record; the ones it left out stay.
     * Both empty: nothing is planned any more. The end must come after the
     * start, the one kept included: a PATCH may send only one of them.
     *
     * @param  Model&Administrable  $record
     * @param  array<string,mixed>  $values  extract()'s
     *
     * @throws ValidationException
     */
    public function plan(Model $record, array $values): void
    {
        $entry = $this->entryOf($record::class, $record->getKey()) ?? new ScheduledRecord(['module' => $record::getAdminRoutePrefix(), 'record_id' => $record->getKey()]);
        $entry->fill(array_map(static fn (mixed $at): mixed => blank($at) ? null : $at, $values));

        if ($entry->publish_at !== null && $entry->unpublish_at !== null && $entry->unpublish_at <= $entry->publish_at) {
            throw ValidationException::withMessages([self::UNPUBLISH_AT => __('validation.after', [
                'attribute' => __('atrium::schedule.unpublish_at'),
                'date' => __('atrium::schedule.publish_at'),
            ])]);
        }

        if ($entry->publish_at === null && $entry->unpublish_at === null) {
            $entry->delete();

            return;
        }

        if ($entry->isDirty()) {
            $entry->fill(['site_id' => ActivityLog::siteOf($record), 'admin_user_id' => $this->log->actor()?->getKey()])->save();
        }
    }

    /**
     * The times of the record $id of $model as its form shows them, when the
     * form has the schedule.
     *
     * @param  class-string<Model&Administrable>  $model
     * @param  array<int,Field>  $fields
     * @return array<string,mixed>
     */
    public function present(string $model, mixed $id, array $fields): array
    {
        if (! $this->scheduled($fields)) {
            return [];
        }

        $times = ($this->entryOf($model, $id) ?? new ScheduledRecord)->attributesToArray();

        return [
            self::PUBLISH_AT => $times[self::PUBLISH_AT] ?? null,
            self::UNPUBLISH_AT => $times[self::UNPUBLISH_AT] ?? null,
        ];
    }

    /**
     * What is planned for the records of a list page, by id: one query.
     *
     * @param  class-string<Model&Administrable>  $model
     * @param  array<int,int|string>  $ids
     * @return array<int|string,array{publish_at:string|null,unpublish_at:string|null}>
     */
    public function planned(string $model, array $ids): array
    {
        if (! $this->enabledFor($model)) {
            return [];
        }

        return ScheduledRecord::query()
            ->where('module', $model::getAdminRoutePrefix())
            ->whereIn('record_id', $ids)
            ->get()
            ->mapWithKeys(static fn (ScheduledRecord $entry): array => [$entry->record_id => [
                self::PUBLISH_AT => $entry->publish_at?->toIso8601String(),
                self::UNPUBLISH_AT => $entry->unpublish_at?->toIso8601String(),
            ]])
            ->all();
    }

    /**
     * Switches on and off what is due at $now; the records switched, and
     * the ones whose save was refused with why. A record in the trash waits
     * until it is restored; one that is gone takes its plan along.
     *
     * @return array{switched:int,refused:array<int,string>}
     */
    public function run(CarbonInterface $now): array
    {
        $switched = 0;
        $refused = [];
        $due = ScheduledRecord::query()->where(static fn (Builder $query): Builder => $query->where(self::PUBLISH_AT, '<=', $now)->orWhere(self::UNPUBLISH_AT, '<=', $now));

        foreach ($due->lazyById() as $entry) {
            $record = $this->recordOf($entry);

            if ($record === null) {
                if (! TrashedRecord::query()->where('module', $entry->module)->where('record_id', $entry->record_id)->exists()) {
                    $entry->delete();
                }

                continue;
            }

            try {
                $this->switch($entry, $record, $now);
                $switched++;
            } catch (ValidationException $refusal) {
                // Not tried again every minute: the plan of what is due goes.
                $this->done($entry, $now);
                $refused[] = $entry->module.' #'.$entry->record_id.': '.implode(' ', Arr::flatten($refusal->errors()));
            }
        }

        return ['switched' => $switched, 'refused' => $refused];
    }

    /**
     * Forgets the plan of a record that is deleted for good.
     *
     * @param  Model&Administrable  $record
     */
    public function forget(Model $record): void
    {
        ScheduledRecord::query()->where('module', $record::getAdminRoutePrefix())->where('record_id', $record->getKey())->delete();
    }

    /**
     * Off once the end has come (it is the later time: plan() sees to it),
     * else on; saved the way the form saves.
     *
     * @param  Model&Administrable  $record
     */
    private function switch(ScheduledRecord $entry, Model $record, CarbonInterface $now): void
    {
        $on = ! ($entry->unpublish_at !== null && $entry->unpublish_at <= $now);
        $planner = AdminUser::query()->find($entry->admin_user_id);

        // A copy: when the save is refused, the transaction takes back the
        // dropped times but not what the entry in memory holds.
        DB::transaction(function () use ($entry, $record, $now, $on, $planner): void {
            $this->done(clone $entry, $now);
            $this->log->during(ActivityLog::SCHEDULE, $planner, static fn (): Model => (new RecordWriter($record::class))->write([self::ACTIVE => $on], $record));
        });
    }

    /**
     * Drops the times of $entry that are due; the entry goes with the last.
     */
    private function done(ScheduledRecord $entry, CarbonInterface $now): void
    {
        foreach ([self::PUBLISH_AT, self::UNPUBLISH_AT] as $name) {
            if ($entry->{$name} !== null && $entry->{$name} <= $now) {
                $entry->{$name} = null;
            }
        }

        $entry->publish_at === null && $entry->unpublish_at === null ? $entry->delete() : $entry->save();
    }

    /**
     * @return (Model&Administrable)|null
     */
    private function recordOf(ScheduledRecord $entry): ?Model
    {
        $model = $this->resources->modules()[$entry->module] ?? null;

        return $model === null ? null : $model::query()->find($entry->record_id);
    }

    /**
     * @param  class-string<Model&Administrable>  $model
     */
    private function entryOf(string $model, mixed $id): ?ScheduledRecord
    {
        return ScheduledRecord::query()->where('module', $model::getAdminRoutePrefix())->where('record_id', $id)->first();
    }

    /**
     * @param  array<int,Field>  $fields
     */
    private function scheduled(array $fields): bool
    {
        foreach ($fields as $field) {
            if ($field->getName() === self::PUBLISH_AT) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int,Field>  $fields
     */
    private function positionOfActive(array $fields): ?int
    {
        foreach ($fields as $position => $field) {
            if ($field->getName() === self::ACTIVE) {
                return $position;
            }
        }

        return null;
    }
}
