<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Http\Controllers\ModuleController;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Modules\Catalogue;
use Atrium\Admin\Modules\Module;
use Atrium\Admin\Modules\Modules;
use Atrium\Admin\Resources\ResourceRegistry;
use Atrium\Admin\Tests\Concerns\PinsExtensionSurface;
use Atrium\Admin\Tests\Fixtures\Modules\Label;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;

/**
 * The Modules screen for a superadmin: switching installed modules off and
 * on, the packages required but not installed, the catalogue on Packagist.
 */
final class ModulesScreenTest extends TestCase
{
    use PinsExtensionSurface;

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
        $label = $modules->find('acme/atrium-labels')->label();
        $this->assertSame('labels', $this->app->make(ResourceRegistry::class)->keyOf(Label::class));
        $this->assertFalse($modules->isDisabled('acme/atrium-labels'));

        $this->actingAs($this->admin(), 'admin')->post('/admin/modules/labels/disable')
            ->assertSessionHas('success', __('atrium::modules.disabled', ['module' => $label]));

        $this->assertFalse($modules->isInstalled('acme/atrium-labels'));
        $this->assertTrue($modules->isDisabled('ACME/Atrium-Labels'));
        $this->assertNull($this->app->make(ResourceRegistry::class)->keyOf(Label::class));
        $this->assertStringContainsString("'disabled_modules' => [\n        'acme/atrium-labels-printing',\n        'acme/atrium-labels-qr',\n        'acme/atrium-labels',", (string) file_get_contents(config_path('atrium.php')));

