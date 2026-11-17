<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Mcp\Tools\GetRecord;
use Atrium\Admin\Mcp\Tools\ListModules;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;

/**
 * The MCP server at /mcp: the admin modules for AI assistants, with the API's
 * tokens and the API's rules.
 */
final class McpTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Sanctum::class))->getFileName(), 2).'/database/migrations');
    }

    public function test_it_needs_a_token(): void
    {
        $this->postJson('/mcp', $this->rpc('tools/list'))->assertUnauthorized();
    }

    public function test_it_lists_the_tools(): void
    {
        $names = array_column($this->mcp($this->token(['posts.view']), 'tools/list')->json('result.tools'), 'name');

        $this->assertSame(
            ['list_modules', 'describe_module', 'list_records', 'get_record', 'create_record', 'update_record', 'delete_record'],
            $names,
        );
    }

    public function test_list_modules_shows_what_the_token_and_its_owner_allow(): void
    {
        $token = $this->token(['posts.view', 'posts.update'], ['posts:read', 'posts:write']);

        $this->assertSame(
            [['module' => 'posts', 'label' => 'Posts', 'allowed' => ['view', 'update']]],
            $this->callTool($token, 'list_modules'),
        );
    }

    public function test_describe_module_gives_the_fields(): void
    {
        $module = $this->callTool($this->token(['posts.view']), 'describe_module', ['module' => 'posts']);

        $this->assertSame(['name', 'active', 'sort'], array_column($module['fields'], 'name'));
        $this->assertTrue($module['fields'][0]['required']);
    }

    public function test_an_assistant_creates_finds_changes_and_deletes_a_record(): void
    {
        $token = $this->token(['posts.view', 'posts.create', 'posts.update', 'posts.delete'], ['posts:read', 'posts:write']);

        $created = $this->callTool($token, 'create_record', ['module' => 'posts', 'data' => ['name' => 'Hello', 'active' => true, 'sort' => 10]]);
        $this->assertSame('Hello', $created['data']['name']);
        $id = $created['data']['id'];

        $found = $this->callTool($token, 'list_records', ['module' => 'posts', 'search' => 'Hell']);
        $this->assertSame([$id], array_column($found['data'], 'id'));

        // Only the changed field: the required name is not sent again.
        $this->callTool($token, 'update_record', ['module' => 'posts', 'id' => $id, 'data' => ['sort' => 20]]);
        $this->assertSame(20, Post::query()->findOrFail($id)->sort);
        $this->assertSame('Hello', $this->callTool($token, 'get_record', ['module' => 'posts', 'id' => $id])['data']['name']);

        $this->mcp($token, 'tools/call', ['name' => 'delete_record', 'arguments' => ['module' => 'posts', 'id' => $id]])
            ->assertJsonPath('result.isError', false);
        $this->assertSame(0, Post::query()->count());
    }

    public function test_invalid_fields_come_back_as_an_error_the_assistant_can_read(): void
    {
        $token = $this->token(['posts.view', 'posts.create'], ['posts:read', 'posts:write']);

        $this->mcp($token, 'tools/call', ['name' => 'create_record', 'arguments' => ['module' => 'posts', 'data' => ['sort' => 1]]])
            ->assertJsonPath('result.isError', true)
            ->assertSee('HTTP 422')
            ->assertSee('- name:');

        $this->assertSame(0, Post::query()->count());
    }

    public function test_a_read_only_token_cannot_write(): void
    {
        $token = $this->token(['posts.view', 'posts.create'], ['posts:read']);

        $this->mcp($token, 'tools/call', ['name' => 'create_record', 'arguments' => ['module' => 'posts', 'data' => ['name' => 'Nope']]])
            ->assertJsonPath('result.isError', true)
            ->assertSee('The token lacks the posts:write ability.');

        $this->assertSame(0, Post::query()->count());
    }

    public function test_the_token_never_gives_more_than_its_owner_has(): void
    {
        $token = $this->token(['posts.view'], ['posts:read', 'posts:write']);

        $this->mcp($token, 'tools/call', ['name' => 'create_record', 'arguments' => ['module' => 'posts', 'data' => ['name' => 'Nope']]])
            ->assertJsonPath('result.isError', true)
            ->assertSee('The token owner lacks the posts.create permission.');

        $this->assertSame(0, Post::query()->count());
    }

    public function test_the_module_argument_cannot_point_at_another_api_address(): void
    {
        $token = $this->token(['posts.view', 'posts.create'], ['posts:read', 'posts:write']);

        foreach (['files', 'posts/../files', 'users'] as $module) {
            $this->mcp($token, 'tools/call', ['name' => 'create_record', 'arguments' => ['module' => $module, 'data' => []]])
                ->assertJsonPath('result.isError', true)
                ->assertSee('See list_modules.');
        }
    }

    /**
     * @return array<string,array{0:string,1:array<string,mixed>}>
     */
    public static function toolsWithAModule(): array
    {
        return [
            'list_records' => ['list_records', []],
            'get_record' => ['get_record', ['id' => 1]],
            'create_record' => ['create_record', ['data' => ['name' => 'x']]],
            'update_record' => ['update_record', ['id' => 1, 'data' => ['name' => 'x']]],
            'delete_record' => ['delete_record', ['id' => 1]],
        ];
    }

    /**
     * @param  array<string,mixed>  $arguments
     */
    #[DataProvider('toolsWithAModule')]
    public function test_every_tool_refuses_a_module_that_is_not_in_the_api(string $tool, array $arguments): void
    {
        $post = Post::query()->create(['name' => 'Hello']);
        $token = $this->token(['posts.view', 'posts.create', 'posts.update', 'posts.delete'], ['posts:read', 'posts:write']);

        foreach (['nope', 'users', '', '../posts'] as $module) {
            $this->mcp($token, 'tools/call', ['name' => $tool, 'arguments' => ['module' => $module] + $arguments])
                ->assertJsonPath('result.isError', true)
                ->assertJsonPath('result.content.0.text', 'No module "'.$module.'". See list_modules.');
        }

        $this->assertSame('Hello', $post->fresh()->name);
    }

    #[DataProvider('toolsWithAModule')]
    public function test_every_tool_works_on_a_module_of_the_api(string $tool, array $arguments): void
    {
        $post = Post::query()->create(['name' => 'Hello']);
        $token = $this->token(['posts.view', 'posts.create', 'posts.update', 'posts.delete'], ['posts:read', 'posts:write']);
        $arguments = array_map(static fn ($value) => $value === 1 ? $post->id : $value, $arguments);

        $this->mcp($token, 'tools/call', ['name' => $tool, 'arguments' => ['module' => 'posts'] + $arguments])
            ->assertJsonPath('result.isError', false);
    }

    public function test_a_token_that_may_use_nothing_is_told_so(): void
    {
        $token = $this->token([], ['posts:read']);

        $this->mcp($token, 'tools/call', ['name' => 'list_modules', 'arguments' => []])
            ->assertJsonPath('result.isError', true)
            ->assertSee('This token may not use any module.');
    }

    public function test_list_modules_needs_both_the_ability_and_the_permission(): void
    {
        // The owner may write, the token only read: only "view" is offered.
        $token = $this->token(['posts.view', 'posts.create', 'posts.delete'], ['posts:read']);

        $this->assertSame([['module' => 'posts', 'label' => 'Posts', 'allowed' => ['view']]], $this->callTool($token, 'list_modules'));
    }

    public function test_describe_module_refuses_what_the_token_may_not_read(): void
    {
        $token = $this->token(['posts.view']);

        foreach (['users', 'nope'] as $module) {
            $this->mcp($token, 'tools/call', ['name' => 'describe_module', 'arguments' => ['module' => $module]])
                ->assertJsonPath('result.isError', true)
                ->assertJsonPath('result.content.0.text', "No module \"{$module}\" for this token. See list_modules.");
        }
    }

    public function test_describe_module_needs_the_read_ability(): void
    {
        $this->mcp($this->token(['posts.view', 'posts.create'], ['posts:write']), 'tools/call', ['name' => 'describe_module', 'arguments' => ['module' => 'posts']])
            ->assertJsonPath('result.isError', true)
            ->assertJsonPath('result.content.0.text', 'No module "posts" for this token. See list_modules.');
    }

    public function test_describe_module_names_the_module_and_what_the_token_may_do(): void
    {
        $module = $this->callTool($this->token(['posts.view', 'posts.update'], ['posts:read', 'posts:write']), 'describe_module', ['module' => 'posts']);

        $this->assertSame('posts', $module['module']);
        $this->assertSame('Posts', $module['label']);
        $this->assertSame(['view', 'update'], $module['allowed']);
    }

    public function test_list_records_passes_search_filter_sort_and_paging_on(): void
    {
        foreach (['Apple', 'Banana', 'Cherry', 'Lemon'] as $i => $name) {
            Post::query()->create(['name' => $name, 'active' => $name !== 'Lemon', 'sort' => $i]);
        }
        $token = $this->token(['posts.view']);

        $found = $this->callTool($token, 'list_records', [
            'module' => 'posts', 'search' => 'e', 'filter' => ['active' => 1], 'sort' => 'name', 'direction' => 'desc', 'page' => 1, 'per_page' => 2,
        ]);

        $this->assertSame(['Cherry', 'Apple'], array_column($found['data'], 'name'));
        $this->assertSame(['current_page' => 1, 'last_page' => 1, 'per_page' => 2, 'total' => 2], $found['meta']);

        $this->assertSame(4, $this->callTool($token, 'list_records', ['module' => 'posts', 'search' => '', 'filter' => [], 'sort' => null])['meta']['total']);
    }

    public function test_a_missing_record_comes_back_as_an_error(): void
    {
        $token = $this->token(['posts.view']);

        $this->mcp($token, 'tools/call', ['name' => 'get_record', 'arguments' => ['module' => 'posts', 'id' => 999]])
            ->assertJsonPath('result.isError', true)
            ->assertSee('HTTP 404.');
    }

    public function test_the_site_argument_picks_another_site(): void
    {
        $token = $this->token(['posts.view']);

        $this->mcp($token, 'tools/call', ['name' => 'list_records', 'arguments' => ['module' => 'posts', 'site' => 'nope']])
            ->assertJsonPath('result.isError', true)
            ->assertSee('HTTP 404. No site [nope].');

        $this->assertSame(0, $this->callTool($token, 'list_records', ['module' => 'posts', 'site' => 'main'])['meta']['total']);
    }

    public function test_tools_of_other_packages_join_the_server(): void
    {
        config()->set('atrium.mcp.tools', [ListModules::class, GetRecord::class]);

        $names = array_column($this->mcp($this->token(['posts.view']), 'tools/list')->json('result.tools'), 'name');

        $this->assertSame(
            ['list_modules', 'describe_module', 'list_records', 'get_record', 'create_record', 'update_record', 'delete_record'],
            $names,
            'Listed once, in the order given.',
        );
    }

    /**
     * @param  array<int,string>  $permissions
     * @param  array<int,string>  $abilities
     */
    private function token(array $permissions, array $abilities = ['posts:read']): string
    {
        $user = AdminUser::query()->create([
            'name' => 'Editor',
            'email' => 'editor@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);
        $user->roles()->attach(AdminRole::query()->create(['name' => 'Role', 'permissions' => $permissions]));

        return $user->createToken('assistant', $abilities)->plainTextToken;
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    private function rpc(string $method, array $params = []): array
    {
        return ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params];
    }

    /**
     * @param  array<string,mixed>  $params
     */
    private function mcp(string $token, string $method, array $params = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->postJson('/mcp', $this->rpc($method, $params))->assertOk();
    }

    /**
     * A tool call that must succeed: its JSON result.
     *
     * @param  array<string,mixed>  $arguments
     * @return array<mixed>
     */
    private function callTool(string $token, string $tool, array $arguments = []): array
    {
        $result = $this->mcp($token, 'tools/call', ['name' => $tool, 'arguments' => $arguments])->json('result');

        $this->assertFalse($result['isError'], (string) ($result['content'][0]['text'] ?? ''));

        return json_decode($result['content'][0]['text'], true);
    }
}
