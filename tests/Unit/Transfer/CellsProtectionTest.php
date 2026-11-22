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
 * A cell of an exported CSV that a spreadsheet would run as a formula gets an
 * apostrophe, and the import takes it off again; nothing else is touched.
 */
final class CellsProtectionTest extends TestCase
{
    /**
     * @return array<string,array{string}>
     */
    public static function triggers(): array
    {
        return [
            'equals' => ['=SUM(1+1)'],
            'plus' => ['+cmd|calc'],
            'minus' => ['-cmd'],
            'at' => ['@SUM(1)'],
            'tab' => ["\t=1+1"],
            'carriage return' => ["\r=1+1"],
            'a lone minus' => ['-'],
            'a lone equals' => ['='],
            'minus and a letter that looks like an exponent' => ['-e5'],
            'a number with a formula behind it' => ['=5'],
            'a number with text behind it' => ['-5 apples'],
        ];
    }

    #[DataProvider('triggers')]
    public function test_a_cell_that_starts_like_a_formula_gets_an_apostrophe(string $cell): void
    {
        $this->assertSame("'".$cell, Cells::protect($cell));
    }

    /**
     * @return array<string,array{string}>
     */
    public static function harmless(): array
    {
        return [
            'empty' => [''],
            'a word' => ['Parka'],
            'a space and an equals sign' => [' =1'],
            'an equals sign inside' => ['a=b'],
            'a plus inside' => ['1+1'],
            'a minus inside' => ['a-b'],
            'an at sign inside' => ['mail@example.com'],
            'an apostrophe' => ["'=x"],
            'a negative number' => ['-5'],
            'a negative decimal' => ['-1299.50'],
            'a positive number with a sign' => ['+5'],
            'an exponent' => ['-1e3'],
            'a tab before a number' => ["\t5"],
            'a carriage return before a number' => ["\r5"],
            'zero' => ['0'],
            'Cyrillic' => ['Куртка'],
        ];
    }

    #[DataProvider('harmless')]
    public function test_any_other_cell_is_written_as_it_is(string $cell): void
    {
        $this->assertSame($cell, Cells::protect($cell));
    }

    #[DataProvider('triggers')]
    public function test_the_import_takes_the_apostrophe_off_again(string $cell): void
    {
        $this->assertSame($cell, Cells::unprotect(Cells::protect($cell)));
        $this->assertSame($cell, Cells::unprotect("'".$cell));
    }

    /**
     * @return array<string,array{string}>
     */
    public static function untouched(): array
    {
        return [
            'empty' => [''],
            'a lone apostrophe' => ["'"],
            'an apostrophe and a word' => ["'Parka"],
            'an apostrophe and a digit' => ["'5"],
            'two apostrophes' => ["''=x"],
            'an apostrophe not at the start' => ["a'=x"],
            'an apostrophe after a space' => [" '=x"],
            'a formula without an apostrophe' => ['=SUM(1)'],
        ];
    }

    #[DataProvider('untouched')]
    public function test_a_cell_the_export_did_not_protect_is_read_as_it_is(string $cell): void
    {
        $this->assertSame($cell, Cells::unprotect($cell));
    }
}
