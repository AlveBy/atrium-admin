<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit\Transfer;

use Atrium\Admin\Models\DataImport;
use Atrium\Admin\Tests\TestCase;
use Atrium\Admin\Transfer\RowProblem;
use Atrium\Admin\Transfer\Trees;

/**
 * Names are compared without regard to case in every alphabet, not only in
 * Latin, and a path with empty parts in it ("Europe//Trains", "/Europe") is
 * read as the path without them.
 */
final class TreesCaseAndGapsTest extends TestCase
{
    /** @var array<int,array{0:int|null,1:string}> */
    private array $made = [];

    /**
     * Одежда (1) > Куртки (2); Ёлки (3); Europe (4).
     *
     * @return array<int,array<string,mixed>>
     */
    private function tree(): array
    {
        return [
            ['value' => 1, 'label' => 'Одежда', 'children' => [['value' => 2, 'label' => 'Куртки']]],
            ['value' => 3, 'label' => 'Ёлки'],
            ['value' => 4, 'label' => 'Europe'],
        ];
    }

    private function trees(bool $create = false): Trees
    {
        $next = 100;
        $make = $create ? function (?int $parent, string $name) use (&$next): int {
            $this->made[] = [$parent, $name];

            return ++$next;
        } : null;

        return new Trees($this->tree(), 'Aisle', (new DataImport(['options' => []]))->forceFill(['nodes' => 0]), false, $make);
    }

    public function test_a_single_name_in_capitals_finds_a_name_in_another_alphabet(): void
    {
        $this->assertSame(3, $this->trees()->find('ЁЛКИ'));
        $this->assertSame(3, $this->trees()->find('ёлки'));
        $this->assertSame(2, $this->trees()->find('КУРТКИ'));
    }

    public function test_the_parts_of_a_path_in_capitals_find_names_in_another_alphabet(): void
    {
        $this->assertSame(2, $this->trees()->find('ОДЕЖДА / КУРТКИ'));
        $this->assertSame(2, $this->trees()->find('одежда / куртки'));
    }

    public function test_a_name_that_is_not_there_in_any_case_is_an_error(): void
    {
        $this->expectException(RowProblem::class);

        $this->trees()->find('ОДЕЖДА / ШАПКИ');
    }

    public function test_what_was_made_with_capitals_is_found_in_small_letters_and_made_once(): void
    {
        $trees = $this->trees(create: true);

        $this->assertSame(101, $trees->find('Африка'));
        $this->assertSame(101, $trees->find('африка'));
        $this->assertSame(101, $trees->find('АФРИКА'));
        $this->assertSame(102, $trees->find('Африка / Нил'));
        $this->assertSame(102, $trees->find('африка / нил'));
        $this->assertSame([[null, 'Африка'], [101, 'Нил']], $this->made);
    }

    public function test_a_leading_separator_changes_nothing(): void
    {
        $this->assertSame(4, $this->trees()->find('/Europe'));
        $this->assertSame(4, $this->trees()->find(' / Europe / '));
        $this->assertSame(2, $this->trees()->find('/Одежда/Куртки'));
    }

    public function test_an_empty_part_inside_a_path_changes_nothing(): void
    {
        $trees = $this->trees(create: true);

        $this->assertSame(101, $trees->find('Europe//Trains'));
        $this->assertSame([[4, 'Trains']], $this->made);
        $this->assertSame(101, $trees->find('Europe / Trains'));
        $this->assertSame(102, $trees->find('Europe / / Trains / / Night'));
        $this->assertSame([[4, 'Trains'], [101, 'Night']], $this->made);
        $this->assertSame('Europe / Trains / Night', $trees->path(102));
    }

    public function test_a_tree_built_without_the_check_flag_makes_what_is_missing(): void
    {
        $import = (new DataImport(['options' => []]))->forceFill(['nodes' => 0]);
        $trees = new Trees($this->tree(), 'Aisle', $import, create: function (?int $parent, string $name): int {
            $this->made[] = [$parent, $name];

            return 500;
        });

        $this->assertSame(500, $trees->find('Europe / Trains'));
        $this->assertSame([[4, 'Trains']], $this->made);
        $this->assertSame(1, $import->nodes);
    }

    public function test_a_label_that_is_a_number_is_a_name_like_any_other(): void
    {
        $trees = new Trees(
            [['value' => 7, 'label' => 2026, 'children' => [['value' => 8, 'label' => 12]]]],
            'Aisle',
            (new DataImport(['options' => []]))->forceFill(['nodes' => 0]),
        );

        $this->assertSame(7, $trees->find('2026'));
        $this->assertSame(8, $trees->find('2026 / 12'));
        $this->assertSame('2026 / 12', $trees->path(8));
    }
}
