<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Cache;

use Atrium\Admin\Cache\RecordCache;
use Atrium\Admin\Modules\Module;
use Atrium\Admin\Modules\Modules;
use Atrium\Admin\Support\PublicUrl;
use Atrium\Admin\Tests\Fixtures\Handbook;
use Atrium\Admin\Tests\TestCase;

/**
 * The section paths a request worked out and the ones the record cache
 * keeps: wiring a module in, which happens at every boot, forgets only the
 * first and writes nothing to the cache.
 */
final class PublicUrlMemoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        PublicUrl::forgetPaths();
    }

    private function italy(): Handbook
    {
        $tours = Handbook::query()->create(['name' => 'Tours', 'code' => 'tours']);

        return Handbook::query()->create(['name' => 'Italy', 'code' => 'italy', 'parent_id' => $tours->id]);
    }

    private function pathsVersion(): string
    {
        $versions = app(RecordCache::class)->versions();
        $versions->reset();

        return $versions->get(['list:'.PublicUrl::class])['list:'.PublicUrl::class];
    }

    public function test_forgetting_the_memo_keeps_the_kept_paths_and_forgetting_the_paths_does_not(): void
    {
        $italy = $this->italy();
        $this->assertSame('tours/italy', PublicUrl::sectionPaths(Handbook::class)[$italy->id]);
        Handbook::query()->whereKey($italy->id)->update(['code' => 'italia']);
        $version = $this->pathsVersion();

        PublicUrl::forgetMemo();

        $this->assertSame('tours/italy', PublicUrl::sectionPaths(Handbook::class)[$italy->id]);
        $this->assertSame($version, $this->pathsVersion());

        PublicUrl::forgetPaths();

        $this->assertSame('tours/italia', PublicUrl::sectionPaths(Handbook::class)[$italy->id]);
        $this->assertNotSame($version, $this->pathsVersion());
    }

    public function test_wiring_a_module_in_forgets_the_paths_of_the_request_and_writes_nothing(): void
    {
        $italy = $this->italy();
        $this->assertSame('tours/italy', PublicUrl::sectionPaths(Handbook::class)[$italy->id]);
        // The tree changes behind the request's memo: the kept copy is dropped.
        Handbook::query()->whereKey($italy->id)->update(['code' => 'italia']);
        app(RecordCache::class)->flushClass(Handbook::class);
        $this->assertSame('tours/italy', PublicUrl::sectionPaths(Handbook::class)[$italy->id]);
        $version = $this->pathsVersion();

        app(Modules::class)->wire(new Module('acme/plain'));

        $this->assertSame('tours/italia', PublicUrl::sectionPaths(Handbook::class)[$italy->id]);
        $this->assertSame($version, $this->pathsVersion());
    }
}