        $this->get('/admin/modules')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('modules.0.name', 'acme/atrium-labels')
            ->where('modules.0.enabled', false)
            ->where('modules.0.settings', false));

        $this->post('/admin/modules/labels/enable')->assertSessionHas('success', __('atrium::modules.enabled', ['module' => $label]));

        $this->assertTrue($modules->isInstalled('acme/atrium-labels'));
        $this->assertTrue($modules->isInstalled('acme/atrium-labels-qr'));
        $this->assertSame([], config('atrium.disabled_modules'));
        $this->assertSame('labels', $this->app->make(ResourceRegistry::class)->keyOf(Label::class));
        $this->assertStringNotContainsString("        'acme/atrium-labels',\n    ],\n", (string) file_get_contents(config_path('atrium.php')));
        $this->assertSame([
            'off acme/atrium-labels-printing', 'off acme/atrium-labels-qr', 'off acme/atrium-labels',
            'on acme/atrium-labels', 'on acme/atrium-labels-printing', 'on acme/atrium-labels-qr',
        ], $heard);
        $actions = $this->assertRouteActionsArePublic();
        foreach (['index', 'disable', 'enable'] as $action) {
            $this->assertArrayHasKey(ModuleController::class.'::'.$action, $actions);
        }
    }

    public function test_a_submodule_is_switched_on_only_under_a_parent_that_is_on(): void
    {
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();
        $this->actingAs($this->admin(), 'admin')->post('/admin/modules/labels/disable');

        $this->post('/admin/modules/labels_qr/enable')
            ->assertSessionHas('error', __('atrium::modules.parent_off', ['parent' => $this->app->make(Modules::class)->find('acme/atrium-labels')->label()]));

        $this->assertTrue($this->app->make(Modules::class)->isDisabled('acme/atrium-labels-qr'));
    }

    public function test_only_a_module_in_the_other_state_is_switched(): void
    {
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();
        $this->artisan('atrium:module:remove', ['package' => 'acme/atrium-labels-printing'])->assertSuccessful();
        $this->actingAs($this->admin(), 'admin');

        $this->post('/admin/modules/labels/enable')->assertNotFound();
        $this->post('/admin/modules/labels_printing/disable')->assertNotFound();
        $this->post('/admin/modules/labels_printing/enable')->assertNotFound();
        $this->post('/admin/modules/nothing/disable')->assertNotFound();

        $this->post('/admin/modules/labels_qr/disable')->assertSessionHas('success');
        $this->post('/admin/modules/labels_qr/disable')->assertNotFound();
    }

    public function test_the_list_of_modules_switched_off_stays_in_order(): void
    {
        $modules = $this->app->make(Modules::class);
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();
        $printing = $modules->find('acme/atrium-labels-printing');
        $qr = $modules->find('acme/atrium-labels-qr');

        $modules->disable($printing);
        $modules->disable($qr);
        $this->assertSame(['acme/atrium-labels-printing', 'acme/atrium-labels-qr'], array_keys($modules->disabled()));

        $this->assertTrue($modules->enable($printing));
        $this->assertSame(['acme/atrium-labels-qr'], config('atrium.disabled_modules'));

        $modules->disable($printing);
        $this->assertTrue($modules->install($qr));
        $this->assertSame(['acme/atrium-labels-printing'], config('atrium.disabled_modules'));

        $modules->disable($qr);
        $this->assertTrue($modules->remove('ACME/Atrium-Labels-Printing'));
        $this->assertSame(['acme/atrium-labels-qr'], config('atrium.disabled_modules'));
        $this->assertFalse($modules->isDisabled('acme/atrium-labels-printing'));
        $this->assertFalse($modules->isInstalled('acme/atrium-labels-printing'));
    }

    public function test_a_module_switched_on_gets_its_pages_and_the_migrations_it_got_while_off(): void
    {
        $modules = $this->app->make(Modules::class);
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();
        $this->actingAs($this->admin(), 'admin')->post('/admin/modules/labels/disable');

        // As after a boot with the module off, and a migration that came
        // with an update.
        config()->set('atrium.public_urls', []);
        Schema::drop('labels');
        DB::table('migrations')->where('migration', 'like', '%create_labels_table')->delete();
        // Someone else's migration waiting for its own deploy: not run.
        mkdir($this->root.'/stray');
        file_put_contents($this->root.'/stray/2026_01_01_000000_create_strays_table.php', '<?php return new class extends Illuminate\Database\Migrations\Migration { public function up(): void { Illuminate\Support\Facades\Schema::create("strays", fn ($table) => $table->id()); } };');
        $this->app->make('migrator')->path($this->root.'/stray');
        // Where migrate asks for confirmation unless forced (and the CSRF
        // token is checked in tests too).
        $this->app['env'] = 'production';
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->post('/admin/modules/labels/enable')->assertSessionHas('success');

        $this->assertTrue(Schema::hasTable('labels'));
        $this->assertFalse(Schema::hasTable('strays'));
        $this->assertSame($modules->find('acme/atrium-labels')->publicUrls, config('atrium.public_urls'));
    }

    public function test_switching_a_module_on_leaves_its_removed_submodules_out(): void
    {
        $modules = $this->app->make(Modules::class);
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();
        $this->artisan('atrium:module:remove', ['package' => 'acme/atrium-labels-printing'])->assertSuccessful();

        $this->actingAs($this->admin(), 'admin')->post('/admin/modules/labels/disable');
        $this->post('/admin/modules/labels/enable')->assertSessionHas('success');

        $this->assertTrue($modules->isInstalled('acme/atrium-labels-qr'));
        $this->assertFalse($modules->isInstalled('acme/atrium-labels-printing'));
        $this->assertFalse($modules->isDisabled('acme/atrium-labels-printing'));
    }

    public function test_removing_a_module_removes_its_submodules_switched_off_too(): void
    {
        $modules = $this->app->make(Modules::class);
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();
        $this->artisan('atrium:module:remove', ['package' => 'acme/atrium-labels-printing'])->assertSuccessful();
        $this->actingAs($this->admin(), 'admin')->post('/admin/modules/labels_qr/disable');

        $this->artisan('atrium:module:remove', ['package' => 'acme/atrium-labels', '--keep-data' => true])
            ->doesntExpectOutputToContain('acme/atrium-labels-printing is not installed')
            ->assertSuccessful();

        $this->assertFalse($modules->isDisabled('acme/atrium-labels-qr'));
        $this->assertSame([], config('atrium.disabled_modules'));
        $this->assertSame([], config('atrium.modules'));
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

        $this->post('/admin/modules/labels_qr/disable')->assertSessionHas('success');
        $this->assertSame(['config:cache', 'route:cache', 'config:cache', 'route:cache'], $built);
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
        $acme = static fn ($required): array => array_values(array_filter($required->all(), static fn (array $module): bool => str_starts_with($module['name'], 'acme/')));

        $this->actingAs($this->admin(), 'admin')->get('/admin/modules')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('canManage', true)
            ->where('required', fn ($required): bool => array_column($acme($required), 'name') === ['acme/atrium-labels', 'acme/atrium-labels-printing', 'acme/atrium-labels-qr']));

        // Installed or switched off is not "required".
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();
        $this->artisan('atrium:module:remove', ['package' => 'acme/atrium-labels-printing'])->assertSuccessful();
        $this->post('/admin/modules/labels_qr/disable');
        $printing = $this->app->make(Modules::class)->find('acme/atrium-labels-printing');

        $this->get('/admin/modules')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('required', fn ($required): bool => $acme($required) === [
                ['name' => 'acme/atrium-labels-printing', 'label' => $printing->label(), 'description' => $printing->description],
            ]));
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
                ['name' => 'acme/atrium-labels-printing', 'description' => 'Printing', 'downloads' => 1],
            ]]),
        ]);
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-labels'])->assertSuccessful();
        $this->artisan('atrium:module:remove', ['package' => 'acme/atrium-labels-printing'])->assertSuccessful();
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
                ->where('catalogue.packages.4.name', 'acme/atrium-labels-printing')
                ->where('catalogue.packages.4.status', 'required')
                ->count('catalogue.packages', 5)));

        // Asked once, then cached.
        $this->app->make(Catalogue::class)->packages();
        Http::assertSentCount(2);
    }

    public function test_the_catalogue_follows_the_pages_and_cleans_up_what_packagist_sends(): void
    {
        Cache::forget('atrium.modules.catalogue');
        Http::fake(function (Request $request) {
            return match ($request->url()) {
                'https://packagist.org/search.json?type=atrium-module&per_page=100' => Http::response(['results' => [
                    'not a package',
                    ['name' => '', 'downloads' => 100],
                    ['name' => 'someone/atrium-gone', 'downloads' => 100, 'abandoned' => 'someone/atrium-new'],
                    ['name' => 'Someone/Atrium-Upper', 'description' => 'Upper', 'url' => 'https://example.com/upper', 'downloads' => '12'],
                    ['name' => 'someone/atrium-bare'],
                ], 'next' => 'https://packagist.org/search.json?type=atrium-module&page=2']),
                'https://packagist.org/search.json?type=atrium-module&page=2' => Http::response(['results' => [
                    ['name' => 'someone/atrium-second', 'description' => 42, 'downloads' => 1],
                ], 'next' => '']),
                'https://packagist.org/search.json?tags=atrium-module&per_page=100' => Http::response(['results' => [
                    ['name' => 'someone/atrium-upper', 'description' => 'Upper', 'url' => 'https://example.com/upper', 'downloads' => 12],
                ]]),
                default => Http::response('', 404),
            };
        });

        $this->assertSame([
            ['name' => 'someone/atrium-upper', 'description' => 'Upper', 'url' => 'https://example.com/upper', 'downloads' => 12],
            ['name' => 'someone/atrium-second', 'description' => '42', 'url' => 'https://packagist.org/packages/someone/atrium-second', 'downloads' => 1],
            ['name' => 'someone/atrium-bare', 'description' => '', 'url' => 'https://packagist.org/packages/someone/atrium-bare', 'downloads' => 0],
        ], $this->app->make(Catalogue::class)->packages());
        Http::assertSentCount(3);
    }

    public function test_the_catalogue_asks_for_five_pages_at_most(): void
    {
        Cache::forget('atrium.modules.catalogue');
        Http::fake(fn (Request $request) => Http::response(['results' => [], 'next' => $request->url().'&more']));

        $this->assertSame([], $this->app->make(Catalogue::class)->packages());
        Http::assertSentCount(10);
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
