<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit\Fields;

use Atrium\Admin\Fields\DateField;
use Atrium\Admin\Fields\DateTimeField;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;

final class DateFieldsTest extends FieldTestCase
{
    public function test_a_date_field_stores_a_day_and_shows_it_the_european_way(): void
    {
        $field = DateField::make('published_on');
        $array = $field->toArray();

        $this->assertSame('date', $field->getType());
        $this->assertNull($array['minDate']);
        $this->assertNull($array['maxDate']);
        $this->assertSame('Y-m-d', $array['format']);
        $this->assertSame('d.m.Y', $array['displayFormat']);
        $this->assertSame(['nullable', 'date'], $field->getValidationRules());
    }

    public function test_a_date_field_takes_its_formats(): void
    {
        $array = DateField::make('a')->format('d/m/Y')->displayFormat('j F Y')->toArray();

        $this->assertSame('d/m/Y', $array['format']);
        $this->assertSame('j F Y', $array['displayFormat']);
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function dates(): array
    {
        return [
            'a day' => ['2026-03-05', true],
            'a day and time' => ['2026-03-05 10:00:00', true],
            'empty, the field is optional' => [null, true],
            'the 30th of February' => ['2026-02-30', false],
            'not a date' => ['tomorrow-ish', false],
            'an array' => [['2026-03-05'], false],
        ];
    }

    #[DataProvider('dates')]
    public function test_a_date_field_refuses_what_is_not_a_date(mixed $value, bool $passes): void
    {
        $this->assertSame($passes, $this->passes(DateField::make('published_on'), $value));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function bounded(): array
    {
        return [
            'the first day allowed' => ['2026-03-01', true],
            'the last day allowed' => ['2026-03-31', true],
            'inside' => ['2026-03-15', true],
            'the day before' => ['2026-02-28', false],
            'the day after' => ['2026-04-01', false],
        ];
    }

    #[DataProvider('bounded')]
    public function test_a_date_outside_the_range_is_refused(string $value, bool $passes): void
    {
        $field = DateField::make('starts_on')->minDate('2026-03-01')->maxDate('2026-03-31');

        $this->assertSame('2026-03-01', $field->toArray()['minDate']);
        $this->assertSame('2026-03-31', $field->toArray()['maxDate']);
        $this->assertSame(['nullable', 'date', 'after_or_equal:2026-03-01', 'before_or_equal:2026-03-31'], $field->getValidationRules());
        $this->assertSame($passes, $this->passes($field, $value));
    }

    public function test_only_a_first_or_only_a_last_day_can_be_set(): void
    {
        $this->assertSame(['nullable', 'date', 'after_or_equal:2026-03-01'], DateField::make('a')->minDate('2026-03-01')->getValidationRules());
        $this->assertSame(['nullable', 'date', 'before_or_equal:2026-03-31'], DateField::make('a')->maxDate('2026-03-31')->getValidationRules());
    }

    public function test_a_date_can_be_limited_to_the_future_or_to_the_past(): void
    {
        $this->travelTo(Carbon::parse('2026-03-05 12:00:00'));

        $future = DateField::make('departs_on')->minToday();
        $past = DateField::make('born_on')->maxToday();

        $this->assertSame('today', $future->toArray()['minDate']);
        $this->assertSame('today', $past->toArray()['maxDate']);
        $this->assertTrue($this->passes($future, '2026-03-05'));
        $this->assertTrue($this->passes($future, '2026-03-06'));
        $this->assertFalse($this->passes($future, '2026-03-04'));
        $this->assertTrue($this->passes($past, '2026-03-05'));
        $this->assertTrue($this->passes($past, '2026-03-04'));
        $this->assertFalse($this->passes($past, '2026-03-06'));
    }

    public function test_a_date_and_time_field_stores_minutes_in_steps_of_five(): void
    {
        $field = DateTimeField::make('starts_at');
        $array = $field->toArray();

        $this->assertSame('datetime', $field->getType());
        $this->assertSame('datetimeField', $field->getComponent());
        $this->assertSame('Y-m-d H:i:s', $array['format']);
        $this->assertSame('d.m.Y H:i', $array['displayFormat']);
        $this->assertFalse($array['showSeconds']);
        $this->assertSame(5, $array['minuteStep']);
        $this->assertArrayHasKey('minDate', $array, 'the date settings are kept');
        $this->assertSame(['nullable', 'date'], $field->getValidationRules());
    }

    public function test_a_date_and_time_field_can_show_seconds(): void
    {
        $array = DateTimeField::make('starts_at')->withSeconds()->minuteStep(15)->toArray();

        $this->assertTrue($array['showSeconds']);
        $this->assertSame('d.m.Y H:i:s', $array['displayFormat']);
        $this->assertSame(15, $array['minuteStep']);
    }

    public function test_switching_seconds_off_leaves_the_display_format_alone(): void
    {
        $array = DateTimeField::make('starts_at')->displayFormat('H:i d.m')->withSeconds(false)->toArray();

        $this->assertFalse($array['showSeconds']);
        $this->assertSame('H:i d.m', $array['displayFormat']);
    }

    public function test_a_date_and_time_field_keeps_the_range_rules(): void
    {
        $field = DateTimeField::make('starts_at')->minDate('2026-03-05 10:00');

        $this->assertTrue($this->passes($field, '2026-03-05 10:00'));
        $this->assertFalse($this->passes($field, '2026-03-05 09:59'));
    }
}
