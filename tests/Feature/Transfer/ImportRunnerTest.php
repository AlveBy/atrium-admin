<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Transfer;

use Atrium\Admin\Models\DataImport;
use Atrium\Admin\Tests\Fixtures\Parcel;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Transfer\ImportRunner;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * A check or an import moves a while at a time and saves its place; only one
 * step works on it at once, and a crash stops it with a reason.
 */
final class ImportRunnerTest extends TransferTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['atrium.import.chunk' => 2]);
        File::ensureDirectoryExists($this->directory);
        $this->freezeTime();
    }

    public function test_an_import_runs_to_the_end_chunk_after_chunk_and_deletes_its_file(): void
    {
        $import = $this->import(DataImport::IMPORTING, 5);

        $this->assertFalse($this->runner()->step($import, 20));

        $import->refresh();
        $this->assertSame(DataImport::DONE, $import->status);
        $this->assertSame([5, 5], [$import->processed, $import->created]);
        $this->assertFileDoesNotExist($this->directory.'/a.csv');
        $this->assertNotNull($import->finished_at);
        $this->assertSame(5, Parcel::query()->count());
    }

    public function test_a_check_ends_checked_and_keeps_the_file_for_the_import(): void
    {
        $import = $this->import(DataImport::CHECKING, 3);

        $this->assertFalse($this->runner()->step($import, 20));

        $import->refresh();
        $this->assertSame(DataImport::CHECKED, $import->status);
        $this->assertSame([3, 3], [$import->processed, $import->created]);
        $this->assertFileExists($this->directory.'/a.csv');
        $this->assertSame(0, Parcel::query()->count());
    }

    public function test_a_step_stops_at_its_deadline_and_saves_its_place(): void
    {
        $import = $this->import(DataImport::IMPORTING, 3);

        $this->assertTrue($this->runner()->step($import, 0));

        $saved = DataImport::query()->findOrFail($import->id);
        $this->assertSame(DataImport::IMPORTING, $saved->status);
        $this->assertSame(1, $saved->processed);
        $this->assertSame(strlen("2,P-2,\"Parcel 2\"\n"), $saved->position);
        $this->assertNull($saved->finished_at);

        $this->assertTrue($this->runner()->step($saved, 0));
        $this->assertFalse($this->runner()->step($saved, 0));
        $this->assertSame(DataImport::DONE, $saved->refresh()->status);
        $this->assertSame(['P-2', 'P-3', 'P-4'], Parcel::query()->orderBy('id')->pluck('article')->all());
    }

    public function test_a_step_goes_on_past_a_chunk_while_there_is_time(): void
    {
        $import = $this->import(DataImport::IMPORTING, 3);
        // The first row takes the whole time: the rest of the chunk waits.
        Parcel::created(fn () => $this->travel(30)->seconds());

        $this->assertTrue($this->runner()->step($import, 20));

        $this->assertSame(1, $import->refresh()->processed);
    }

    public function test_a_busy_import_is_left_alone(): void
    {
        $import = $this->import(DataImport::IMPORTING, 2);
        $lock = Cache::lock('atrium-import:'.$import->id, 10);
        $lock->get();

        $this->assertTrue($this->runner()->step($import, 20));
        $this->assertSame(0, $import->refresh()->processed);

        $lock->release();
        $this->assertFalse($this->runner()->step($import, 20));
        $this->assertTrue(Cache::lock('atrium-import:'.$import->id, 10)->get());
    }

    public function test_the_lock_outlives_the_step_by_a_minute(): void
    {
        $import = $this->import(DataImport::CHECKED, 2);
        $lock = Cache::lock('another', 80);
        Cache::partialMock()->shouldReceive('lock')->once()->with('atrium-import:'.$import->id, 80)->andReturn($lock);

        $this->assertFalse($this->runner()->step($import, 20));
    }

    public function test_an_import_that_is_not_running_is_not_moved(): void
    {
        $import = $this->import(DataImport::CHECKED, 2);

        $this->assertFalse($this->runner()->step($import, 20));
        $this->assertSame(0, $import->refresh()->processed);
    }

    public function test_the_import_is_read_again_before_the_step(): void
    {
        $import = $this->import(DataImport::IMPORTING, 2);
        DataImport::query()->whereKey($import->id)->update(['status' => DataImport::CANCELLED]);

        $this->assertFalse($this->runner()->step($import, 20));
        $this->assertSame(0, $import->refresh()->processed);
        $this->assertSame(0, Parcel::query()->count());
    }

    public function test_a_crash_stops_the_import_with_a_reason_and_is_logged(): void
    {
        Exceptions::fake();
        $import = $this->import(DataImport::IMPORTING, 3);
        Parcel::creating(static fn (Parcel $parcel) => $parcel->article === 'P-3' ? throw new RuntimeException('Disk full') : null);

        $this->assertFalse($this->runner()->step($import, 20));

        $import->refresh();
        $this->assertSame(DataImport::FAILED, $import->status);
        $this->assertSame('The import stopped because of an error on the server. It has been logged; what had been imported stays.', $import->message);
        $this->assertSame(1, $import->processed);
        $this->assertNotNull($import->finished_at);
        $this->assertFileExists($this->directory.'/a.csv');
        Exceptions::assertReported(static fn (RuntimeException $crash): bool => $crash->getMessage() === 'Disk full');
    }

    public function test_a_module_that_is_gone_stops_the_import_with_a_reason(): void
    {
        $import = $this->import(DataImport::IMPORTING, 2, ['module' => 'ghosts']);

        $this->assertFalse($this->runner()->step($import, 20));

        $import->refresh();
        $this->assertSame(DataImport::FAILED, $import->status);
        $this->assertSame('The module "ghosts" is not there any more.', $import->message);
        $this->assertSame(0, $import->processed);
        $this->assertNotNull($import->finished_at);
        $this->assertFileExists($this->directory.'/a.csv');
        $this->assertSame(0, Parcel::query()->count());
    }

    public function test_a_key_the_module_has_no_field_for_stops_the_import_with_a_reason(): void
    {
        $import = $this->import(DataImport::CHECKING, 2, ['options' => ['key' => 'ghost', 'mode' => 'both']]);

        $this->assertFalse($this->runner()->step($import, 20));

        $import->refresh();
        $this->assertSame(DataImport::FAILED, $import->status);
        $this->assertSame('The records cannot be found by "ghost": the module has no such field.', $import->message);
        $this->assertSame(0, $import->processed);
    }

    public function test_an_import_of_another_module_goes_to_that_module(): void
    {
        $import = $this->import(DataImport::IMPORTING, 2, ['module' => 'posts', 'headers' => ['ID', 'Name'], 'mapping' => ['id', 'name'], 'options' => ['key' => 'id', 'mode' => 'both']]);
        // The file the helper wrote is replaced: posts have ids and names.
        File::put($this->directory.'/a.csv', "2,1,Hello\n3,2,World\n");

        $this->assertFalse($this->runner()->step($import, 20));

        $this->assertSame(DataImport::DONE, $import->refresh()->status);
        $this->assertSame(['Hello', 'World'], Post::query()->orderBy('id')->pluck('name')->all());
        $this->assertSame(0, Parcel::query()->count());
    }

    private function runner(): ImportRunner
    {
        return app(ImportRunner::class);
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    private function import(string $status, int $rows, array $attributes = []): DataImport
    {
        $lines = '';

        for ($row = 2; $row < $rows + 2; $row++) {
            $lines .= "{$row},P-{$row},\"Parcel {$row}\"\n";
        }

        File::put($this->directory.'/a.csv', $lines);

        $import = DataImport::query()->create([
            'module' => 'parcels', 'status' => DataImport::NEW, 'original_name' => 'a.csv', 'file' => 'a.csv', 'format' => 'csv',
            'encoding' => 'UTF-8', 'delimiter' => ';', 'headers' => ['Article', 'Name'], 'total' => $rows,
            'mapping' => ['article', 'name'], 'options' => ['key' => 'article', 'mode' => 'both'],
            ...$attributes,
        ]);
        $import->begin($status);

        return $import;
    }
}
