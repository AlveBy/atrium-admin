<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Http\Controllers\AdminController;
use Atrium\Admin\Http\Controllers\Api\ResourceController;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use ReflectionClass;

/**
 * What a route calls is public: a module's controller extends these, and the
 * router calls a controller without Laravel's callAction() from outside.
 */
final class RouteActionsTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Sanctum::class))->getFileName(), 2).'/database/migrations');
    }

    public function test_every_action_a_request_reaches_in_the_panel_and_the_api_is_public(): void
    {
        $admin = $this->admin();
        [$first, $second] = [Post::query()->create(['name' => 'First']), Post::query()->create(['name' => 'Second'])];
        $this->actingAs($admin, 'admin')->get('/admin/posts')->assertOk();
        $this->delete("/admin/posts/{$first->id}")->assertRedirect();
        $token = $admin->createToken('agent', ['posts:read', 'posts:write'])->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/posts')->assertOk()->assertJsonCount(1, 'data');
        $this->withToken($token)->deleteJson("/api/v1/posts/{$second->id}")->assertNoContent();
        $this->assertSame(0, Post::query()->count());

        $actions = $this->assertRouteActionsArePublic();

        $this->assertArrayHasKey(AdminController::class.'::destroy', $actions);
        $this->assertArrayHasKey(ResourceController::class.'::destroyItem', $actions);
        $this->assertArrayHasKey(AdminController::class.'::index', $actions);
        $this->assertArrayHasKey(ResourceController::class.'::listItems', $actions);
    }
}
