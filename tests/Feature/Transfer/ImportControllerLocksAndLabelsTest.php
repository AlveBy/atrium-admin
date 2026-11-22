<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Transfer;

use Atrium\Admin\Http\Controllers\ImportController;
use Atrium\Admin\Models\DataImport;
use Atrium\Admin\Tests\Fixtures\Parcel;
use Atrium\Admin\Transfer\ImportRunner;
use Atrium\Admin\Transfer\SourceFile;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The stop waits for the step that is under way, on the lock the step takes,
 * for as long as a step may last; an import of a module that has gone is not
 * touched; the upload names its field in the language of the panel.
 */
final class ImportControllerLocksAndLabelsTest extends TransferTestCase
{
    /**
     * @param  array<string,mixed>  $attributes
     */
    private function importOf_(string $module, string $status, array $attributes = []): DataImport
    {
        File::ensureDirectoryExists($this->directory);
        file_put_contents($this->directory.'/source.csv', "Article;Name\nA-1;Parka\n");
        $file = Str::uuid().'.csv';
        $read = app(SourceFile::class)->normalize($this->directory.'/source.csv', $this->directory.'/'.$file);

        $import = new DataImport;
        $import->forceFill([
            ...$read, 'module' => $module, 'status' => $status, 'original_name' => 'prices.csv', 'file' => $file,
            'mapping' => ['article', 'name'], 'options' => ['key' => 'article', 'mode' => 'both'], ...$attributes,
        ])->save();

        return $import;
    }

    public function test_the_stop_waits_on_the_lock_of_the_import_for_a_step_and_five_seconds(): void
    {
        config(['atrium.import.seconds' => 7]);
        $import = $this->importOf_('parcels', DataImport::IMPORTING);
        $lock = Mockery::mock();
        $lock->shouldReceive('block')->once()->with(12, Mockery::type(Closure::class))->andReturnUsing(static fn (int $seconds, Closure $work): mixed => $work());
        Cache::partialMock()->shouldReceive('lock')->once()->with('atrium-import:'.$import->id, 10)->andReturn($lock);

        $this->actingAs($this->admin(), 'admin')->post("/admin/parcels/import/{$import->id}/cancel")->assertRedirect("/admin/parcels/import/{$import->id}");

        $this->assertSame(DataImport::CANCELLED, $import->refresh()->status);
        $this->assertFileDoesNotExist($import->path());
    }

    public function test_the_stop_looks_again_when_it_has_the_lock_and_leaves_an_import_that_finished_while_it_waited(): void
    {
        $import = $this->importOf_('parcels', DataImport::IMPORTING);
        $lock = Mockery::mock();
        $lock->shouldReceive('block')->once()->andReturnUsing(static function (int $seconds, Closure $work) use ($import): mixed {
            DataImport::query()->whereKey($import->id)->update(['status' => DataImport::DONE]);

            return $work();
        });
        Cache::partialMock()->shouldReceive('lock')->once()->andReturn($lock);

        $this->actingAs($this->admin(), 'admin')->post("/admin/parcels/import/{$import->id}/cancel")->assertRedirect();

        $this->assertSame(DataImport::DONE, $import->refresh()->status);
        $this->assertFileExists($import->path());
    }

    /**
     * @return array<string,array{string,string}> action, status
     */
    public static function actions(): array
    {
        return [
            'the import' => ['run', DataImport::CHECKED],
            'the progress' => ['step', DataImport::IMPORTING],
            'the stop' => ['cancel', DataImport::IMPORTING],
        ];
    }

    #[DataProvider('actions')]
    public function test_an_import_of_a_module_that_is_gone_is_not_found_and_not_touched(string $action, string $status): void
    {
        $import = $this->importOf_('gone', $status);
        $arguments = $action === 'step' ? [$import, 'gone', app(ImportRunner::class)] : [$import, 'gone'];

        try {
            app(ImportController::class)->{$action}(...$arguments);
            $this->fail('The module is not there, so the action has to be refused.');
        } catch (NotFoundHttpException) {
            // As it should be.
        }

        $import->refresh();
        $this->assertSame($status, $import->status);
        $this->assertNull($import->finished_at);
        $this->assertSame(0, $import->processed);
        $this->assertFileExists($import->path());
    }

    public function test_the_index_of_a_module_that_is_gone_is_not_found(): void
    {
        $this->expectException(NotFoundHttpException::class);

        app(ImportController::class)->index('gone');
    }

    public function test_an_import_of_a_module_that_is_there_is_moved_by_the_same_calls(): void
    {
        $import = $this->importOf_('parcels', DataImport::IMPORTING);

        app(ImportController::class)->step($import, 'parcels', app(ImportRunner::class));

        $this->assertSame([DataImport::DONE, 1], [$import->refresh()->status, Parcel::query()->count()]);
    }

    public function test_the_upload_names_its_field_as_the_panel_does(): void
    {
        app('translator')->addLines(['import.upload.file' => 'spreadsheet'], 'en', 'atrium');

        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post('/admin/parcels/import', [])
            ->assertSessionHasErrors(['file' => 'The spreadsheet field is required.']);
        $this->actingAs($admin, 'admin')->post('/admin/parcels/import', ['file' => 'prices.csv'])
            ->assertSessionHasErrors(['file' => 'The spreadsheet field must be a file.']);
    }
}
