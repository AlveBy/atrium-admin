<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit\Columns;

use Atrium\Admin\Columns\DateColumn;
use Atrium\Admin\Tests\TestCase;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use PHPUnit\Framework\Attributes\DataProvider;

final class DateColumnTest extends TestCase
{
    public function test_by_default_it_shows_the_day_without_time_or_timezone(): void
    {
        $array = DateColumn::make('created_at')->toArray();

        $this->assertSame('d.m.Y', $array['format']);
        $this->assertFalse($array['diffForHumans']);
        $this->assertNull($array['timezone']);
    }

    /**
     * @return array<string, array{\Closure(DateColumn):DateColumn, string}>
     */
    public static function formats(): array
    {
        return [
            'date' => [fn (DateColumn $c): DateColumn => $c, '05.03.2026'],
            'date and time' => [fn (DateColumn $c): DateColumn => $c->datetime(), '05.03.2026 14:07'],
            'time only' => [fn (DateColumn $c): DateColumn => $c->time(), '14:07'],
            'own format' => [fn (DateColumn $c): DateColumn => $c->format('Y/m/d H:i:s'), '2026/03/05 14:07:09'],
        ];
    }

    #[DataProvider('formats')]
    public function test_a_date_is_shown_in_the_chosen_format(\Closure $configure, string $expected): void
    {
        $column = $configure(DateColumn::make('created_at'));

        $this->assertSame($expected, $column->formatValue('2026-03-05 14:07:09', null));
        $this->assertSame($expected, $column->formatValue(Carbon::parse('2026-03-05 14:07:09'), null));
    }

    public function test_the_chosen_format_reaches_the_list(): void
    {
        $this->assertSame('d.m.Y H:i', DateColumn::make('a')->datetime()->toArray()['format']);
        $this->assertSame('H:i', DateColumn::make('a')->time()->toArray()['format']);
        $this->assertSame('Y', DateColumn::make('a')->format('Y')->toArray()['format']);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function empties(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'zero' => [0],
            'false' => [false],
        ];
    }

    #[DataProvider('empties')]
    public function test_an_empty_date_shows_nothing(mixed $value): void
    {
        $this->assertNull(DateColumn::make('published_at')->datetime()->formatValue($value, null));
    }

    public function test_a_date_is_moved_into_the_chosen_timezone(): void
    {
        $column = DateColumn::make('created_at')->datetime()->timezone('Asia/Tokyo');

        $this->assertSame('Asia/Tokyo', $column->toArray()['timezone']);
        $this->assertSame('05.03.2026 23:07', $column->formatValue(Carbon::parse('2026-03-05 14:07:00', 'UTC'), null));
        $this->assertSame('05.03.2026 14:07', DateColumn::make('created_at')->datetime()->formatValue(Carbon::parse('2026-03-05 14:07:00', 'UTC'), null));
    }

    public function test_a_relative_date_says_how_long_ago_it_was(): void
    {
        $this->travelTo(Carbon::parse('2026-03-05 12:00:00'));

        $column = DateColumn::make('created_at')->diffForHumans();

        $this->assertTrue($column->toArray()['diffForHumans']);
        $this->assertSame('3 hours ago', $column->formatValue('2026-03-05 09:00:00', null));
        $this->assertSame('05.03.2026', $column->diffForHumans(false)->formatValue('2026-03-05 09:00:00', null));
    }

    public function test_a_relative_date_is_counted_after_moving_to_the_timezone(): void
    {
        $this->travelTo(Carbon::parse('2026-03-05 12:00:00', 'UTC'));

        $this->assertSame('2 days ago', DateColumn::make('created_at')->timezone('Asia/Tokyo')->diffForHumans()->formatValue('2026-03-03 12:00:00', null));
    }

    public function test_text_that_is_not_a_date_is_refused(): void
    {
        $this->expectException(InvalidFormatException::class);

        DateColumn::make('created_at')->formatValue('not a date', null);
    }
}
