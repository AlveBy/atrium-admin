<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Transfer;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Models\DataImport;
use Atrium\Admin\Tests\Fixtures\Parcel;
use Atrium\Admin\Transfer\Hosts;
use Atrium\Admin\Transfer\RecordWriter;
use Atrium\Admin\Transfer\RowImporter;
use Atrium\Admin\Transfer\Target;
use Atrium\Admin\Transfer\Targets;

/**
 * A column a module reads its own way is one column of the row: the ones
 * after it are read as usual.
 */
final class RowImporterResolvedTargetOrderTest extends TransferTestCase
{
    public function test_the_columns_after_one_a_module_reads_itself_are_written_too(): void
    {
        Hook::filter('atrium.import.targets', static function (array $targets): array {
            $targets['shout'] = new Target('shout', 'Shout', resolve: static fn (string $cell): array => ['label' => 'new']);

            return $targets;
        });
        $mapping = ['article', 'shout', 'name', 'code', 'price'];
        $import = DataImport::query()->create([
            'module' => 'parcels', 'status' => DataImport::NEW, 'original_name' => 'a.csv', 'file' => 'a.csv', 'format' => 'csv',
            'encoding' => 'UTF-8', 'delimiter' => ';', 'headers' => ['a', 'b', 'c', 'd', 'e'], 'total' => 1,
            'mapping' => $mapping, 'options' => ['key' => 'article', 'mode' => 'both'],
        ]);
        $import->begin(DataImport::IMPORTING);

        (new RowImporter($import, Parcel::class, Targets::for(Parcel::class), new RecordWriter(Parcel::class), new Hosts(static fn (): array => ['10.0.0.1'])))
            ->import(2, ['A-1', 'hey', 'Parka', 'the-code', '12']);

        $this->assertSame([], $import->errors);
        $parcel = Parcel::query()->sole();
        $this->assertSame('Parka', $parcel->name);
        $this->assertSame(['A-1', 'new', 'the-code', '12.00'], [$parcel->article, $parcel->label, $parcel->code, $parcel->price]);
    }
}
