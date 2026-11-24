<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Fields\DateField;
use Atrium\Admin\Fields\DateTimeField;
use Atrium\Admin\Tests\Fixtures\Holiday;
use Atrium\Admin\Tests\Fixtures\Meeting;
use Atrium\Admin\Tests\Fixtures\Ticket;
use Atrium\Admin\Tests\TestCase;
use Atrium\Admin\Views\CalendarView;
use Atrium\Admin\Views\Schedule;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What a calendar draws and the dates a dropped record gets: the arithmetic
 * of days, with nothing about the page.
 */
final class ScheduleTest extends TestCase
{
    private function timed(): Schedule
    {
        return new Schedule(CalendarView::make('starts_at')->until('ends_at'), DateTimeField::make('starts_at'), DateTimeField::make('ends_at'));
    }

    private function dated(): Schedule
    {
        return new Schedule(CalendarView::make('on_day'), DateField::make('on_day'));
    }

    private function meeting(string $title, ?string $start, ?string $end = null): Meeting
    {
        return Meeting::query()->create(['title' => $title, 'starts_at' => $start, 'ends_at' => $end]);
    }

    /**
     * @return array<int,string>
     */
    private function titles(Schedule $schedule, string $from, string $to): array
    {
        return $schedule->within(Meeting::query(), CarbonImmutable::parse($from), CarbonImmutable::parse($to))->get()->pluck('title')->all();
    }

    public function test_a_calendar_has_three_modes_and_the_month_is_the_one_to_fall_back_to(): void
    {
        $this->assertSame(['month', 'week', 'list'], Schedule::MODES);

        foreach (['month', 'week', 'list'] as $mode) {
            $this->assertSame($mode, Schedule::mode($mode));
        }

        foreach (['year', '', null, ['week'], 5] as $odd) {
            $this->assertSame('month', Schedule::mode($odd));
        }
    }

    /**
     * @return array<string,array{mixed,?string}>
     */
    public static function days(): array
    {
        return [
            'a day' => ['2026-12-31', '2026-12-31'],
            'a leap day' => ['2028-02-29', '2028-02-29'],
            'no such day' => ['2026-02-30', null],
            'no such month' => ['2026-13-01', null],
            'not padded' => ['2026-2-3', null],
            'with a time' => ['2026-12-31 10:00', null],
            'text' => ['tomorrow', null],
            'empty' => ['', null],
            'nothing' => [null, null],
            'an array' => [['2026-12-31'], null],
            'a number' => [20261231, null],
        ];
    }

    #[DataProvider('days')]
    public function test_a_day_is_a_real_date_written_year_month_day(mixed $asked, ?string $expected): void
    {
        $this->assertSame($expected, Schedule::day($asked)?->format('Y-m-d'));
    }

    public function test_a_day_is_a_plain_date_in_no_time_zone_of_the_application(): void
    {
        $this->app['config']->set('app.timezone', 'Pacific/Auckland');
        date_default_timezone_set('Pacific/Auckland');

        $day = Schedule::day('2026-12-31');

        $this->assertSame('UTC', $day?->getTimezone()->getName());
        $this->assertSame('00:00:00', $day?->format('H:i:s'));
    }

    public function test_a_month_is_six_weeks_from_the_monday_before_its_first_day(): void
    {
        $day = CarbonImmutable::parse('2026-12-15');

        [$from, $to] = Schedule::range('month', $day);

        $this->assertSame('2026-11-30', $from->format('Y-m-d'));
        $this->assertSame('2027-01-11', $to->format('Y-m-d'));
        $this->assertSame([$from->format('Y-m-d'), $to->format('Y-m-d')], array_map(static fn (CarbonImmutable $d): string => $d->format('Y-m-d'), Schedule::range('anything', $day)), 'Any other mode is the month.');
        $this->assertSame('2026-06-01', Schedule::range('month', CarbonImmutable::parse('2026-06-30'))[0]->format('Y-m-d'), 'A month that starts on a Monday starts the grid.');
    }

    public function test_a_week_is_the_seven_days_from_its_monday_and_a_list_the_days_of_the_month(): void
    {
        [$from, $to] = Schedule::range('week', CarbonImmutable::parse('2026-12-16'));

        $this->assertSame(['2026-12-14', '2026-12-21'], [$from->format('Y-m-d'), $to->format('Y-m-d')]);

        [$from, $to] = Schedule::range('week', CarbonImmutable::parse('2026-12-20'));

        $this->assertSame('2026-12-14', $from->format('Y-m-d'), 'A Sunday belongs to the week before it.');

        [$from, $to] = Schedule::range('list', CarbonImmutable::parse('2026-12-16'));

        $this->assertSame(['2026-12-01', '2027-01-01'], [$from->format('Y-m-d'), $to->format('Y-m-d')]);
    }

