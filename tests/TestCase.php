<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests;

use Atrium\Admin\AtriumServiceProvider;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Tests\Fixtures\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Routing\Route;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Laravel\Mcp\Server\McpServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use ReflectionMethod;
use Tighten\Ziggy\ZiggyServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /**
     * On MySQL and PostgreSQL the whole suite shares one database: each
     * class migrates it again with the tables and modules it uses.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        RefreshDatabaseState::$migrated = false;
    }

    public static function tearDownAfterClass(): void
    {
        RefreshDatabaseState::$migrated = false;
        parent::tearDownAfterClass();
    }

    protected function getPackageProviders($app): array
    {
        return [
            InertiaServiceProvider::class,
            ZiggyServiceProvider::class,
            McpServiceProvider::class,
            AtriumServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $target = env('DB_CONNECTION');
        $app['config']->set('database.default', in_array($target, ['mysql', 'pgsql'], true) ? $target : 'testing');
        $app['config']->set('atrium.resources', [Post::class]);
        $app['config']->set('atrium.auth.two_factor.enabled', false);
        $app['config']->set('atrium.dashboard.timings.enabled', false);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    /**
     * A superadmin: may do everything, like every administrator before roles.
     */
    protected function admin(): AdminUser
    {
        $admin = AdminUser::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        $admin->forceFill(['is_superadmin' => true])->save();

        return $admin;
    }

    /**
     * What a route of Atrium calls is public: a module's controller extends
     * these, and the router calls a controller without Laravel's
     * callAction() from outside. Called after the actions ran, so a change to
     * one of them is checked by the tests that reach it.
     *
     * @return array<string,true> Class::method of every action
     */
    protected function assertRouteActionsArePublic(): array
    {
        $actions = [];

        foreach ($this->app['router']->getRoutes() as $route) {
            /** @var Route $route */
            $controller = $route->getControllerClass();

            if ($controller !== null && str_starts_with($controller, 'Atrium\\')) {
                $method = new ReflectionMethod($controller, $route->getActionMethod() === $controller ? '__invoke' : $route->getActionMethod());
                $actions[$method->class.'::'.$method->name] = $method->isPublic();
            }
        }

        $this->assertNotContains(false, $actions, implode(', ', array_keys(array_filter($actions, static fn (bool $public): bool => ! $public))));

        return $actions;
    }
}
