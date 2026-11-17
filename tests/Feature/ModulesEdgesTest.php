<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Modules\DemoSeeder;
use Atrium\Admin\Modules\Module;
use Atrium\Admin\Modules\Modules;
use Atrium\Admin\Support\PublicUrl;
use Atrium\Admin\Tests\Fixtures\Handbook;
use Atrium\Admin\Tests\TestCase;
use stdClass;

final class ModulesEdgesTest extends TestCase
{
    protected function tearDown(): void
    {
        PublicUrl::forgetPaths();

        parent::tearDown();
    }

    public function test_a_module_without_a_demo_has_nothing_to_seed(): void
    {
        $this->assertFalse(app(Modules::class)->seedDemo(new Module('acme/plain')));
    }

    public function test_a_demo_that_is_not_a_seeder_is_not_run(): void
    {
        $this->assertFalse(app(Modules::class)->seedDemo(new Module('acme/odd', demo: stdClass::class)));
    }

    public function test_a_demo_reports_whether_it_added_anything(): void
    {
        FillingDemo::$runs = 0;

        $this->assertTrue(app(Modules::class)->seedDemo(new Module('acme/fresh', demo: FillingDemo::class)));
        $this->assertFalse(app(Modules::class)->seedDemo(new Module('acme/filled', demo: FilledDemo::class)));
        $this->assertSame(1, FillingDemo::$runs);
        $this->assertSame(1, Handbook::query()->count());
    }

    public function test_seeded_sections_get_their_addresses_built_again(): void
    {
        $this->assertSame([], PublicUrl::sectionPaths(Handbook::class));

        app(Modules::class)->seedDemo(new Module('acme/fresh', demo: FillingDemo::class));

        $this->assertSame(['tours'], array_values(PublicUrl::sectionPaths(Handbook::class)));
    }

    public function test_a_module_cannot_be_removed_without_a_config_to_write(): void
    {
        config(['atrium.modules' => ['acme/one']]);
        $this->app->useConfigPath(sys_get_temp_dir().'/atrium-no-config-'.bin2hex(random_bytes(4)));

        $this->assertFalse(app(Modules::class)->remove('Acme/One'));
        $this->assertSame(['acme/one'], config('atrium.modules'));
    }
}

final class FillingDemo extends DemoSeeder
{
    public static int $runs = 0;

    public function run(): bool
    {
        self::$runs++;
        Handbook::query()->create(['name' => 'Tours', 'code' => 'tours']);

        return true;
    }
}

final class FilledDemo extends DemoSeeder
{
    public function run(): bool
    {
        return false;
    }
}
