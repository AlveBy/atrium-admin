<?php

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
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
