<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Transfer;

use Atrium\Admin\Models\DataImport;
use Atrium\Admin\Tests\Fixtures\Parcel;
use Atrium\Admin\Transfer\ImportRunner;
use Atrium\Admin\Transfer\ProcessImport;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

/**
 * A queue worker moves an import a step at a time, queueing the next step
 * itself; without one the scheduler does it every minute.
 */
final class ProcessImportTest extends TransferTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        File::ensureDirectoryExists($this->directory);
        Queue::fake();
    }

    public function test_a_queue_worker_moves_imports_unless_the_queue_is_sync(): void
    {
        config(['queue.default' => 'sync']);
        $this->assertFalse(ProcessImport::queued());

        config(['queue.default' => 'redis']);
        $this->assertTrue(ProcessImport::queued());
    }

    public function test_a_queued_start_dispatches_the_first_step(): void
    {
        config(['queue.default' => 'redis']);
        $import = $this->import("2,A-1,Parka\n");

        ProcessImport::start($import);

        Queue::assertPushed(ProcessImport::class, 1);
        Queue::assertPushed(ProcessImport::class, static fn (ProcessImport $job): bool => $job->import === $import->id && $job->delay === null);
        $this->assertSame(0, $import->refresh()->processed);
    }

    public function test_a_start_without_a_queue_dispatches_nothing(): void
    {
        config(['queue.default' => 'sync']);

        ProcessImport::start($this->import("2,A-1,Parka\n"));

        Queue::assertNothingPushed();
    }

    public function test_a_step_that_finds_the_import_busy_queues_itself_again(): void
    {
        $import = $this->import("2,A-1,Parka\n");
        $lock = Cache::lock('atrium-import:'.$import->id, 10);
        $lock->get();

        (new ProcessImport($import->id))->handle(app(ImportRunner::class));

        $this->assertSame(0, $import->refresh()->processed);
        Queue::assertPushed(ProcessImport::class, 1);
        Queue::assertPushed(ProcessImport::class, static fn (ProcessImport $job): bool => $job->import === $import->id && $job->delay === 1);
    }

    public function test_a_job_runs_once_for_up_to_five_minutes(): void
    {
        $job = new ProcessImport(7);

        $this->assertSame([7, 1, 300], [$job->import, $job->tries, $job->timeout]);
    }

    public function test_a_step_with_more_to_do_queues_the_next_one(): void
    {
        config(['atrium.import.seconds' => 0]);
        $import = $this->import("2,A-1,Parka\n3,A-2,Boots\n");

        (new ProcessImport($import->id))->handle(app(ImportRunner::class));

        $this->assertSame(1, $import->refresh()->processed);
        Queue::assertPushed(ProcessImport::class, 1);
        Queue::assertPushed(ProcessImport::class, static fn (ProcessImport $job): bool => $job->import === $import->id && $job->delay === 1);
    }

    public function test_the_last_step_queues_nothing(): void
    {
        $import = $this->import("2,A-1,Parka\n");

        (new ProcessImport($import->id))->handle(app(ImportRunner::class));

        $this->assertSame(DataImport::DONE, $import->refresh()->status);
        $this->assertSame(['A-1'], Parcel::query()->pluck('article')->all());
        Queue::assertNothingPushed();
    }

    public function test_an_import_that_was_deleted_is_left_alone(): void
    {
        (new ProcessImport(999))->handle(app(ImportRunner::class));

        Queue::assertNothingPushed();
    }

    public function test_the_scheduler_moves_the_imports_every_minute(): void
    {
        $events = array_values(array_filter(
            app(Schedule::class)->events(),
            static fn ($event): bool => str_contains((string) $event->command, 'atrium:import --pending'),
        ));

        $this->assertCount(1, $events);
        $this->assertSame('* * * * *', $events[0]->expression);
        $this->assertTrue($events[0]->withoutOverlapping);
    }

    private function import(string $rows): DataImport
    {
        $import = DataImport::query()->create([
            'module' => 'parcels', 'status' => DataImport::NEW, 'original_name' => 'a.csv', 'file' => 'a.csv', 'format' => 'csv',
            'encoding' => 'UTF-8', 'delimiter' => ';', 'headers' => ['Article', 'Name'], 'total' => 2,
            'mapping' => ['article', 'name'], 'options' => ['key' => 'article', 'mode' => 'both'],
        ]);
        File::put($import->path(), $rows);
        $import->begin(DataImport::IMPORTING);

        return $import;
    }
}
