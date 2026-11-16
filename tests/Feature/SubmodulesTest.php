<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Modules\Module;
use Atrium\Admin\Modules\Modules;
use Atrium\Admin\Tests\Fixtures\Modules\Label;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Inertia\Testing\AssertableInertia;

/**
 * A module's submodules: packages of their own, installed with it, listed
 * under it, removed before it.
 */
final class SubmodulesTest extends TestCase
{
    private string $root;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app->resolving(Modules::class, static function (Modules $modules): void {
            $modules->add(Label::module());
            $modules->add(self::fixture('labels-printing'));
            $modules->add(self::fixture('labels-qr'));
        });

        $this->root = sys_get_temp_dir().'/atrium-submodules-'.uniqid();
        mkdir($this->root.'/config', 0777, true);
        copy(dirname(__DIR__, 2).'/config/atrium.php', $this->root.'/config/atrium.php');
        $app->useConfigPath($this->root.'/config');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_a_module_has_the_submodules_it_lists_and_those_that_name_it(): void
    {
        $modules = $this->app->make(Modules::class);
        $labels = $modules->find('acme/atrium-labels');

        $this->assertSame(['acme/atrium-labels-printing'], $labels->submodules);
        $this->assertSame('acme/atrium-labels', self::fixture('labels-qr')->parent);
        $this->assertSame(['acme/atrium-labels-printing', 'acme/atrium-labels-qr'], array_keys($modules->childrenOf($labels)));
        $this->assertSame('acme/atrium-labels', $modules->parentOf($modules->find('acme/atrium-labels-printing'))?->name);
        $this->assertSame('acme/atrium-labels', $modules->parentOf($modules->find('acme/atrium-labels-qr'))?->name);
        $this->assertNull($modules->parentOf($labels));
    }

    public function test_installing_a_module_installs_its_submodules(): void
    {
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();

        $this->assertSame(['acme/atrium-labels', 'acme/atrium-labels-printing', 'acme/atrium-labels-qr'], config('atrium.modules'));
        $this->assertStringContainsString('acme/atrium-labels-qr', (string) file_get_contents(config_path('atrium.php')));
    }

    public function test_a_submodule_needs_its_parent_installed_first(): void
    {
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels-qr'])
            ->expectsOutputToContain('Install acme/atrium-labels first')
            ->assertFailed();

        $this->assertSame([], config('atrium.modules'));
    }

    public function test_a_submodule_removed_on_its_own_leaves_its_parent(): void
    {
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();
        $this->artisan('atrium:module:remove', ['package' => 'acme/atrium-labels-qr'])->assertSuccessful();

        $this->assertSame(['acme/atrium-labels', 'acme/atrium-labels-printing'], config('atrium.modules'));
    }

    public function test_removing_a_module_removes_its_submodules_first(): void
    {
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();
        $this->artisan('atrium:module:remove', ['package' => 'acme/atrium-labels', '--keep-data' => true])->assertSuccessful();

        $this->assertSame([], config('atrium.modules'));
    }

    public function test_the_modules_screen_lists_submodules_under_their_parent(): void
    {
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();

        $this->actingAs($this->admin(), 'admin')->get('/admin/modules')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('modules.0.name', 'acme/atrium-labels')
                ->where('modules.0.parent', null)
                ->where('modules.1.name', 'acme/atrium-labels-printing')
                ->where('modules.1.parent', 'acme/atrium-labels')
                ->where('modules.2.name', 'acme/atrium-labels-qr')
                ->where('modules.2.parent', 'acme/atrium-labels'));
    }

    public function test_a_parents_demo_content_comes_before_its_submodules(): void
    {
        $modules = $this->app->make(Modules::class);
        $all = ['acme/atrium-labels-qr' => self::fixture('labels-qr'), 'acme/atrium-labels' => Label::module()];

        $this->assertSame(['acme/atrium-labels', 'acme/atrium-labels-qr'], array_keys($modules->dependencyOrder($all)));
    }

    private static function fixture(string $folder): Module
    {
        return Module::fromPath(dirname(__DIR__).'/Fixtures/Modules/'.$folder) ?? throw new \RuntimeException("{$folder} is not a module.");
    }
}
