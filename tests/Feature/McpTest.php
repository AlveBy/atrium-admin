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
use Atrium\Admin\Tests\Concerns\PinsExtensionSurface;
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
    use PinsExtensionSurface;

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
            ['list_modules', 'describe_module', 'list_records', 'get_record', 'create_record', 'update_record', 'delete_record', 'list_actions', 'run_record_action', 'run_bulk_action'],
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
            'list_actions' => ['list_actions', []],
            'run_bulk_action' => ['run_bulk_action', ['ids' => [1], 'action' => 'delete']],
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

    public function test_list_records_takes_the_search_literally_and_ignores_case(): void
    {
        foreach (['50% off', '500 rooms', 'a_c', 'abc', 'Big Sale!', 'Big Sales', 'Apple'] as $name) {
            Post::query()->create(['name' => $name]);
        }
        $token = $this->token(['posts.view']);

        $this->assertSame(['50% off'], array_column($this->callTool($token, 'list_records', ['module' => 'posts', 'search' => '50%'])['data'], 'name'));
        $this->assertSame(['Apple'], array_column($this->callTool($token, 'list_records', ['module' => 'posts', 'search' => 'APPLE'])['data'], 'name'));
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
            ['list_modules', 'describe_module', 'list_records', 'get_record', 'create_record', 'update_record', 'delete_record', 'list_actions', 'run_record_action', 'run_bulk_action'],
            $names,
            'Listed once, in the order given.',
        );
    }

    public function test_every_tool_describes_its_arguments_exactly(): void
    {
        $module = ['description' => 'Module key from list_modules, e.g. "articles".', 'type' => 'string'];
        $site = ['description' => 'Site code, when the record belongs to another site than this address.', 'type' => 'string'];
        $id = ['description' => 'Record id.', 'type' => 'integer'];
        $action = ['description' => 'Action name from list_actions, e.g. "delete".', 'type' => 'string'];

        $expected = [
            'list_modules' => [],
            'describe_module' => ['properties' => ['module' => $module], 'required' => ['module']],
            'list_records' => ['properties' => [
                'module' => $module,
                'site' => $site,
                'search' => ['description' => 'Text to look for in the searchable columns.', 'type' => 'string'],
                'filter' => ['description' => 'Exact column values, e.g. {"active": 1, "section_id": 4}.', 'type' => 'object'],
                'sort' => ['description' => 'Column to sort by. Default: id.', 'type' => 'string'],
                'direction' => ['description' => 'Default: asc.', 'enum' => ['asc', 'desc'], 'type' => 'string'],
                'page' => ['description' => 'Page number, from 1.', 'type' => 'integer'],
                'per_page' => ['description' => 'Records per page, 1 to 100. Default: 20.', 'type' => 'integer'],
            ], 'required' => ['module']],
            'get_record' => ['properties' => ['module' => $module, 'site' => $site, 'id' => $id], 'required' => ['module', 'id']],
            'create_record' => ['properties' => [
                'module' => $module,
                'site' => $site,
                'data' => ['description' => 'Field values keyed by field name.', 'type' => 'object'],
            ], 'required' => ['module', 'data']],
            'update_record' => ['properties' => [
                'module' => $module,
                'site' => $site,
                'id' => $id,
                'data' => ['description' => 'The fields to change, keyed by field name.', 'type' => 'object'],
            ], 'required' => ['module', 'id', 'data']],
            'delete_record' => ['properties' => ['module' => $module, 'site' => $site, 'id' => $id], 'required' => ['module', 'id']],
            'list_actions' => ['properties' => ['module' => $module, 'site' => $site], 'required' => ['module']],
            'run_record_action' => ['properties' => ['module' => $module, 'site' => $site, 'id' => $id, 'action' => $action], 'required' => ['module', 'id', 'action']],
            'run_bulk_action' => ['properties' => [
                'module' => $module,
                'site' => $site,
                'ids' => ['description' => 'Ids of the records.', 'type' => 'array', 'items' => ['type' => 'integer']],
                'action' => $action,
            ], 'required' => ['module', 'ids', 'action']],
        ];

        $tools = $this->mcp($this->token(['posts.view']), 'tools/list')->json('result.tools');
        $actual = [];
        foreach ($tools as $tool) {
            $schema = $tool['inputSchema'];
            unset($schema['type']);
            $actual[$tool['name']] = $schema === ['properties' => []] ? [] : $schema;
        }

        $this->assertSame(array_keys($expected), array_keys($actual));
        foreach ($expected as $name => $schema) {
            $this->assertEquals($schema, $actual[$name], $name);
            $this->assertSame(array_keys($schema['properties'] ?? []), array_keys($actual[$name]['properties'] ?? []), "{$name}: order");
        }
    }

    public function test_a_module_that_is_not_a_string_is_still_refused_by_name(): void
    {
        $token = $this->token(['posts.view'], ['posts:read']);

        $this->mcp($token, 'tools/call', ['name' => 'list_records', 'arguments' => ['module' => 123]])
            ->assertJsonPath('result.isError', true)
            ->assertJsonPath('result.content.0.text', 'No module "123". See list_modules.');
        $this->mcp($token, 'tools/call', ['name' => 'describe_module', 'arguments' => ['module' => 123]])
            ->assertJsonPath('result.isError', true)
            ->assertJsonPath('result.content.0.text', 'No module "123" for this token. See list_modules.');
    }

    public function test_an_empty_or_missing_site_adds_nothing_to_the_query(): void
    {
        $token = $this->token(['posts.view']);

        foreach ([['site' => ''], ['site' => null], []] as $extra) {
            $this->assertSame(0, $this->callTool($token, 'list_records', ['module' => 'posts'] + $extra)['meta']['total']);
        }
    }

    public function test_unknown_modules_stay_closed_even_for_a_token_that_may_do_anything(): void
    {
        $owner = AdminUser::query()->forceCreate([
            'name' => 'Root', 'email' => 'root@example.com', 'password' => 'secret-password', 'is_active' => true, 'is_superadmin' => true,
        ]);
        $token = $owner->createToken('all', ['*'])->plainTextToken;

        $this->mcp($token, 'tools/call', ['name' => 'describe_module', 'arguments' => ['module' => 'nope']])
            ->assertJsonPath('result.isError', true)
            ->assertJsonPath('result.content.0.text', 'No module "nope" for this token. See list_modules.');
        $this->assertSame('Posts', $this->callTool($token, 'describe_module', ['module' => 'posts'])['label']);
    }

    public function test_get_update_and_delete_take_the_id_as_a_number(): void
    {
        $post = Post::query()->create(['name' => 'Hello']);
        $token = $this->token(['posts.view', 'posts.update', 'posts.delete'], ['posts:read', 'posts:write']);

        // Whatever arrives is made an integer first, so it can never add anything to the address.
        $id = $post->id + 0.5;

        $this->assertSame($post->id, $this->callTool($token, 'get_record', ['module' => 'posts', 'id' => $id])['data']['id']);
        $this->assertSame('Hi', $this->callTool($token, 'update_record', ['module' => 'posts', 'id' => $id, 'data' => ['name' => 'Hi']])['data']['name']);
        $this->mcp($token, 'tools/call', ['name' => 'delete_record', 'arguments' => ['module' => 'posts', 'id' => $id]])
            ->assertJsonPath('result.isError', false);
        $this->assertSame(0, Post::query()->count());
    }

    public function test_list_records_sends_only_what_was_asked_for(): void
    {
        foreach (['B', 'A'] as $i => $name) {
            Post::query()->create(['name' => $name, 'sort' => $i]);
        }
        $token = $this->token(['posts.view']);

        // Empty values must not reach the API as filters: "sort" of "" would be an invalid column.
        $found = $this->callTool($token, 'list_records', ['module' => 'posts', 'sort' => '', 'direction' => '', 'page' => null, 'per_page' => null, 'search' => null, 'filter' => []]);
        $this->assertSame(['B', 'A'], array_column($found['data'], 'name'));

        $this->assertSame(['A', 'B'], array_column($this->callTool($token, 'list_records', ['module' => 'posts', 'sort' => 'name'])['data'], 'name'));
        $this->assertSame(['B'], array_column($this->callTool($token, 'list_records', ['module' => 'posts', 'page' => 1, 'per_page' => 1])['data'], 'name'));
        $this->assertSame(['A'], array_column($this->callTool($token, 'list_records', ['module' => 'posts', 'page' => 2, 'per_page' => 1])['data'], 'name'));
        $this->assertSame(['B', 'A'], array_column($this->callTool($token, 'list_records', ['module' => 'posts', 'sort' => 'sort', 'direction' => 'asc'])['data'], 'name'));
    }

    public function test_a_config_without_extra_tools_changes_nothing(): void
    {
        $token = $this->token(['posts.view']);

        foreach ([null, []] as $value) {
            config()->set('atrium.mcp.tools', $value);

            $names = array_column($this->mcp($token, 'tools/list')->json('result.tools'), 'name');

            $this->assertSame(
                ['list_modules', 'describe_module', 'list_records', 'get_record', 'create_record', 'update_record', 'delete_record', 'list_actions', 'run_record_action', 'run_bulk_action'],
                $names,
            );
        }
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