    public function test_the_records_of_a_range_are_those_that_start_in_it_or_last_into_it(): void
    {
        $this->meeting('before', '2026-12-04 10:00:00', '2026-12-04 12:00:00');
        $this->meeting('from before', '2026-12-04 10:00:00', '2026-12-08 00:00:00');
        $this->meeting('starts at the first moment', '2026-12-07 00:00:00');
        $this->meeting('inside', '2026-12-10 15:00:00');
        $this->meeting('starts at the last moment', '2026-12-13 23:59:59');
        $this->meeting('starts after', '2026-12-14 00:00:00', '2026-12-15 00:00:00');
        $this->meeting('no date', null);
        $this->meeting('only an end', null, '2026-12-10 00:00:00');
        $this->meeting('ends at the first moment of the range', '2026-12-01 00:00:00', '2026-12-07 00:00:00');
        $this->meeting('ends a second before', '2026-12-01 00:00:00', '2026-12-06 23:59:59');

        $this->assertSame(
            ['ends at the first moment of the range', 'from before', 'starts at the first moment', 'inside', 'starts at the last moment'],
            $this->titles($this->timed(), '2026-12-07', '2026-12-14'),
        );
    }

    public function test_without_a_field_for_the_end_a_record_is_where_it_starts(): void
    {
        $this->meeting('from before', '2026-12-04 10:00:00', '2026-12-08 00:00:00');
        $this->meeting('inside', '2026-12-10 15:00:00');

        $schedule = new Schedule(CalendarView::make('starts_at'), DateTimeField::make('starts_at'));

        $this->assertSame(['inside'], $this->titles($schedule, '2026-12-07', '2026-12-14'));
    }

    public function test_the_records_are_in_the_order_of_their_start_then_of_their_key(): void
    {
        $this->meeting('late', '2026-12-10 15:00:00');
        $this->meeting('second at noon', '2026-12-09 12:00:00');
        $this->meeting('early', '2026-12-08 08:00:00');
        $this->meeting('first at noon', '2026-12-09 12:00:00');

        $query = Meeting::query()->orderByDesc('title')->orderByDesc('id');

        $this->assertSame(
            ['early', 'second at noon', 'first at noon', 'late'],
            $this->timed()->within($query, CarbonImmutable::parse('2026-12-07'), CarbonImmutable::parse('2026-12-14'))->get()->pluck('title')->all(),
            'The order of the list is replaced.',
        );
    }

    public function test_one_more_than_the_most_is_asked_for_to_tell_that_there_are_more(): void
    {
        $this->assertSame(500, Schedule::MAX_EVENTS);
        $this->assertSame(Schedule::MAX_EVENTS + 1, $this->timed()->within(Meeting::query(), CarbonImmutable::parse('2026-12-07'), CarbonImmutable::parse('2026-12-14'))->getQuery()->limit);
    }

    public function test_a_date_field_is_compared_by_days(): void
    {
        Holiday::query()->create(['name' => 'first', 'on_day' => '2026-12-07']);
        Holiday::query()->create(['name' => 'last', 'on_day' => '2026-12-13']);
        Holiday::query()->create(['name' => 'after', 'on_day' => '2026-12-14']);
        Holiday::query()->create(['name' => 'before', 'on_day' => '2026-12-06']);

        $names = $this->dated()->within(Holiday::query(), CarbonImmutable::parse('2026-12-07'), CarbonImmutable::parse('2026-12-14'))->get()->pluck('name')->all();

        $this->assertSame(['first', 'last'], $names);
    }

    public function test_a_stamp_is_the_date_or_the_time_as_the_field_keeps_it(): void
    {
        $meeting = $this->meeting('m', '2026-12-05 10:30:15', null);

        $this->assertSame('2026-12-05 10:30:15', $this->timed()->stamp($meeting, DateTimeField::make('starts_at')));
        $this->assertSame('2026-12-05', $this->timed()->stamp($meeting, DateField::make('starts_at')));
        $this->assertNull($this->timed()->stamp($meeting, DateTimeField::make('ends_at')));
        $this->assertNull($this->timed()->stamp($meeting, null));
    }

    public function test_a_stamp_reads_a_value_kept_as_text_and_nothing_else(): void
    {
        $text = new Ticket(['name' => '2026-12-05 10:30:15']);

        $this->assertSame('2026-12-05 10:30:15', $this->timed()->stamp($text, DateTimeField::make('name')));
        $this->assertNull($this->timed()->stamp(new Ticket(['name' => '']), DateTimeField::make('name')), 'An empty text is no date.');
        $this->assertNull($this->timed()->stamp(new Ticket(['lane_id' => 5]), DateTimeField::make('lane_id')), 'A number is no date.');
    }

