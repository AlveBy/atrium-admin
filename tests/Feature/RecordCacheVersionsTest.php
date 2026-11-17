<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Cache\CacheStore;
use Illuminate\Support\Facades\File;

/**
 * The same tests on the file store: no tags, version keys only (also the
 * way the database store works).
 */
final class RecordCacheVersionsTest extends RecordCacheTest
{
    private string $directory;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->directory = sys_get_temp_dir().'/atrium-cache-test-'.getmypid();
        $app['config']->set('cache.stores.cachefile', ['driver' => 'file', 'path' => $this->directory]);
        $app['config']->set('atrium.cache.store', 'cachefile');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_the_file_store_uses_version_keys(): void
    {
        $this->assertFalse($this->app->make(CacheStore::class)->usesTags());
        $this->assertSame('file', $this->app->make(CacheStore::class)->driver());
    }
}
