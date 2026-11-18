<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Tests\Fixtures\Bundle;
use Atrium\Admin\Tests\Fixtures\Page;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Topic;
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

        $app['config']->set('atrium.resources', [Post::class, Page::class, Bundle::class, Topic::class]);
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

    public function test_a_filter_on_a_foreign_column_does_not_stop_the_filters_after_it(): void
    {
        Post::query()->create(['name' => 'Hello']);
        Post::query()->create(['name' => 'Bye']);

        $this->assertSame(['Hello'], $this->names('/api/v1/posts?filter[password]=x&filter[name]=Hello'));
    }

    public function test_the_id_and_the_dates_are_fields_to_filter_and_to_sort_by(): void
    {
        $first = Post::query()->create(['name' => 'First']);
        $second = Post::query()->create(['name' => 'Second']);
        $first->forceFill(['created_at' => '2026-03-01 00:00:00', 'updated_at' => '2026-03-03 00:00:00'])->saveQuietly();
        $second->forceFill(['created_at' => '2026-03-02 00:00:00', 'updated_at' => '2026-03-01 00:00:00'])->saveQuietly();

        $this->assertSame(['Second'], $this->names("/api/v1/posts?filter[id]={$second->id}"));
        $this->assertSame(['First', 'Second'], $this->names('/api/v1/posts?sort=created_at'));
        $this->assertSame(['Second', 'First'], $this->names('/api/v1/posts?sort=created_at&direction=desc'));
        $this->assertSame(['Second', 'First'], $this->names('/api/v1/posts?sort=updated_at'));
        $this->assertSame(['Second', 'First'], $this->names('/api/v1/posts?sort=id&direction=desc'));
        $this->assertSame(['First', 'Second'], $this->names('/api/v1/posts?sort=password'), 'Not a field: by id.');
    }

    public function test_a_translated_field_is_filtered_by_its_text_in_any_language(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        $first = Page::query()->create(['name' => ['en' => 'First', 'de' => 'Erste']]);
        $second = Page::query()->create(['name' => ['en' => 'Second', 'de' => 'Zweite']]);
        $ids = fn (string $text): array => $this->api(['pages:read'])->getJson('/api/v1/pages?filter[name]='.urlencode($text))->assertOk()->json('data.*.id');

        $this->assertSame([$first->id], $ids('First'));
        $this->assertSame([$first->id], $ids('Erste'));
        $this->assertSame([$second->id], $ids('Zweite'));
        $this->assertSame([], $ids('Third'));
    }

    public function test_a_token_without_the_ability_for_the_call_is_refused_with_403(): void
    {
        $this->api(['posts:read'])->postJson('/api/v1/posts', ['name' => 'New'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'The token lacks the posts:write ability.');
        $this->api(['posts:write'])->getJson('/api/v1/posts')
            ->assertStatus(403)
            ->assertJsonPath('message', 'The token lacks the posts:read ability.');
        $this->api(['pages:read'])->getJson('/api/v1/posts')->assertStatus(403);

        $this->assertSame(0, Post::query()->count());
    }

    public function test_every_relation_of_a_record_comes_back_with_it(): void
    {
        $bundle = Bundle::query()->create(['name' => 'Starter']);
        $post = Post::query()->create(['name' => 'Hello']);
        $topic = Topic::query()->create(['name' => 'News']);
        $bundle->posts()->attach($post);
        $bundle->topics()->attach($topic);

        $this->api(['bundles:read'])->getJson('/api/v1/bundles')
            ->assertOk()
            ->assertJsonPath('data.0.posts', [$post->id])
            ->assertJsonPath('data.0.topics', [$topic->id]);
        $this->api(['bundles:read'])->getJson("/api/v1/bundles/{$bundle->id}")
            ->assertJsonPath('data.posts', [$post->id])
            ->assertJsonPath('data.topics', [$topic->id]);
    }

    public function test_a_search_or_a_sort_that_is_a_list_does_not_break_the_page(): void
    {
        Post::query()->create(['name' => 'Hello']);
        Post::query()->create(['name' => 'Bye']);

        $this->assertSame(['Hello', 'Bye'], $this->names('/api/v1/posts?search[]=Hello'));
        $this->assertSame(['Bye', 'Hello'], $this->names('/api/v1/posts?sort[]=name&direction=desc'), 'By id, newest first.');
    }
}
