<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Transfer;

use Atrium\Admin\Models\DataImport;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;

/**
 * An import keeps its file, its settings, its counters and the rows that
 * went wrong.
 */
final class DataImportTest extends TransferTestCase
{
    public function test_files_live_in_the_configured_directory(): void
    {
        config(['atrium.import.directory' => '/var/imports/']);
        $import = $this->import(['file' => 'a.csv']);

        $this->assertSame('/var/imports', DataImport::directory());
        // Model::__callStatic would reach a protected one too, so visibility is read as declared.
        $this->assertTrue((new ReflectionMethod(DataImport::class, 'directory'))->isPublic());
        $this->assertSame('/var/imports/a.csv', $import->path());
    }

    public function test_the_directory_loses_only_the_slashes_at_its_end(): void
    {
        config(['atrium.import.directory' => '/var/imports//']);
        $this->assertSame('/var/imports', DataImport::directory());

        config(['atrium.import.directory' => '/var/imports']);
        $this->assertSame('/var/imports', DataImport::directory());

        config(['atrium.import.directory' => '/']);
        $this->assertSame('', DataImport::directory());
    }

    public function test_an_option_falls_back_to_its_default(): void
    {
        $import = $this->import(['options' => ['key' => 'code', 'create_sections' => true, 'empty' => '']]);

        $this->assertSame('code', $import->option('key'));
        $this->assertTrue($import->option('create_sections'));
        $this->assertSame('', $import->option('empty', 'x'));
        $this->assertSame('both', $import->option('mode', 'both'));
        $this->assertNull($import->option('mode'));
        $this->assertSame('/', $this->import()->option('separator', '/'));
        $this->assertSame('/', $this->import(['options' => null])->option('separator', '/'));
    }

    /**
     * @return array<string,array{string,bool,bool}>
     */
    public static function statuses(): array
    {
        return [
            'new' => [DataImport::NEW, false, true],
            'checking' => [DataImport::CHECKING, true, false],
            'checked' => [DataImport::CHECKED, false, true],
            'importing' => [DataImport::IMPORTING, true, false],
            'done' => [DataImport::DONE, false, false],
            'failed' => [DataImport::FAILED, false, true],
            'cancelled' => [DataImport::CANCELLED, false, false],
        ];
    }

    #[DataProvider('statuses')]
    public function test_running_and_open(string $status, bool $running, bool $open): void
    {
        $import = $this->import(['status' => $status]);

        $this->assertSame($running, $import->isRunning());
        $this->assertSame($open, $import->isOpen());
    }

    public function test_the_modes_are_those_the_import_knows(): void
    {
        $this->assertSame(['both', 'create', 'update'], DataImport::MODES);
    }

    public function test_a_value_is_remembered_once_per_kind_and_import_case_aside(): void
    {
        $import = $this->import();
        $other = $this->import();

        $this->assertFalse($import->seen('record', 'A-1'));
        $this->assertTrue($import->remember('record', 'A-1'));
        $this->assertFalse($import->remember('record', 'a-1'));
        $this->assertTrue($import->seen('record', 'a-1'));
        $this->assertFalse($import->seen('node', 'A-1'));
        $this->assertFalse($other->seen('record', 'A-1'));
        $this->assertTrue($other->remember('record', 'A-1'));
        $this->assertTrue($import->remember('node', 'Ключ'));
        $this->assertTrue($import->seen('node', 'КЛЮЧ'));
        $this->assertFalse($import->remember('node', 'кЛЮЧ'));

        $import->forgetSeen();

        $this->assertFalse($import->seen('record', 'A-1'));
        $this->assertTrue($other->seen('record', 'A-1'));
    }

    public function test_begin_starts_from_the_first_row(): void
    {
        $this->travelTo('2026-11-22 10:00:00');
        $import = $this->import();
        $import->forceFill([
            'position' => 500, 'processed' => 9, 'created' => 1, 'updated' => 2, 'skipped' => 3, 'failed' => 4, 'nodes' => 5,
            'errors' => [['row' => 2, 'skipped' => false, 'messages' => ['x']]], 'message' => 'Broken', 'finished_at' => now(),
        ])->save();
        $import->remember('record', 'A-1');

        $import->begin(DataImport::IMPORTING);
        $import->refresh();

        $this->assertSame(DataImport::IMPORTING, $import->status);
        $this->assertSame([0, 0, 0, 0, 0, 0, 0], [$import->position, $import->processed, $import->created, $import->updated, $import->skipped, $import->failed, $import->nodes]);
        $this->assertSame([], $import->errors);
        $this->assertNull($import->message);
        $this->assertSame('2026-11-22 10:00:00', $import->started_at?->toDateTimeString());
        $this->assertNull($import->finished_at);
        $this->assertFalse($import->seen('record', 'A-1'));
    }

