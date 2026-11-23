<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Api\ApiDocumentation;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Tests\Fixtures\Kit;
use Atrium\Admin\Tests\Fixtures\Note;
use Atrium\Admin\Tests\Fixtures\Page;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use ReflectionClass;

/**
 * Past the threshold only the form searches: the API, its docs and MCP
 * read and write relation fields as they did with few records.
 */
final class RelationFieldsOutsideTheFormTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Kit::class, Post::class, Note::class, Page::class]);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Sanctum::class))->getFileName(), 2).'/database/migrations');
    }

    public function test_the_api_creates_and_updates_links_among_many_records(): void
    {
        $posts = $this->posts(10);
        $token = $this->token();

        $created = $this->withToken($token)
            ->postJson('/api/v1/kits', ['name' => 'Kit', 'post_id' => $posts[7], 'posts' => [$posts[8], $posts[2]]])
            ->assertCreated();

        $kit = Kit::query()->sole();
        $this->assertSame($posts[7], $kit->post_id);
        $this->assertEqualsCanonicalizing([$posts[8], $posts[2]], $kit->posts()->pluck('posts.id')->all());
        $this->assertEqualsCanonicalizing([$posts[8], $posts[2]], $created->json('data.posts'));

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->patchJson("/api/v1/kits/{$kit->id}", ['posts' => [$posts[9]]])->assertOk()
            ->assertJsonPath('data.posts', [$posts[9]]);

        $this->assertSame([$posts[9]], $kit->posts()->pluck('posts.id')->all());
        $this->assertSame($posts[7], $kit->fresh()?->post_id, 'a partial update leaves the other link');
    }

    public function test_the_api_still_refuses_a_record_that_does_not_exist(): void
    {
        $posts = $this->posts(10);

        $this->withToken($this->token())
            ->postJson('/api/v1/kits', ['name' => 'Kit', 'post_id' => $posts[9] + 100])
            ->assertStatus(422)->assertJsonValidationErrors('post_id');

        $this->assertSame(0, Kit::query()->count());
    }

    public function test_the_docs_list_the_records_the_form_searches(): void
    {
        $posts = $this->posts(10);

        $fields = collect(app(ApiDocumentation::class)->modules(['kits'])['kits']['fields'])->keyBy('name');

        $this->assertCount(10, $fields['post_id']['options']);
        $this->assertSame(['value' => $posts[0], 'label' => 'Post 1'], $fields['post_id']['options'][0]);
        $this->assertCount(10, $fields['posts']['options'], 'the field query keeps every post active');
    }

    public function test_mcp_writes_and_reads_links_among_many_records(): void
    {
        $posts = $this->posts(10);
        $token = $this->token(['kits:read', 'kits:write']);

        $this->withToken($token)->postJson('/mcp', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
            'params' => ['name' => 'create_record', 'arguments' => ['module' => 'kits', 'data' => ['name' => 'Kit', 'post_id' => $posts[4], 'posts' => [$posts[6]]]]],
        ])->assertOk()->assertJsonMissingPath('error');

        $kit = Kit::query()->sole();
        $this->assertSame($posts[4], $kit->post_id);
        $this->assertSame([$posts[6]], $kit->posts()->pluck('posts.id')->all());

        $this->app['auth']->forgetGuards();
        $read = $this->withToken($token)->postJson('/mcp', [
            'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call',
            'params' => ['name' => 'get_record', 'arguments' => ['module' => 'kits', 'id' => $kit->id]],
        ])->assertOk();

        $record = json_decode((string) $read->json('result.content.0.text'), true);
        $this->assertSame([$posts[6]], $record['data']['posts']);
        $this->assertSame($posts[4], $record['data']['post_id']);
    }

    /**
     * @return array<int,int> ids, the first one first
     */
    private function posts(int $count): array
    {
        $ids = [];

        for ($i = 1; $i <= $count; $i++) {
            $ids[] = Post::query()->create(['name' => "Post {$i}", 'active' => true])->id;
        }

        return $ids;
    }

    /**
     * @param  array<int,string>  $abilities
     */
    private function token(array $abilities = ['kits:read', 'kits:write']): string
    {
        $owner = AdminUser::query()->create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'secret-password', 'is_active' => true]);
        $owner->forceFill(['is_superadmin' => true])->save();

        return $owner->createToken('agent', $abilities)->plainTextToken;
    }
}
