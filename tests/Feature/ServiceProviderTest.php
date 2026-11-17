<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\AtriumServiceProvider;
use Atrium\Admin\Http\Middleware\RecordPageTiming;
use Atrium\Admin\Http\Middleware\ResolveSite;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\Attributes\DefineEnvironment;

final class ServiceProviderTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // After the base class, which switches page timings off for the suite.
        if ($this->name() === 'test_switched_on_page_timings_join_the_web_group') {
            $app['config']->set('atrium.dashboard.timings.enabled', true);
        }

        if ($this->name() === 'test_the_app_routes_file_adds_pages_inside_the_panel') {
            file_put_contents($app->basePath('routes/atrium.php'), "<?php\n\nIlluminate\\Support\\Facades\\Route::get('reports', fn () => 'Reports')->name('admin.reports');\n");
        }
    }

    protected function tearDown(): void
    {
        @unlink($this->app->basePath('routes/atrium.php'));

        parent::tearDown();
    }

    /**
     * Served by a web server: nothing to publish, no commands.
     */
    protected function outsideTheConsole(Application $app): void
    {
        (fn () => $this->isRunningInConsole = false)->call($app);
        ServiceProvider::$publishes = [];
        ServiceProvider::$publishGroups = [];
    }

    /**
     * @return array<int,string>
     */
    private function webGroup(): array
    {
        return $this->app->make(HttpKernel::class)->getMiddlewareGroups()['web'];
    }

    public function test_page_timings_are_recorded_only_when_switched_on(): void
    {
        $this->assertContains(ResolveSite::class, $this->webGroup());
        $this->assertNotContains(RecordPageTiming::class, $this->webGroup());
    }

    public function test_switched_on_page_timings_join_the_web_group(): void
    {
        $this->assertContains(ResolveSite::class, $this->webGroup());
        $this->assertContains(RecordPageTiming::class, $this->webGroup());
    }

    public function test_without_an_app_routes_file_the_panel_has_only_its_own_pages(): void
    {
        $this->assertFileDoesNotExist(base_path('routes/atrium.php'));
        $this->assertFalse($this->app['router']->has('admin.reports'));
    }

    public function test_the_app_routes_file_adds_pages_inside_the_panel(): void
    {
        $route = $this->app['router']->getRoutes()->getByName('admin.reports');

        $this->assertNotNull($route);
        $this->assertSame('admin/reports', $route->uri());
        $this->assertSame(['web', 'atrium.inertia', 'admin.auth', 'admin.can'], $route->middleware());
        $this->get('/admin/reports')->assertRedirect(route('admin.login'));
        $this->actingAs($this->admin(), 'admin')->get('/admin/reports')->assertOk()->assertSee('Reports');
    }

    public function test_the_console_publishes_every_part_of_the_panel(): void
    {
        $src = realpath(__DIR__.'/../../src');

        $paths = static fn (string $tag): array => array_map(
            static fn (string $from): string|false => realpath($from),
            array_keys(ServiceProvider::pathsToPublish(AtriumServiceProvider::class, $tag)),
        );

        $this->assertSame([realpath($src.'/../config/atrium.php')], $paths('atrium-config'));
        $this->assertSame([config_path('atrium.php')], array_values(ServiceProvider::pathsToPublish(AtriumServiceProvider::class, 'atrium-config')));
        $this->assertSame([realpath($src.'/../database/migrations')], $paths('atrium-migrations'));
        $this->assertSame([database_path('migrations')], array_values(ServiceProvider::pathsToPublish(AtriumServiceProvider::class, 'atrium-migrations')));
        $this->assertSame([realpath($src.'/../resources/views')], $paths('atrium-views'));
        $this->assertSame([resource_path('views/vendor/atrium')], array_values(ServiceProvider::pathsToPublish(AtriumServiceProvider::class, 'atrium-views')));
        $this->assertSame([realpath($src.'/../lang')], $paths('atrium-lang'));
        $this->assertSame([$this->app->langPath('vendor/atrium')], array_values(ServiceProvider::pathsToPublish(AtriumServiceProvider::class, 'atrium-lang')));
        $this->assertSame([public_path('vendor/atrium')], array_values(ServiceProvider::pathsToPublish(AtriumServiceProvider::class, 'atrium-assets')));
        $this->assertSame([public_path('vendor/atrium')], array_values(ServiceProvider::pathsToPublish(AtriumServiceProvider::class, 'laravel-assets')));
    }

    public function test_the_console_has_the_panel_commands(): void
    {
        $commands = array_keys($this->app->make(ConsoleKernel::class)->all());

        foreach (['atrium:install', 'atrium:module:install', 'atrium:module:remove'] as $command) {
            $this->assertContains($command, $commands);
        }
    }

    #[DefineEnvironment('outsideTheConsole')]
    public function test_outside_the_console_nothing_is_published_and_no_command_is_added(): void
    {
        $this->assertFalse($this->app->runningInConsole());
        $this->assertSame([], ServiceProvider::pathsToPublish(AtriumServiceProvider::class, 'atrium-config'));
        $this->assertSame([], ServiceProvider::pathsToPublish(AtriumServiceProvider::class, 'atrium-assets'));
        $this->assertNotContains('atrium:install', array_keys($this->app->make(ConsoleKernel::class)->all()));

        // The panel itself is still there.
        $this->assertTrue(view()->exists('atrium::app'));
    }
}
