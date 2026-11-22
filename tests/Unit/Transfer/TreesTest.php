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
use Closure;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A cell points into a tree of records by a name or by a path; an export
 * writes the path. What is not there is an error, or is made when the module
 * says how.
 */
final class TreesTest extends TestCase
{
    /** @var array<int,array{0:int|null,1:string}> */
    private array $made = [];

    /**
     * Europe (1) > Day trips (2), Cruises (3); Asia (4) > Cruises (5); Day trips (6); Dupe (7) and Dupe (8) at the top; Ёлки (9).
     *
     * @return array<int,array<string,mixed>>
     */
    private function tree(): array
    {
        return [
            ['value' => 1, 'label' => 'Europe', 'children' => [['value' => 2, 'label' => 'Day trips'], ['value' => 3, 'label' => 'Cruises']]],
            ['value' => 4, 'label' => 'Asia', 'children' => [['value' => 5, 'label' => 'Cruises']]],
            ['value' => 6, 'label' => 'Day trips'],
            ['value' => 7, 'label' => 'Dupe'],
            ['value' => 8, 'label' => 'Dupe'],
            ['value' => '9', 'label' => 'Ёлки'],
        ];
    }

    /**
     * @param  array<string,mixed>  $options  of the import
     */
    private function trees(array $options = [], bool $check = false, bool $create = false, ?DataImport $import = null): Trees
    {
        $import ??= $this->blank($options);
        $next = 100;
        $make = $create ? function (?int $parent, string $name) use (&$next): int {
            $this->made[] = [$parent, $name];

            return ++$next;
        } : null;

        return new Trees($this->tree(), 'Aisle', $import, $check, $make);
    }

    /**
     * @param  array<string,mixed>  $options
     */
    private function blank(array $options = []): DataImport
    {
        return (new DataImport(['options' => $options]))->forceFill(['nodes' => 0]);
    }

    private function saved(): DataImport
    {
        return DataImport::query()->create([
            'module' => 'parcels', 'status' => DataImport::NEW, 'original_name' => 'a.csv', 'file' => 'a.csv', 'format' => 'csv',
            'encoding' => 'UTF-8', 'delimiter' => ';', 'headers' => ['Aisle'], 'total' => 1,
        ])->refresh();
    }

    private function problem(Closure $call): string
    {
        try {
            $call();
        } catch (RowProblem $problem) {
            return $problem->getMessage();
        }

        $this->fail('The row should have been refused.');
    }

    public function test_a_name_is_found_anywhere_in_the_tree_whatever_its_case(): void
    {
        $trees = $this->trees();

        $this->assertSame(1, $trees->find('Europe'));
        $this->assertSame(1, $trees->find('  eUROPE  '));
        $this->assertSame(5, $trees->find('Asia / Cruises'));
        $this->assertSame(4, $trees->find('ASIA'));
        $this->assertSame(9, $trees->find('ёлки'));
    }

    public function test_a_name_that_several_nodes_have_is_the_one_at_the_top_if_only_one_is(): void
    {
        $this->assertSame(6, $this->trees()->find('Day trips'));
        $this->assertSame(6, $this->trees()->find('day trips'));
    }

    public function test_a_name_that_several_nodes_have_and_none_is_at_the_top_is_ambiguous(): void
    {
        $message = $this->problem(fn () => $this->trees()->find('Cruises'));

        $this->assertSame('"Aisle": more than one is called "Cruises": write its path, "Parent / Cruises".', $message);
    }

    public function test_two_nodes_at_the_top_with_one_name_are_ambiguous_too(): void
    {
        $message = $this->problem(fn () => $this->trees()->find('dupe'));

        $this->assertSame('"Aisle": more than one is called "dupe": write its path, "Parent / dupe".', $message);
    }

