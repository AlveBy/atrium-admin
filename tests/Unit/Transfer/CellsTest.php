<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit\Transfer;

use Atrium\Admin\Transfer\Cells;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cells are read the way people write them in a price list; what cannot be
 * read comes back as it was, for the field's validation to judge.
 */
final class CellsTest extends TestCase
{
    /**
     * @return array<string,array{string,bool|null}>
     */
    public static function booleans(): array
    {
        return [
            'one' => ['1', true],
            'yes in Russian, upper case, spaces' => ['  ДА ', true],
            'Y' => ['Y', true],
            'x' => ['x', true],
            'Japanese yes' => ['はい', true],
            'zero' => ['0', false],
            'no in Russian' => ['нет', false],
            'N' => ['N', false],
            'dash' => ['-', false],
            'Uzbek no' => ["yo'q", false],
            'neither' => ['maybe', null],
            'empty' => ['', null],
        ];
    }

    #[DataProvider('booleans')]
    public function test_yes_and_no_in_many_languages(string $cell, ?bool $expected): void
    {
        $this->assertSame($expected, Cells::bool($cell));
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function numbers(): array
    {
        return [
            'plain' => ['1299', '1299'],
            'decimal comma' => ['1299,5', '1299.5'],
            'decimal point' => ['1299.50', '1299.50'],
            'space thousands, comma decimals' => ['1 299,50', '1299.50'],
            'no-break space thousands' => ["1\u{00A0}299", '1299'],
            'narrow no-break space thousands' => ["1\u{202F}299,9", '1299.9'],
            'apostrophe thousands' => ["1'299.90", '1299.90'],
            'comma thousands, point decimals' => ['1,299.50', '1299.50'],
            'point thousands, comma decimals' => ['1.299,50', '1299.50'],
            'several point thousands' => ['1.299.000', '1299000'],
            'several comma thousands' => ['1,299,000', '1299000'],
            'not a number' => ['abc', 'abc'],
            'negative' => ['-5,5', '-5.5'],
        ];
    }

    #[DataProvider('numbers')]
    public function test_numbers_the_way_price_lists_write_them(string $cell, string $expected): void
    {
        $this->assertSame($expected, Cells::number($cell));
    }

    /**
     * @return array<string,array{string,bool,string}>
     */
    public static function dates(): array
    {
        return [
            'ISO' => ['2026-12-31', false, '2026-12-31'],
            'Russian' => ['31.12.2026', false, '2026-12-31'],
            'slashes' => ['31/12/2026', false, '2026-12-31'],
            'year first with points' => ['2026.12.31', false, '2026-12-31'],
            'dashes, day first' => ['31-12-2026', false, '2026-12-31'],
            'spaces around' => [' 31.12.2026 ', false, '2026-12-31'],
            'impossible day is kept' => ['31.02.2026', false, '31.02.2026'],
            'not a date' => ['soon', false, 'soon'],
            'a date drops the time' => ['31.12.2026 10:00', false, '2026-12-31'],
            'a bad time is kept in a date' => ['31.12.2026 25:00', false, '31.12.2026 25:00'],
            'with seconds' => ['31.12.2026 10:20:30', true, '2026-12-31 10:20:30'],
            'without seconds' => ['31.12.2026 10:20', true, '2026-12-31 10:20:00'],
            'date only, midnight' => ['31.12.2026', true, '2026-12-31 00:00:00'],
            'ISO with time' => ['2026-12-31 23:59:59', true, '2026-12-31 23:59:59'],
            'bad time is kept' => ['31.12.2026 25:00', true, '31.12.2026 25:00'],
        ];
    }

    #[DataProvider('dates')]
    public function test_dates_in_the_usual_formats(string $cell, bool $withTime, string $expected): void
    {
        $this->assertSame($expected, Cells::date($cell, $withTime));
    }

    public function test_several_values_by_bars_or_lines(): void
    {
        $this->assertSame(['red', 'green', 'light blue', 'black'], Cells::items(" red | green\r\nlight blue\n\n|black| "));
        $this->assertSame(['a, b'], Cells::items('a, b'));
        $this->assertSame([], Cells::items(' | '));
        $this->assertSame(['a', 'b'], Cells::items('a| |b'));
    }

    public function test_addresses_also_by_commas_semicolons_and_spaces(): void
    {
        $this->assertSame(
            ['https://a.test/1.jpg', 'https://a.test/2.jpg', '/upload/3.png', 'b/4.webp', 'c/5.gif'],
            Cells::addresses("https://a.test/1.jpg, https://a.test/2.jpg;/upload/3.png b/4.webp\n|c/5.gif"),
        );
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function formulas(): array
    {
        return [
            'equals' => ['=SUM(1+1)', "'=SUM(1+1)"],
            'plus' => ['+cmd', "'+cmd"],
            'minus' => ['-cmd', "'-cmd"],
            'at' => ['@SUM(A1)', "'@SUM(A1)"],
            'tab' => ["\tcmd", "'\tcmd"],
            'carriage return' => ["\rcmd", "'\rcmd"],
            'a number with a minus' => ['-5', '-5'],
            'a number with a plus' => ['+5.5', '+5.5'],
            'a number with an exponent' => ['-1e3', '-1e3'],
            'plain text' => ['Parka', 'Parka'],
            'a formula mark inside' => ['a=b', 'a=b'],
            'an apostrophe alone' => ["'x", "'x"],
            'empty' => ['', ''],
        ];
    }

    #[DataProvider('formulas')]
    public function test_a_cell_that_reads_like_a_formula_is_protected(string $cell, string $protected): void
    {
        $this->assertSame($protected, Cells::protect($cell));
    }

    #[DataProvider('formulas')]
    public function test_what_was_protected_is_read_back_as_it_was(string $cell, string $protected): void
    {
        $this->assertSame($cell, Cells::unprotect($protected));
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function protectedCells(): array
    {
        return [
            'one apostrophe only' => ["'=", '='],
            'an apostrophe before text' => ["'Parka", "'Parka"],
            'an apostrophe before a digit' => ["'5", "'5"],
            'an apostrophe alone' => ["'", "'"],
            'a formula without the apostrophe' => ['=1', '=1'],
            'the apostrophe inside' => ["a'=b", "a'=b"],
            'two apostrophes' => ["''=1", "''=1"],
            'at after an apostrophe' => ["'@", '@'],
            'minus after an apostrophe' => ["'-", '-'],
            'plus after an apostrophe' => ["'+", '+'],
            'tab after an apostrophe' => ["'\t", "\t"],
            'carriage return after an apostrophe' => ["'\r", "\r"],
            'empty' => ['', ''],
        ];
    }

    #[DataProvider('protectedCells')]
    public function test_only_an_apostrophe_before_a_formula_mark_is_taken_off(string $cell, string $plain): void
    {
        $this->assertSame($plain, Cells::unprotect($cell));
    }

    public function test_normalize_keeps_letters_and_digits_in_lower_case(): void
    {
        $this->assertSame('ценаруб', Cells::normalize('Цена, руб.'));
        $this->assertSame('ценаруб', Cells::normalize(' цена  руб '));
        $this->assertSame('vendorcode2', Cells::normalize('Vendor_Code (2)'));
        $this->assertSame('', Cells::normalize(' -_- '));
    }
}
