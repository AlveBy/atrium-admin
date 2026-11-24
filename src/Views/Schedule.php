<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Views;

use Atrium\Admin\Fields\Field;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * What a CalendarView shows: the days it draws for a mode and a day, the
 * records that fall on them, and the dates a record gets when it is dropped
 * on another day. The days are plain dates, with no time zone: a record is
 * on the day its field says.
 */
final class Schedule
{
    /** Month grid, week grid, and the records of the month as a list. */
    public const MODES = ['month', 'week', 'list'];

    /** Records one range may hold; more are left out and the page says so. */
    public const MAX_EVENTS = 500;

    /** The hour a record gets when it is made by a click on a day, if its field has a time. */
    public const DEFAULT_TIME = '09:00:00';

    public function __construct(
        private readonly CalendarView $view,
        private readonly Field $start,
        private readonly ?Field $end = null,
    ) {}

    public function view(): CalendarView
    {
        return $this->view;
    }

    /**
     * `month`, `week` or `list`; anything else is the month.
     */
    public static function mode(mixed $asked): string
    {
        return is_string($asked) && in_array($asked, self::MODES, true) ? $asked : 'month';
    }

    /**
     * A day written 2026-12-31 and really there; null for anything else.
     */
    public static function day(mixed $asked): ?CarbonImmutable
    {
        if (! is_string($asked) || ! CarbonImmutable::hasFormat($asked, 'Y-m-d')) {
            return null;
        }

        $day = CarbonImmutable::parse($asked, 'UTC');

        return $day->format('Y-m-d') === $asked ? $day : null;
    }

    /**
     * The first day drawn and the day after the last one: a month is six
     * weeks of seven days from the Monday before its first day, a week is
     * its seven days, a list is the days of the month.
     *
     * @return array{0:CarbonImmutable,1:CarbonImmutable}
     */
    public static function range(string $mode, CarbonImmutable $day): array
    {
        return match ($mode) {
            'week' => [$week = $day->startOfWeek(CarbonImmutable::MONDAY), $week->addDays(7)],
            'list' => [$month = $day->startOfMonth(), $month->addMonth()],
            default => [$grid = $day->startOfMonth()->startOfWeek(CarbonImmutable::MONDAY), $grid->addDays(42)],
        };
    }

    /**
     * The records that fall in the range, or last into it, by their start.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function within(Builder $query, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        $model = $query->getModel();
        $start = $model->qualifyColumn($this->start->getColumn());
        $end = $this->end;

        return $query->reorder()
            ->where($start, '<', $to->format($this->format($this->start)))
            ->where(function (Builder $where) use ($model, $start, $end, $from): void {
                $where->where($start, '>=', $from->format($this->format($this->start)));

                if ($end !== null) {
                    $where->orWhere($model->qualifyColumn($end->getColumn()), '>=', $from->format($this->format($end)));
                }
            })
            ->orderBy($start)
            ->orderBy($model->getQualifiedKeyName())
            ->limit(self::MAX_EVENTS + 1);
    }

    /**
     * A record's date or time in the form the field is stored in, or null
     * when it has none.
     */
    public function stamp(Model $record, ?Field $field): ?string
    {
        return $field === null ? null : $this->moment($record->getAttribute($field->getColumn()))?->format($this->format($field));
    }

    /**
     * The dates to save when the record is dropped on `$day`: its start
     * moves there with its time of day, its end by the same number of days.
     * Null for a record that has no start, which no day shows.
     *
     * @return array<string,string>|null field => value
     */
    public function moved(Model $record, CarbonImmutable $day): ?array
    {
        $start = $this->moment($record->getAttribute($this->start->getColumn()));

        if ($start === null) {
            return null;
        }

        // Whole days, counted on plain dates: a clock change between the two days must not shorten one.
        $days = intval(CarbonImmutable::parse($start->format('Y-m-d'), 'UTC')->diffInDays($day, false));
        $dates = [$this->start->getName() => $start->addDays($days)->format($this->format($this->start))];
        $end = $this->end === null ? null : $this->moment($record->getAttribute($this->end->getColumn()));

        if ($this->end !== null && $end !== null) {
            $dates[$this->end->getName()] = $end->addDays($days)->format($this->format($this->end));
        }

        return $dates;
    }

    /**
     * What a click on a day fills in: the day, and for a field with a time
     * the hour of a working day's start.
     */
    public function created(CarbonImmutable $day): string
    {
        return $this->start->getType() === 'datetime' ? $day->format('Y-m-d').' '.self::DEFAULT_TIME : $day->format('Y-m-d');
    }

    /**
     * The same moment as a CarbonImmutable; null for an empty value.
     */
    private function moment(mixed $value): ?CarbonImmutable
    {
        return match (true) {
            $value instanceof DateTimeInterface => CarbonImmutable::instance($value),
            is_string($value) && $value !== '' => CarbonImmutable::parse($value),
            default => null,
        };
    }

    private function format(Field $field): string
    {
        return $field->getType() === 'datetime' ? 'Y-m-d H:i:s' : 'Y-m-d';
    }
}
