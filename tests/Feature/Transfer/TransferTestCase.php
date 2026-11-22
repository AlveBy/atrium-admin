<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Transfer;

use Atrium\Admin\Models\DataImport;
use Atrium\Admin\Resources\ResourceRegistry;
use Atrium\Admin\Tests\Fixtures\Parcel;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Atrium\Admin\Transfer\Hosts;
use Atrium\Admin\Transfer\ImportRunner;
use Atrium\Admin\Transfer\SourceFile;
use Atrium\Admin\Transfer\Targets;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Import and export of the modules of the test application: parcels, which
 * have a field of every kind, and posts.
 */
abstract class TransferTestCase extends TestCase
{
    protected string $directory;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Parcel::class, Post::class]);
        $app['config']->set('atrium.import.directory', $this->directory = sys_get_temp_dir().'/atrium-import-'.Str::random(8));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    /**
     * An import of a file as the upload makes it, mapped as the headers say.
     *
     * @param  array<string,mixed>  $options
     */
    protected function importOf(string $module, string $path, array $options = [], ?array $mapping = null): DataImport
    {
        File::ensureDirectoryExists(DataImport::directory());
        $file = Str::uuid().'.csv';
        $read = app(SourceFile::class)->normalize($path, DataImport::directory().'/'.$file);
        $model = app(ResourceRegistry::class)->model($module);

        return DataImport::query()->create([
            ...$read,
            'module' => $module,
            'status' => DataImport::NEW,
            'original_name' => basename($path),
            'file' => $file,
            'mapping' => $mapping ?? Targets::for($model)->guess($read['headers']),
            'options' => ['key' => 'id', 'mode' => 'both', ...$options],
        ]);
    }

    /**
     * Run a check or an import to the end.
     */
    protected function run_(DataImport $import, string $status): DataImport
    {
        $import->begin($status);
        $runner = new ImportRunner(app(ResourceRegistry::class), app(Hosts::class));

        while ($runner->step($import, 20)) {
            // Each step saves its place.
        }

        return $import->refresh();
    }
}
