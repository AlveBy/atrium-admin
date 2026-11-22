<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Transfer;

use Atrium\Admin\Models\DataImport;
use Atrium\Admin\Tests\TestCase;

/**
 * The directory of the files of imports: the configured one, else the private
 * storage. The config file itself holds no path, so it can be read before the
 * application is up (the translations test does).
 */
final class ImportDirectoryDefaultTest extends TestCase
{
    public function test_the_config_file_leaves_the_directory_empty_and_can_be_read_without_an_application(): void
    {
        $config = require dirname(__DIR__, 3).'/config/atrium.php';

        $this->assertNull($config['import']['directory']);
    }

    public function test_without_a_configured_directory_the_files_wait_in_the_private_storage(): void
    {
        $this->assertNull(config('atrium.import.directory'));
        $this->assertSame(storage_path('app/private/atrium-imports'), DataImport::directory());
    }

    public function test_a_configured_directory_wins_and_loses_its_trailing_slash(): void
    {
        config(['atrium.import.directory' => '/var/imports/']);

        $this->assertSame('/var/imports', DataImport::directory());
    }

    public function test_the_directory_can_be_set_in_the_environment(): void
    {
        $_ENV['ATRIUM_IMPORT_DIRECTORY'] = '/var/from-env';
        $_SERVER['ATRIUM_IMPORT_DIRECTORY'] = '/var/from-env';

        try {
            $config = require dirname(__DIR__, 3).'/config/atrium.php';
        } finally {
            unset($_ENV['ATRIUM_IMPORT_DIRECTORY'], $_SERVER['ATRIUM_IMPORT_DIRECTORY']);
        }

        $this->assertSame('/var/from-env', $config['import']['directory']);
    }
}
