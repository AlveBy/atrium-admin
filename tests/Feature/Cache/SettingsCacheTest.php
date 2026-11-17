<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Cache;

use Atrium\Admin\Cache\RecordCache;
use Atrium\Admin\Modules\Modules;
use Atrium\Admin\Modules\Settings;
use Atrium\Admin\Tests\Fixtures\Modules\Label;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;

/**
 * Module settings in the record cache: read once for all the visitors, kept
 * under their own name, dropped when saved, flushed or when the module goes.
 */
final class SettingsCacheTest extends TestCase
{
    private string $root;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app->resolving(Modules::class, static fn (Modules $modules) => $modules->add(Label::module()));

        $this->root = sys_get_temp_dir().'/atrium-settings-cache-'.uniqid();
        mkdir($this->root.'/config', 0777, true);
        copy(dirname(__DIR__, 3).'/config/atrium.php', $this->root.'/config/atrium.php');
        $app->useConfigPath($this->root.'/config');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }

    private function settings(): Settings
    {
        return $this->app->make(Settings::class);
    }

    /**
     * What a new request starts with: the settings not yet read.
     */
    private function forgetMemo(): void
    {
        (fn () => $this->stored = [])->call($this->settings());
    }

    /**
     * What the record cache holds under a name, or the given marker.
     */
    private function kept(string $key, bool $perSite = false): mixed
    {
        return $this->app->make(RecordCache::class)->remember(Settings::class, $key, static fn (): string => 'nothing kept', perSite: $perSite);
    }

    public function test_the_settings_of_a_module_are_kept_under_its_name_for_all_sites(): void
    {
        $this->settings()->save('labels', ['color' => 'blue']);
        $this->forgetMemo();

        $this->assertSame(['color' => 'blue'], $this->settings()->stored('labels'));

        $this->assertSame([0 => ['color' => 'blue']], $this->kept('settings:labels'));
    }

    public function test_every_module_has_a_name_of_its_own_in_the_cache(): void
    {
        $this->settings()->save('labels', ['color' => 'blue']);
        $this->settings()->save('banners', ['size' => 'big']);
        $this->forgetMemo();

        $this->assertSame(['size' => 'big'], $this->settings()->stored('banners'));
        $this->assertSame(['color' => 'blue'], $this->settings()->stored('labels'));

        $this->assertSame([0 => ['size' => 'big']], $this->kept('settings:banners'));
        $this->assertSame('nothing kept', $this->kept('settings:'));
        $this->assertSame('nothing kept', $this->kept('labels'));
        $this->assertSame('nothing kept', $this->kept('labelssettings:'));
    }

    public function test_the_cache_holds_the_values_of_every_site_of_the_module(): void
    {
        DB::table(Settings::TABLE)->insert([
            ['module' => 'labels', 'site_id' => null, 'values' => '{"color":"red"}', 'created_at' => now(), 'updated_at' => now()],
            ['module' => 'labels', 'site_id' => 7, 'values' => '{"color":"blue"}', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->assertSame(['color' => 'blue'], $this->settings()->stored('labels', 7));
        $this->assertSame(['color' => 'red'], $this->settings()->stored('labels'));
        $this->assertSame([], $this->settings()->stored('labels', 8));
    }

    public function test_a_row_with_something_else_than_a_list_of_values_reads_as_an_array(): void
    {
        DB::table(Settings::TABLE)->insert(['module' => 'labels', 'site_id' => null, 'values' => '"damaged"', 'created_at' => now(), 'updated_at' => now()]);

        $this->assertIsArray($this->settings()->stored('labels'));
    }

    public function test_a_row_with_nothing_in_it_reads_as_no_values(): void
    {
        DB::table(Settings::TABLE)->insert(['module' => 'labels', 'site_id' => null, 'values' => 'null', 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame([], $this->settings()->stored('labels'));
        $this->assertSame([0 => []], $this->kept('settings:labels'));
    }

    public function test_flushing_makes_the_settings_be_read_anew(): void
    {
        $this->settings()->save('labels', ['color' => 'blue']);
        $this->forgetMemo();
        $this->assertSame(['color' => 'blue'], $this->settings()->stored('labels'));

        // Changed behind the cache's back.
        DB::table(Settings::TABLE)->where('module', 'labels')->update(['values' => '{"color":"red"}']);
        $this->forgetMemo();
        $this->assertSame(['color' => 'blue'], $this->settings()->stored('labels'));

        $this->settings()->flush();

        $this->assertSame(['color' => 'red'], $this->settings()->stored('labels'));
    }

    public function test_flushing_forgets_what_was_read_and_what_modules_declared(): void
    {
        $this->settings()->save('labels', ['color' => 'blue']);
        DB::table(Settings::TABLE)->where('module', 'labels')->update(['values' => '{"color":"red"}']);

        $this->settings()->flush();

        $this->assertSame(['color' => 'red'], $this->settings()->stored('labels'));
    }

    public function test_saving_makes_the_settings_show_at_once(): void
    {
        $this->settings()->save('labels', ['color' => 'blue']);
        $this->forgetMemo();
        $this->assertSame(['color' => 'blue'], $this->settings()->stored('labels'));

        $this->settings()->save('labels', ['color' => 'red']);
        $this->forgetMemo();

        $this->assertSame(['color' => 'red'], $this->settings()->stored('labels'));
    }

    public function test_removing_a_module_with_its_data_drops_its_settings_from_the_cache_too(): void
    {
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();
        $this->settings()->save('labels', ['color' => 'blue']);
        $this->assertSame(['color' => 'blue'], $this->settings()->stored('labels'));

        $this->artisan('atrium:module:remove', ['package' => 'acme/atrium-labels', '--delete-data' => true])->assertSuccessful();

        $this->assertSame([], $this->settings()->stored('labels'));
    }

    public function test_the_settings_are_gone_from_the_cache_even_when_the_config_file_cannot_be_edited(): void
    {
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();
        $this->settings()->save('labels', ['color' => 'blue']);
        $this->assertSame(['color' => 'blue'], $this->settings()->stored('labels'));
        unlink(config_path('atrium.php'));

        $this->artisan('atrium:module:remove', ['package' => 'acme/atrium-labels', '--delete-data' => true])->assertFailed();

        $this->assertSame(0, DB::table(Settings::TABLE)->count());
        $this->assertSame([], $this->settings()->stored('labels'));
    }

    public function test_removing_a_module_and_keeping_its_data_keeps_its_settings(): void
    {
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();
        $this->settings()->save('labels', ['color' => 'blue']);
        $this->assertSame(['color' => 'blue'], $this->settings()->stored('labels'));

        $this->artisan('atrium:module:remove', ['package' => 'acme/atrium-labels', '--keep-data' => true])->assertSuccessful();

        $this->assertSame(['color' => 'blue'], $this->settings()->stored('labels'));
        $this->assertSame(1, DB::table(Settings::TABLE)->count());
    }
}
