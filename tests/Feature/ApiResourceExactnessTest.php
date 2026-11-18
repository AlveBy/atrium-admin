<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use ReflectionClass;

/**
 * The module API, one condition at a time: what filters and sorts accept,
 * what a token needs for each call, and which relations come back.
 */
final class ApiResourceExactnessTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Post::class]);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Sanctum::class))->getFileName(), 2).'/database/migrations');
    }

    /**
     * @param  array<int,string>  $abilities
     */
    private function api(array $abilities = ['posts:read', 'posts:write']): static
    {
        $owner = AdminUser::query()->where('email', 'owner@example.com')->first()
            ?? AdminUser::query()->create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'secret-password', 'is_active' => true]);
        $owner->forceFill(['is_superadmin' => true])->save();
        $this->app['auth']->forgetGuards();

        return $this->withToken($owner->createToken('agent', $abilities)->plainTextToken);
    }

    /**
     * @return array<int,string>
     */
    private function names(string $url): array
    {
        return $this->api()->getJson($url)->assertOk()->json('data.*.name');
    }

    public function test_a_search_or_a_sort_that_is_a_list_does_not_break_the_page(): void
    {
        Post::query()->create(['name' => 'Hello']);
        Post::query()->create(['name' => 'Bye']);

        $this->assertSame(['Hello', 'Bye'], $this->names('/api/v1/posts?search[]=Hello'));
        $this->assertSame(['Bye', 'Hello'], $this->names('/api/v1/posts?sort[]=name&direction=desc'), 'By id, newest first.');
    }
}