    public function test_a_path_goes_down_from_the_top(): void
    {
        $trees = $this->trees();

        $this->assertSame(3, $trees->find('Europe / Cruises'));
        $this->assertSame(3, $trees->find('europe/cruises'));
        $this->assertSame(2, $trees->find(' Europe /  Day trips '));
        $this->assertSame(3, $trees->find('Europe//Cruises'));
        $this->assertSame(2, $trees->find('Europe / Day trips / '));
    }

    public function test_the_separator_is_the_imports_option(): void
    {
        $trees = $this->trees(['separator' => '>']);

        $this->assertSame(3, $trees->find('Europe > Cruises'));
        $this->assertSame(3, $trees->find('Europe>Cruises'));
        $this->assertSame('"Aisle": there is no "Europe / Cruises".', $this->problem(fn () => $trees->find('Europe / Cruises')));
    }

    public function test_a_path_whose_parent_is_not_the_one_named_is_not_found(): void
    {
        $this->assertSame('"Aisle": there is no "Asia / Day trips".', $this->problem(fn () => $this->trees()->find('Asia / Day trips')));
        $this->assertSame('"Aisle": there is no "Day trips / Europe".', $this->problem(fn () => $this->trees()->find('Day trips / Europe')));
    }

    /**
     * @return array<string,array{string}>
     */
    public static function nothing(): array
    {
        return ['empty' => [''], 'spaces' => ['   '], 'a separator' => ['/'], 'separators and spaces' => [' / / ']];
    }

    #[DataProvider('nothing')]
    public function test_a_cell_with_no_name_in_it_is_an_error(string $value): void
    {
        $this->assertSame('"Aisle": there is no "'.$value.'".', $this->problem(fn () => $this->trees(create: true)->find($value)));
        $this->assertSame([], $this->made);
    }

    public function test_what_is_missing_is_an_error_when_the_module_cannot_make_it(): void
    {
        $this->assertSame('"Aisle": there is no "Africa".', $this->problem(fn () => $this->trees()->find('Africa')));
        $this->assertSame('"Aisle": there is no "Europe / Trains".', $this->problem(fn () => $this->trees()->find('Europe / Trains')));
    }

    public function test_a_missing_name_is_made_at_the_top(): void
    {
        $import = $this->blank();
        $trees = $this->trees(create: true, import: $import);

        $this->assertSame(101, $trees->find('Africa'));
        $this->assertSame([[null, 'Africa']], $this->made);
        $this->assertSame(1, $import->nodes);
    }

    public function test_what_was_made_is_found_the_next_time_and_made_once(): void
    {
        $import = $this->blank();
        $trees = $this->trees(create: true, import: $import);

        $this->assertSame(101, $trees->find('Africa'));
        $this->assertSame(101, $trees->find('africa'));
        $this->assertSame(101, $trees->find('Africa'));
        $this->assertSame([[null, 'Africa']], $this->made);
        $this->assertSame(1, $import->nodes);
        $this->assertSame('Africa', $trees->path(101));
    }

    public function test_the_missing_part_of_a_path_is_made_down_from_the_last_node_there(): void
    {
        $import = $this->blank();
        $trees = $this->trees(create: true, import: $import);

        $this->assertSame(102, $trees->find('Europe / Trains / Night'));
        $this->assertSame([[1, 'Trains'], [101, 'Night']], $this->made);
        $this->assertSame(2, $import->nodes);
        $this->assertSame('Europe / Trains / Night', $trees->path(102));
        $this->assertSame(102, $trees->find('europe / trains / night'));
        $this->assertSame(2, $import->nodes);
    }

    public function test_a_whole_new_path_starts_at_the_top(): void
    {
        $import = $this->blank();
        $trees = $this->trees(create: true, import: $import);

        $this->assertSame(102, $trees->find('Africa / Nile'));
        $this->assertSame([[null, 'Africa'], [101, 'Nile']], $this->made);
        $this->assertSame(2, $import->nodes);
    }

