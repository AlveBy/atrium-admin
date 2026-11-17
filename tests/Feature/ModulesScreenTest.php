<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Modules\Catalogue;
use Atrium\Admin\Modules\Module;
use Atrium\Admin\Modules\Modules;
use Atrium\Admin\Resources\ResourceRegistry;
use Atrium\Admin\Tests\Fixtures\Modules\Label;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

/**
 * The Modules screen for a superadmin: switching installed modules off and
 * on, the packages required but not installed, the catalogue on Packagist.
 */
final class ModulesScreenTest extends TestCase
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

        $this->root = sys_get_temp_dir().'/atrium-modules-screen-'.uniqid();
        mkdir($this->root.'/config', 0777, true);
        copy(dirname(__DIR__, 2).'/config/atrium.php', $this->root.'/config/atrium.php');
        $app->useConfigPath($this->root.'/config');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_a_switched_off_module_is_out_of_the_panel_until_switched_on(): void
    {
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();
        $heard = [];
        Hook::on('atrium.module.disabled', function (Module $module) use (&$heard): void {
            $heard[] = 'off '.$module->name;
        });
        Hook::on('atrium.module.enabled', function (Module $module) use (&$heard): void {
            $heard[] = 'on '.$module->name;
        });
        $modules = $this->app->make(Modules::class);

        $this->actingAs($this->admin(), 'admin')->post('/admin/modules/labels/disable')->assertSessionHas('success');

        $this->assertFalse($modules->isInstalled('acme/atrium-labels'));
        $this->assertTrue($modules->isDisabled('acme/atrium-labels'));
        $this->assertNull($this->app->make(ResourceRegistry::class)->keyOf(Label::class));
        $this->assertStringContainsString("'disabled_modules' => [\n        'acme/atrium-labels-printing',\n        'acme/atrium-labels-qr',\n        'acme/atrium-labels',", (string) file_get_contents(config_path('atrium.php')));

        $this->get('/admin/modules')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('modules.0.name', 'acme/atrium-labels')
            ->where('modules.0.enabled', false)
            ->where('modules.0.settings', false));

        $this->post('/admin/modules/labels/enable')->assertSessionHas('success');

        $this->assertTrue($modules->isInstalled('acme/atrium-labels'));
        $this->assertTrue($modules->isInstalled('acme/atrium-labels-qr'));
        $this->assertSame('labels', $this->app->make(ResourceRegistry::class)->keyOf(Label::class));
        $this->assertStringNotContainsString("        'acme/atrium-labels',\n    ],\n", (string) file_get_contents(config_path('atrium.php')));
        $this->assertSame([
            'off acme/atrium-labels-printing', 'off acme/atrium-labels-qr', 'off acme/atrium-labels',
            'on acme/atrium-labels', 'on acme/atrium-labels-printing', 'on acme/atrium-labels-qr',
        ], $heard);
    }

    public function test_a_submodule_is_switched_on_only_under_a_parent_that_is_on(): void
    {
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();
        $this->actingAs($this->admin(), 'admin')->post('/admin/modules/labels/disable');

        $this->post('/admin/modules/labels_qr/enable')->assertSessionHas('error');

        $this->assertTrue($this->app->make(Modules::class)->isDisabled('acme/atrium-labels-qr'));
    }

    public function test_switching_a_module_on_leaves_its_removed_submodules_out(): void
    {
        $modules = $this->app->make(Modules::class);
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();
        $this->artisan('atrium:module:remove', ['package' => 'acme/atrium-labels-qr'])->assertSuccessful();

        $this->actingAs($this->admin(), 'admin')->post('/admin/modules/labels/disable');
        $this->post('/admin/modules/labels/enable')->assertSessionHas('success');

        $this->assertTrue($modules->isInstalled('acme/atrium-labels-printing'));
        $this->assertFalse($modules->isInstalled('acme/atrium-labels-qr'));
        $this->assertFalse($modules->isDisabled('acme/atrium-labels-qr'));
    }

    public function test_nothing_changes_when_the_config_file_cannot_be_edited(): void
    {
        $modules = $this->app->make(Modules::class);
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();
        $this->artisan('atrium:module:remove', ['package' => 'acme/atrium-labels-printing'])->assertSuccessful();
        $this->actingAs($this->admin(), 'admin')->post('/admin/modules/labels_qr/disable');
        unlink(config_path('atrium.php'));

        $this->post('/admin/modules/labels/disable')->assertSessionHas('error', __('atrium::modules.config_locked'));
        $this->assertTrue($modules->isInstalled('acme/atrium-labels'));

        $this->post('/admin/modules/labels_qr/enable')->assertSessionHas('error', __('atrium::modules.config_locked'));
        $this->assertFalse($modules->install($modules->find('acme/atrium-labels-qr')));
        $this->assertTrue($modules->isDisabled('acme/atrium-labels-qr'));

        $this->assertFalse($modules->install($modules->find('acme/atrium-labels-printing')));
        $this->assertFalse($modules->isInstalled('acme/atrium-labels-printing'));
    }

    public function test_cached_config_and_routes_are_built_again_after_a_switch(): void
    {
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();
        // Stand-ins for the real commands, which would cache the test app.
        $built = [];
        foreach (['config:cache', 'route:cache'] as $name) {
            $this->app->make(ConsoleKernel::class)->registerCommand(new ClosureCommand($name, function () use (&$built, $name): void {
                $built[] = $name;
            }));
        }

        $this->actingAs($this->admin(), 'admin')->post('/admin/modules/labels_qr/disable')->assertSessionHas('success');
        $this->assertSame([], $built, 'Nothing was cached, so nothing is built.');

        // What Laravel looks at to tell cached config and routes.
        $this->app->instance('config_loaded_from_cache', true);
        $this->app->instance('routes.cached', true);

        $this->post('/admin/modules/labels_qr/enable')->assertSessionHas('success');
        $this->assertSame(['config:cache', 'route:cache'], $built);
    }

    public function test_only_a_superadmin_switches_modules(): void
    {
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();
        $editor = AdminUser::query()->create(['name' => 'Editor', 'email' => 'editor@example.com', 'password' => 'secret-password', 'is_active' => true]);
        $editor->roles()->attach(AdminRole::query()->create(['name' => 'Editors', 'permissions' => ['labels.settings']]));

        $this->actingAs($editor->fresh(), 'admin')->post('/admin/modules/labels/disable')->assertForbidden();
        $this->get('/admin/modules')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('canManage', false)
            ->where('required', [])
            ->where('catalogue', null));

        $this->assertTrue($this->app->make(Modules::class)->isInstalled('acme/atrium-labels'));
    }

    public function test_installing_switches_a_module_on_and_removing_forgets_it_was_off(): void
    {
        $modules = $this->app->make(Modules::class);
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();
        $this->actingAs($this->admin(), 'admin')->post('/admin/modules/labels_qr/disable');

        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels-qr'])
            ->expectsOutputToContain('was installed already')
            ->assertSuccessful();
        $this->assertTrue($modules->isInstalled('acme/atrium-labels-qr'));

        $this->post('/admin/modules/labels_qr/disable');
        $this->artisan('atrium:module:remove', ['package' => 'acme/atrium-labels-qr'])->assertSuccessful();

        $this->assertFalse($modules->isDisabled('acme/atrium-labels-qr'));
        $this->assertSame([], config('atrium.disabled_modules'));
        $this->assertStringNotContainsString("'acme/atrium-labels-qr',", (string) file_get_contents(config_path('atrium.php')));
    }

    public function test_the_screen_lists_modules_required_but_not_installed(): void
    {
        Http::fake(['packagist.org/*' => Http::response(['results' => []])]);

        $this->actingAs($this->admin(), 'admin')->get('/admin/modules')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('canManage', true)
            ->where('required', fn ($required): bool => array_values(array_filter(array_column($required->all(), 'name'), static fn (string $name): bool => str_starts_with($name, 'acme/'))) === ['acme/atrium-labels', 'acme/atrium-labels-printing', 'acme/atrium-labels-qr']));
    }

    public function test_the_catalogue_lists_alveby_first_with_what_each_module_is_here(): void
    {
        Cache::forget('atrium.modules.catalogue');
        Http::fake([
            'packagist.org/search.json?type=atrium-module*' => Http::response(['results' => [
                ['name' => 'someone/atrium-popular', 'description' => 'Popular', 'url' => 'https://packagist.org/packages/someone/atrium-popular', 'downloads' => 900],
                ['name' => 'alveby/atrium-sample', 'description' => 'Brands', 'url' => 'https://packagist.org/packages/alveby/atrium-sample', 'downloads' => 10],
                ['name' => 'someone/atrium-old', 'description' => 'Old', 'downloads' => 5000, 'abandoned' => true],
            ]]),
            'packagist.org/search.json?tags=atrium-module*' => Http::response(['results' => [
                ['name' => 'acme/atrium-labels', 'description' => 'Labels', 'downloads' => 3],
                ['name' => 'acme/atrium-labels-qr', 'description' => 'QR', 'downloads' => 2],
            ]]),
        ]);
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();
        $this->actingAs($this->admin(), 'admin')->post('/admin/modules/labels_qr/disable');

        $this->get('/admin/modules')->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('catalogue')
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->where('catalogue.packages.0.name', 'alveby/atrium-sample')
                ->where('catalogue.packages.0.status', 'available')
                ->where('catalogue.packages.1.name', 'someone/atrium-popular')
                ->where('catalogue.packages.2.name', 'acme/atrium-labels')
                ->where('catalogue.packages.2.status', 'installed')
                ->where('catalogue.packages.3.name', 'acme/atrium-labels-qr')
                ->where('catalogue.packages.3.status', 'disabled')
                ->count('catalogue.packages', 4)));

        // Asked once, then cached.
        $this->app->make(Catalogue::class)->packages();
        Http::assertSentCount(2);
    }

    public function test_the_catalogue_says_so_when_packagist_does_not_answer_and_asks_again_next_time(): void
    {
        Cache::forget('atrium.modules.catalogue');
        Http::fake(['packagist.org/*' => Http::response('', 503)]);

        $this->assertNull($this->app->make(Catalogue::class)->packages());
        $this->assertNull(Cache::get('atrium.modules.catalogue'));

        Http::fake(fn () => throw new ConnectionException('Packagist is down'));

        $this->assertNull($this->app->make(Catalogue::class)->packages());
    }

    private static function fixture(string $folder): Module
    {
        return Module::fromPath(dirname(__DIR__).'/Fixtures/Modules/'.$folder) ?? throw new \RuntimeException("{$folder} is not a module.");
    }
}
