<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Modules\Module;
use Atrium\Admin\Modules\Modules;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\ShelfDemoSeeder;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Console\ClosureCommand;

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
}