    public function test_a_row_is_noted_and_the_list_stops_at_the_limit(): void
    {
        config(['atrium.import.errors' => 2]);
        $import = $this->import();

        $import->note(2, true, ['exists']);
        $import->note(3, false, ['bad']);
        $import->note(4, false, ['worse']);

        $this->assertSame(1, $import->skipped);
        $this->assertSame(2, $import->failed);
        $this->assertSame([
            ['row' => 2, 'skipped' => true, 'messages' => ['exists']],
            ['row' => 3, 'skipped' => false, 'messages' => ['bad']],
        ], $import->errors);
    }

    public function test_a_row_is_listed_while_the_list_is_one_short_of_the_limit(): void
    {
        config(['atrium.import.errors' => 1]);
        $import = $this->import();

        $import->note(2, false, ['bad']);
        $import->note(3, false, ['worse']);

        $this->assertSame(2, $import->failed);
        $this->assertSame([['row' => 2, 'skipped' => false, 'messages' => ['bad']]], $import->errors);
    }

    public function test_stop_saves_the_status_and_the_reason_and_forgets_what_it_saw(): void
    {
        $this->travelTo('2026-11-22 10:00:00');
        $import = $this->import(['status' => DataImport::IMPORTING]);
        $import->remember('record', 'A-1');

        $import->stop(DataImport::FAILED, 'Broken');
        $import->refresh();

        $this->assertSame([DataImport::FAILED, 'Broken'], [$import->status, $import->message]);
        $this->assertSame('2026-11-22 10:00:00', $import->finished_at?->toDateTimeString());
        $this->assertSame(0, DB::table('admin_import_keys')->count());

        $import->stop(DataImport::CHECKED);
        $this->assertNull($import->refresh()->message);
    }

    public function test_the_file_is_deleted_once(): void
    {
        config(['atrium.import.directory' => sys_get_temp_dir()]);
        $import = $this->import(['file' => 'atrium-delete-'.bin2hex(random_bytes(4)).'.csv']);
        file_put_contents($import->path(), 'x');

        $import->deleteFile();
        $this->assertFileDoesNotExist($import->path());

        $import->deleteFile();
        $this->assertFileDoesNotExist($import->path());
    }

    public function test_it_belongs_to_the_administrator_who_uploaded_it(): void
    {
        $admin = $this->admin();

        $this->assertSame($admin->id, $this->import(['admin_user_id' => $admin->id])->user?->id);
        $this->assertNull($this->import()->user);
    }

    public function test_the_lists_and_dates_come_back_as_such(): void
    {
        $this->travelTo('2026-11-22 10:00:00');
        $import = $this->import(['mapping' => ['name', null], 'options' => ['key' => 'id']]);
        $import->forceFill(['errors' => [['row' => 2, 'skipped' => true, 'messages' => ['x']]], 'started_at' => now(), 'finished_at' => now()])->save();
        $import = DataImport::query()->findOrFail($import->id);

        $this->assertSame(['Name'], $import->headers);
        $this->assertSame(['name', null], $import->mapping);
        $this->assertSame(['key' => 'id'], $import->options);
        $this->assertSame([['row' => 2, 'skipped' => true, 'messages' => ['x']]], $import->errors);
        $this->assertSame('2026-11-22 10:00:00', $import->started_at?->toDateTimeString());
        $this->assertSame('2026-11-22 10:00:00', $import->finished_at?->toDateTimeString());
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    private function import(array $attributes = []): DataImport
    {
        return DataImport::query()->create([
            'module' => 'parcels',
            'status' => DataImport::NEW,
            'original_name' => 'price.csv',
            'file' => 'price.csv',
            'format' => 'csv',
            'encoding' => 'UTF-8',
            'delimiter' => ';',
            'headers' => ['Name'],
            'total' => 1,
            ...$attributes,
        ]);
    }
}
