<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Modules\Module;
use Atrium\Admin\Modules\Modules;
use Atrium\Admin\Tests\Fixtures\Page;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\ShelfDemoSeeder;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * atrium:module:install and atrium:module:remove where things go wrong:
 * a config they cannot edit, a missing config, demo content and cached
 * config.
 */
final class ModuleCommandsTest extends TestCase
{
    private const SHELVES = 'acme/atrium-shelves';

    private string $root;

    /** @var array<string,int> */
    private array $built = ['config:cache' => 0, 'route:cache' => 0];

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app->resolving(Modules::class, static fn (Modules $modules) => $modules->add(new Module(name: self::SHELVES, demo: ShelfDemoSeeder::class)));

        // The commands edit config/atrium.php: a copy, never the package's.
        $this->root = sys_get_temp_dir().'/atrium-module-commands-'.uniqid();
        mkdir($this->root.'/config', 0777, true);
        copy(dirname(__DIR__, 2).'/config/atrium.php', $this->root.'/config/atrium.php');
        $app->useConfigPath($this->root.'/config');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }

    /**
     * Stand-ins for config:cache and route:cache, which would cache the
     * shared test app; they count their runs here.
     */
    private function standIns(): void
    {
        $built = &$this->built;

        foreach (array_keys($this->built) as $name) {
            $this->app->make(ConsoleKernel::class)->registerCommand(new ClosureCommand($name, function () use (&$built, $name): void {
                $built[$name]++;
            }));
        }
    }

    public function test_demo_content_is_filled_only_when_asked(): void
    {
        $this->artisan('atrium:module:install', ['package' => self::SHELVES])->assertSuccessful();
        $this->assertSame(0, Post::query()->count());

        $this->artisan('atrium:module:install', ['package' => self::SHELVES, '--demo' => true])
            ->expectsOutputToContain('Filling it with demo content')
            ->assertSuccessful();
        $this->assertSame(['Demo shelf'], Post::query()->pluck('name')->all());
    }

    public function test_a_config_without_the_lists_is_refused_with_what_to_do(): void
    {
        file_put_contents($this->root.'/config/atrium.php', "<?php\n\nreturn ['prefix' => 'admin'];\n");

        $this->artisan('atrium:module:install', ['package' => self::SHELVES])
            ->expectsOutputToContain("config/atrium.php could not be edited: add '".self::SHELVES."' to its 'modules' list")
            ->assertFailed();

        $this->assertNotContains(self::SHELVES, (array) config('atrium.modules'));
        $this->assertStringNotContainsString(self::SHELVES, (string) file_get_contents($this->root.'/config/atrium.php'));
    }

    public function test_a_missing_config_is_published_first(): void
    {
        unlink($this->root.'/config/atrium.php');
        $published = [];
        $this->app->make(ConsoleKernel::class)->registerCommand(new ClosureCommand('vendor:publish {--tag=}', function () use (&$published): void {
            $published[] = $this->option('tag');
            copy(dirname(__DIR__, 2).'/config/atrium.php', config_path('atrium.php'));
        }));

        $this->artisan('atrium:module:install', ['package' => self::SHELVES])->assertSuccessful();

        $this->assertSame(['atrium-config'], $published);
        $this->assertSame([self::SHELVES], (require $this->root.'/config/atrium.php')['modules']);

        $this->artisan('atrium:module:install', ['package' => self::SHELVES])->assertSuccessful();
        $this->assertSame(['atrium-config'], $published, 'An existing config is not published again.');
    }

    public function test_remove_reports_a_config_it_cannot_edit(): void
    {
        $this->artisan('atrium:module:install', ['package' => self::SHELVES])->assertSuccessful();
        unlink($this->root.'/config/atrium.php');

        $this->artisan('atrium:module:remove', ['package' => self::SHELVES])
            ->expectsOutputToContain("config/atrium.php could not be edited: take '".self::SHELVES."' out of its 'modules' list.")
            ->assertFailed();

        $this->assertContains(self::SHELVES, (array) config('atrium.modules'));
    }

    public function test_cached_config_and_routes_are_built_again_only_when_they_were_cached(): void
    {
        $this->standIns();

        $this->artisan('atrium:module:install', ['package' => self::SHELVES])->assertSuccessful();
        $this->artisan('atrium:module:remove', ['package' => self::SHELVES])->assertSuccessful();
        $this->assertSame(['config:cache' => 0, 'route:cache' => 0], $this->built);

        $this->app->instance('config_loaded_from_cache', true);
        $this->artisan('atrium:module:install', ['package' => self::SHELVES])->assertSuccessful();
        $this->assertSame(['config:cache' => 1, 'route:cache' => 0], $this->built);

        $this->app->instance('config_loaded_from_cache', false);
        $this->app->instance('routes.cached', true);
        $this->artisan('atrium:module:remove', ['package' => self::SHELVES])->assertSuccessful();
        $this->assertSame(['config:cache' => 1, 'route:cache' => 1], $this->built);
    }

    /**
     * @return array<string,array{int,string}>
     */
    public static function exitCodes(): array
    {
        return ['succeeds' => [0, 'DONE'], 'fails' => [1, 'FAIL']];
    }

    /**
     * A module with a migrations folder and a migrate that only records how it was called.
     *
     * @param  array<int,array<string,mixed>>  $calls
     */
    private function crates(int $exit, array &$calls): string
    {
        $path = $this->root.'/crates';
        mkdir($path);
        $this->app->make(Modules::class)->add(new Module(name: 'acme/atrium-crates', migrations: [$path]));

        foreach (['migrate', 'migrate:reset'] as $name) {
            $this->app->make(ConsoleKernel::class)->registerCommand(new ClosureCommand($name.' {--path=*} {--realpath} {--force}', function () use (&$calls, $name, $exit): int {
                $calls[] = [$name, $this->option('path'), $this->option('realpath'), $this->option('force')];

                return $exit;
            }));
        }

        return $path;
    }

    private function printed(string $command, array $options): string
    {
        $output = new BufferedOutput;
        Artisan::call($command, $options, $output);

        return $output->fetch();
    }

    #[DataProvider('exitCodes')]
    public function test_the_migrations_of_a_module_are_run_for_its_folder_and_reported_by_their_result(int $exit, string $result): void
    {
        $calls = [];
        $path = $this->crates($exit, $calls);

        $printed = $this->printed('atrium:module:install', ['package' => 'acme/atrium-crates']);

        $this->assertSame([['migrate', [$path], true, true]], $calls);
        $this->assertMatchesRegularExpression('/Running its migrations[ .]*\S* '.$result.'/', $printed);
    }

    #[DataProvider('exitCodes')]
    public function test_the_tables_of_a_module_are_dropped_for_its_folder_and_reported_by_their_result(int $exit, string $result): void
    {
        $calls = [];
        $path = $this->crates($exit, $calls);
        $this->printed('atrium:module:install', ['package' => 'acme/atrium-crates']);
        $calls = [];

        $printed = $this->printed('atrium:module:remove', ['package' => 'acme/atrium-crates', '--delete-data' => true]);

        $this->assertSame([['migrate:reset', [$path], true, true]], $calls);
        $this->assertMatchesRegularExpression('/Dropping its tables[ .]*\S* '.$result.'/', $printed);
    }

    public function test_an_unknown_module_is_refused_with_the_modules_that_could_be_installed(): void
    {
        $printed = $this->printed('atrium:module:install', ['package' => 'acme/atrium-missing']);

        $this->assertStringContainsString('acme/atrium-missing is not an Atrium module Composer has installed.', $printed);
        $this->assertStringContainsString(self::SHELVES, $printed);

        $this->artisan('atrium:module:install', ['package' => self::SHELVES])->assertSuccessful();
        $this->assertStringNotContainsString(self::SHELVES, $this->printed('atrium:module:install', ['package' => 'acme/atrium-missing']));
    }

    public function test_the_closing_line_names_the_keys_the_module_added_to_the_menu(): void
    {
        $this->app->make(Modules::class)->add(new Module(name: 'acme/atrium-posts', resources: [Post::class]));

        $this->assertStringContainsString(
            'acme/atrium-posts is installed. In the menu and the role form: posts.',
            $this->printed('atrium:module:install', ['package' => 'acme/atrium-posts']),
        );
        $this->assertStringContainsString(
            'acme/atrium-posts was installed already; its tables are up to date.',
            $this->printed('atrium:module:install', ['package' => 'acme/atrium-posts']),
        );
    }

    public function test_the_menu_keys_are_those_of_the_modules_that_can_be_administered_all_of_them(): void
    {
        $this->app->make(Modules::class)->add(new Module(name: 'acme/atrium-mixed', resources: [Post::class, 'Acme\\Missing', \stdClass::class, Page::class]));

        $this->assertStringContainsString(
            'acme/atrium-mixed is installed. In the menu and the role form: posts, pages',
            $this->printed('atrium:module:install', ['package' => 'acme/atrium-mixed']),
        );
    }

    public function test_the_package_name_is_trimmed_and_lowercased(): void
    {
        $this->artisan('atrium:module:install', ['package' => '  ACME/Atrium-Shelves '])->assertSuccessful();

        $this->assertContains(self::SHELVES, (require $this->root.'/config/atrium.php')['modules']);
    }

    public function test_the_installed_hook_is_fired_with_the_module(): void
    {
        $heard = [];
        Hook::on('atrium.module.installed', static function (Module $module) use (&$heard): void {
            $heard[] = $module->name;
        });

        $this->artisan('atrium:module:install', ['package' => self::SHELVES])->assertSuccessful();

        $this->assertSame([self::SHELVES], $heard);
    }

    public function test_a_submodule_is_filled_with_demo_content_only_when_its_parent_is(): void
    {
        $modules = $this->app->make(Modules::class);
        $modules->add(new Module(name: 'acme/atrium-parent'));
        $modules->add(new Module(name: 'acme/atrium-child', demo: ShelfDemoSeeder::class, parent: 'acme/atrium-parent'));

        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-parent'])->assertSuccessful();
        $this->assertSame(0, Post::query()->count());

        $this->artisan('atrium:module:remove', ['package' => 'acme/atrium-parent'])->assertSuccessful();
        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-parent', '--demo' => true])->assertSuccessful();
        $this->assertSame(['Demo shelf'], Post::query()->pluck('name')->all());
    }

    public function test_a_permission_that_comes_with_another_goes_to_the_roles_that_have_it_once(): void
    {
        $this->app->make(Modules::class)->add(new Module(name: 'acme/atrium-orders', declaredPermissions: [
            'orders.refund' => ['label' => 'Refund', 'with' => 'orders.view'],
            'orders.export' => ['label' => 'Export', 'with' => 'orders.view'],
            'orders.audit' => ['label' => 'Audit', 'with' => null],
        ]));
        $holder = AdminRole::query()->create(['name' => 'Holder', 'permissions' => ['orders.view', 'orders.view', 'posts.view']]);
        $already = AdminRole::query()->create(['name' => 'Already', 'permissions' => ['orders.view', 'orders.refund', 'orders.export']]);
        $other = AdminRole::query()->create(['name' => 'Other', 'permissions' => ['posts.view']]);
        DB::table('admin_roles')->insert(['name' => 'Nothing', 'permissions' => 'null', 'created_at' => now(), 'updated_at' => now()]);
        $nothing = AdminRole::query()->where('name', 'Nothing')->sole();
        $saved = [];
        AdminRole::saved(static function (AdminRole $role) use (&$saved): void {
            $saved[] = $role->name;
        });

        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-orders'])->assertSuccessful();

        $this->assertSame(['orders.view', 'posts.view', 'orders.refund', 'orders.export'], $holder->fresh()->permissions);
        $this->assertSame(['orders.view', 'orders.refund', 'orders.export'], $already->fresh()->permissions);
        $this->assertSame(['posts.view'], $other->fresh()->permissions);
        $this->assertSame([], (array) $nothing->fresh()->permissions);
        $this->assertSame(['Holder'], $saved, 'Only a role that lacked something was written.');
    }

    public function test_a_module_without_permissions_that_come_with_another_leaves_the_roles_alone(): void
    {
        $role = AdminRole::query()->create(['name' => 'Holder', 'permissions' => ['orders.view']]);
        $saved = 0;
        AdminRole::saved(static function () use (&$saved): void {
            $saved++;
        });
        $this->app->make(Modules::class)->add(new Module(name: 'acme/atrium-orders', declaredPermissions: ['orders.audit' => ['label' => 'Audit', 'with' => null]]));

        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-orders'])->assertSuccessful();

        $this->assertSame(0, $saved);
        $this->assertSame(['orders.view'], $role->fresh()->permissions);
    }

    public function test_the_permissions_step_is_skipped_when_the_roles_table_is_missing(): void
    {
        $this->app->make(Modules::class)->add(new Module(name: 'acme/atrium-orders', declaredPermissions: ['orders.refund' => ['label' => 'Refund', 'with' => 'orders.view']]));
        Schema::drop('admin_role_site_user');
        Schema::drop('admin_role_user');
        Schema::drop('admin_roles');

        $this->artisan('atrium:module:install', ['package' => 'acme/atrium-orders'])->assertSuccessful();

        $this->assertContains('acme/atrium-orders', (require $this->root.'/config/atrium.php')['modules']);
    }
}