    public function test_a_dropped_record_keeps_its_time_and_its_length(): void
    {
        $meeting = $this->meeting('m', '2026-12-05 10:30:15', '2026-12-07 12:00:00');

        $this->assertSame(
            ['starts_at' => '2026-12-20 10:30:15', 'ends_at' => '2026-12-22 12:00:00'],
            $this->timed()->moved($meeting, CarbonImmutable::parse('2026-12-20')),
        );
        $this->assertSame(
            ['starts_at' => '2026-12-01 10:30:15', 'ends_at' => '2026-12-03 12:00:00'],
            $this->timed()->moved($meeting, CarbonImmutable::parse('2026-12-01')),
            'Back in time as well.',
        );
        $this->assertSame(
            ['starts_at' => '2026-12-05 10:30:15', 'ends_at' => '2026-12-07 12:00:00'],
            $this->timed()->moved($meeting, CarbonImmutable::parse('2026-12-05')),
            'On its own day it stays.',
        );
    }

    public function test_a_dropped_record_without_an_end_saves_only_its_start(): void
    {
        $meeting = $this->meeting('m', '2026-12-05 10:30:15', null);

        $this->assertSame(['starts_at' => '2026-12-20 10:30:15'], $this->timed()->moved($meeting, CarbonImmutable::parse('2026-12-20')));
        $this->assertSame(['starts_at' => '2026-12-20 10:30:15'], (new Schedule(CalendarView::make('starts_at'), DateTimeField::make('starts_at')))->moved(
            $this->meeting('m2', '2026-12-05 10:30:15', '2026-12-07 12:00:00'),
            CarbonImmutable::parse('2026-12-20'),
        ), 'A view with no end field leaves the end alone.');
    }

    public function test_a_record_without_a_start_is_not_moved(): void
    {
        $this->assertNull($this->timed()->moved($this->meeting('m', null, '2026-12-07 12:00:00'), CarbonImmutable::parse('2026-12-20')));
    }

    public function test_a_date_is_moved_as_a_date(): void
    {
        $holiday = Holiday::query()->create(['name' => 'h', 'on_day' => '2026-12-05']);

        $this->assertSame(['on_day' => '2026-12-31'], $this->dated()->moved($holiday, CarbonImmutable::parse('2026-12-31')));
    }

    public function test_a_clock_change_between_the_days_does_not_shorten_a_day(): void
    {
        $this->app['config']->set('app.timezone', 'Europe/Paris');
        date_default_timezone_set('Europe/Paris');
        $meeting = $this->meeting('m', '2026-03-28 00:00:00', '2026-03-28 23:30:00');

        $this->assertSame(
            ['starts_at' => '2026-03-30 00:00:00', 'ends_at' => '2026-03-30 23:30:00'],
            $this->timed()->moved($meeting, Schedule::day('2026-03-30') ?? CarbonImmutable::now()),
            'Two days over the night the clocks go forward are two days.',
        );
    }

    public function test_a_click_on_a_day_fills_the_day_and_for_a_field_with_a_time_the_hour_work_starts(): void
    {
        $day = CarbonImmutable::parse('2026-12-20');

        $this->assertSame('09:00:00', Schedule::DEFAULT_TIME);
        $this->assertSame('2026-12-20 09:00:00', $this->timed()->created($day));
        $this->assertSame('2026-12-20', $this->dated()->created($day));
    }

    public function test_a_schedule_knows_its_view(): void
    {
        $view = CalendarView::make('on_day');

        $this->assertSame($view, (new Schedule($view, DateField::make('on_day')))->view());
    }

    public function test_a_calendar_view_declares_what_it_writes_and_needs(): void
    {
        $view = CalendarView::make('starts_at');

        $this->assertSame('calendar', $view->type());
        $this->assertNull($view->getUntil());
        $this->assertSame(['starts_at'], $view->writes());
        $this->assertSame(['starts_at' => ['date', 'datetime']], $view->needs());

        $until = CalendarView::make('starts_at')->until('ends_at');

        $this->assertSame('ends_at', $until->getUntil());
        $this->assertSame(['starts_at', 'ends_at'], $until->writes());
        $this->assertSame(['starts_at' => ['date', 'datetime'], 'ends_at' => ['date', 'datetime']], $until->needs());
    }

    public function test_a_calendar_writes_its_date_and_its_end(): void
    {
        $this->assertSame(['starts_at'], CalendarView::make('starts_at')->writes());
        $this->assertSame(['starts_at', 'ends_at'], CalendarView::make('starts_at')->until('ends_at')->writes());
    }
}
