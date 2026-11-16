<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Modules;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Demo content of a module: the `demo` seeder of its manifest, run by
 * `atrium:install --demo` and `atrium:module:install {package} --demo`.
 *
 * It runs once: when the module already has content, run() returns false
 * and adds nothing, so edits made in the admin stay.
 */
abstract class DemoSeeder extends Seeder
{
    /** Where the pictures and documents go on the uploads disk. */
    public const DIRECTORY = 'demo';

    /**
     * Fill the module. False when it has content already.
     */
    abstract public function run(): bool;

    /**
     * Copy every file of a folder of the package to the uploads disk.
     */
    protected function copyFiles(string $folder): void
    {
        $disk = Storage::disk((string) config('atrium.uploads.disk', 'public'));

        foreach (glob(rtrim($folder, '/').'/*') ?: [] as $file) {
            if (is_file($file)) {
                $disk->put($this->path(basename($file)), (string) file_get_contents($file));
            }
        }
    }

    /**
     * The path of a copied file, as the image and file fields keep it.
     */
    protected function path(string $file): string
    {
        return self::DIRECTORY.'/'.$file;
    }
}
