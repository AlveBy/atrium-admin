<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Transfer;

use Atrium\Admin\Models\DataImport;
use Atrium\Admin\Tests\Fixtures\ApiAisle;
use Atrium\Admin\Tests\Fixtures\Parcel;
use Atrium\Admin\Transfer\Exporter;
use Atrium\Admin\Transfer\Output\CsvOutput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * The tree a column points into is read from the database once for the whole
 * file, not once for each row.
 */
final class TreeIsReadOnceTest extends TransferTestCase
{
    /**
     * How many times the tree was listed while the work ran.
     */
    private function listings(callable $work): int
    {
        $count = 0;
        DB::listen(static function ($query) use (&$count): void {
            $count += preg_match('/^select \* from [`"]api_aisles[`"]/', $query->sql);
        });

        $work();

        return $count;
    }

    private function importRows(int $rows): int
    {
        File::ensureDirectoryExists($this->directory);
        $path = $this->directory.'/in-'.$rows.'.csv';
        file_put_contents($path, "Article;Name;Aisle\n".implode('', array_map(static fn (int $n): string => "A-{$n};Parcel {$n};Europe\n", range(1, $rows))));
        $import = $this->importOf('parcels', $path, ['key' => 'article']);

        $count = $this->listings(fn () => $this->run_($import, DataImport::IMPORTING));

        $this->assertSame($rows, Parcel::query()->where('article', 'like', 'A-%')->whereNotNull('aisle_id')->count());

        return $count;
    }

    public function test_the_tree_is_read_once_for_the_cells_of_many_rows(): void
    {
        ApiAisle::query()->create(['name' => 'Europe']);
        $one = $this->importRows(1);
        Parcel::query()->delete();
        $many = $this->importRows(4);

        // Neither the reading of the cells nor the saving lists it again for
        // each row: saving asks the fields their type, not their records.
        $this->assertGreaterThan(0, $one);
        $this->assertSame($one, $many);
    }

    public function test_the_tree_is_listed_as_often_for_many_records_as_for_one(): void
    {
        $europe = ApiAisle::query()->create(['name' => 'Europe']);
        Parcel::query()->create(['article' => 'A-1', 'name' => 'One', 'aisle_id' => $europe->id]);
        $write = fn () => Exporter::for(Parcel::class)->write(new CsvOutput($this->directory.'/out.csv'), Parcel::query());
        File::ensureDirectoryExists($this->directory);

        $one = $this->listings($write);

        foreach (range(2, 5) as $n) {
            Parcel::query()->create(['article' => "A-{$n}", 'name' => "Parcel {$n}", 'aisle_id' => $europe->id]);
        }
        $many = $this->listings($write);

        $this->assertGreaterThan(0, $one);
        $this->assertSame($one, $many);
        $this->assertStringContainsString('A-5;', (string) file_get_contents($this->directory.'/out.csv'));
    }
}