    public function test_a_path_that_is_all_there_makes_nothing(): void
    {
        $import = $this->blank();
        $trees = $this->trees(create: true, import: $import);

        $this->assertSame(3, $trees->find('Europe / Cruises'));
        $this->assertSame(1, $trees->find('Europe'));
        $this->assertSame([], $this->made);
        $this->assertSame(0, $import->nodes);
    }

    public function test_a_check_counts_what_it_would_make_and_makes_nothing(): void
    {
        $import = $this->saved();
        $trees = $this->trees(check: true, create: true, import: $import);

        $this->assertNull($trees->find('Africa'));
        $this->assertSame(1, $import->nodes);
        $this->assertNull($trees->find('Europe / Trains / Night'));
        $this->assertSame(3, $import->nodes);
        $this->assertSame([], $this->made);
        $this->assertSame('', $trees->path(101));
    }

    public function test_a_check_counts_a_node_once_however_often_the_file_names_it(): void
    {
        $import = $this->saved();
        $trees = $this->trees(check: true, create: true, import: $import);

        $trees->find('Europe / Trains / Night');
        $this->assertSame(2, $import->nodes);

        $trees->find('europe / TRAINS / night');
        $this->assertSame(2, $import->nodes);

        // A sibling under the same new parent is one more; the parent is counted already.
        $trees->find('Europe / Trains / Day');
        $this->assertSame(3, $import->nodes);

        $trees->find('Europe / Trains');
        $this->assertSame(3, $import->nodes);

        foreach (['Europe/Trains', 'Europe/Trains/Night', 'Europe/Trains/Day'] as $path) {
            $this->assertTrue($import->seen('node', $path), $path);
        }

        $this->assertFalse($import->seen('node', 'Europe'));
        $this->assertSame(3, DB::table('admin_import_keys')->where('import_id', $import->id)->where('kind', 'node')->count());
    }

    public function test_a_check_finds_what_is_there_and_counts_nothing(): void
    {
        $import = $this->saved();
        $trees = $this->trees(check: true, create: true, import: $import);

        $this->assertSame(3, $trees->find('Europe / Cruises'));
        $this->assertSame(1, $trees->find('Europe'));
        $this->assertSame(0, $import->nodes);
    }

    public function test_a_check_that_cannot_make_what_is_missing_says_so(): void
    {
        $import = $this->saved();

        $message = $this->problem(fn () => $this->trees(check: true, import: $import)->find('Africa'));

        $this->assertSame('"Aisle": there is no "Africa".', $message);
        $this->assertSame(0, $import->nodes);
        $this->assertSame(0, DB::table('admin_import_keys')->count());
    }

    public function test_the_path_an_export_writes_goes_from_the_top_down(): void
    {
        $trees = $this->trees();

        $this->assertSame('Europe / Day trips', $trees->path(2));
        $this->assertSame('Europe', $trees->path(1));
        $this->assertSame('Day trips', $trees->path(6));
        $this->assertSame('Asia > Cruises', $trees->path(5, ' > '));
        $this->assertSame('Ёлки', $trees->path(9));
    }

    public function test_no_node_and_an_unknown_node_have_no_path(): void
    {
        $trees = $this->trees();

        $this->assertSame('', $trees->path(null));
        $this->assertSame('', $trees->path(0));
        $this->assertSame('', $trees->path(404));
    }

    public function test_a_path_the_export_wrote_is_found_again(): void
    {
        $trees = $this->trees();

        foreach ([1, 2, 3, 4, 5, 6, 9] as $id) {
            $this->assertSame($id, $trees->find($trees->path($id)), "node {$id}");
        }
    }

    public function test_a_tree_that_leads_back_into_itself_ends_the_walk_at_the_node_met_twice(): void
    {
        $trees = new Trees([
            ['value' => 1, 'label' => 'One', 'children' => [['value' => 2, 'label' => 'Two', 'children' => [['value' => 1, 'label' => 'Again']]]]],
        ], 'Aisle', $this->blank(), false);

        $this->assertSame('Two / Again', $trees->path(1));
        $this->assertSame('Again / Two', $trees->path(2));
    }
}
