<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Actions\RunBulkAction;
use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Tests\Concerns\PinsExtensionSurface;
use Atrium\Admin\Tests\Fixtures\Actions\Inspect;
use Atrium\Admin\Tests\Fixtures\Dispatch;
use Atrium\Admin\Tests\Fixtures\Note;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use ReflectionClass;

/**
 * The buttons of a module through the API and MCP: listed and run by token,
 * with the panel's checks and the token's abilities on top.
 */
final class ModuleActionsApiTest extends TestCase
{
    use PinsExtensionSurface;

    private const ALL = ['dispatches.view', 'dispatches.update', 'dispatches.publish', 'dispatches.delete'];

    private const PUBLISH = ['type' => 'record', 'name' => 'publish', 'label' => 'Publish', 'confirm' => 'Publish it?', 'permission' => 'dispatches.publish'];

    private const INSPECT = ['type' => 'record', 'name' => 'inspect', 'label' => 'Inspect', 'confirm' => null, 'permission' => 'dispatches.view'];

    private const DELETE = ['type' => 'bulk', 'name' => 'delete', 'label' => 'Delete', 'confirm' => 'Delete the selected records? This cannot be undone.', 'permission' => 'dispatches.delete'];

    private const FLAG = ['type' => 'bulk', 'name' => 'flag', 'label' => 'Hide', 'confirm' => null, 'permission' => 'dispatches.update'];

    private const QUEUED = ['type' => 'bulk', 'name' => 'queued', 'label' => 'Hide later', 'confirm' => null, 'permission' => 'dispatches.update'];

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Post::class, Dispatch::class, Note::class]);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Sanctum::class))->getFileName(), 2).'/database/migrations');
    }

    public function test_the_list_has_the_server_actions_the_token_may_run(): void
    {
        $write = $this->token(self::ALL, ['dispatches:read', 'dispatches:write']);
        $this->withToken($write)->getJson('/api/v1/dispatches/actions')->assertOk()
            ->assertExactJson(['data' => ['record' => [self::PUBLISH, self::INSPECT], 'bulk' => [self::DELETE, self::FLAG, self::QUEUED]]]);

        $read = $this->token(self::ALL, ['dispatches:read']);
        $this->withToken($read)->getJson('/api/v1/dispatches/actions')->assertOk()
            ->assertExactJson(['data' => ['record' => [self::INSPECT], 'bulk' => []]]);

        $owner = $this->token(['dispatches.view', 'dispatches.update'], ['dispatches:read', 'dispatches:write']);
        $this->withToken($owner)->getJson('/api/v1/dispatches/actions')->assertOk()
            ->assertExactJson(['data' => ['record' => [self::INSPECT], 'bulk' => [self::FLAG, self::QUEUED]]]);
    }

    public function test_the_list_needs_the_module_and_a_read_token(): void
    {
        $this->withToken($this->token(self::ALL, ['dispatches:write']))->getJson('/api/v1/dispatches/actions')
            ->assertForbidden()->assertJsonPath('message', 'The token lacks the dispatches:read ability.');
        $this->withToken($this->token(['dispatches.update'], ['dispatches:read']))->getJson('/api/v1/dispatches/actions')
            ->assertForbidden()->assertJsonPath('message', 'The token owner lacks the dispatches.view permission.');
        $this->withToken($this->token(self::ALL, ['dispatches:read']))->getJson('/api/v1/nopes/actions')->assertNotFound();
    }

    public function test_an_action_that_only_looks_needs_a_view_permission_not_one_ending_like_it(): void
    {
        $dispatch = Dispatch::query()->create(['name' => 'One']);
        Hook::filter('atrium.module.actions', fn (array $actions): array => [...$actions, Inspect::make('review')->label('Review')->permission('review')]);
        $read = $this->token([...self::ALL, 'dispatches.review'], ['dispatches:read']);

        $this->withToken($read)->getJson('/api/v1/dispatches/actions')->assertJsonPath('data.record', [self::INSPECT]);
        $this->withToken($read)->postJson("/api/v1/dispatches/{$dispatch->id}/actions/review")
            ->assertForbidden()->assertJsonPath('message', 'The token lacks the dispatches:write ability.');
        $this->withToken($read)->postJson("/api/v1/dispatches/{$dispatch->id}/actions/inspect")
            ->assertOk()->assertExactJson(['message' => 'It is One.', 'redirect' => null]);
    }

    public function test_a_record_action_runs_and_answers_with_its_message(): void
    {
        $draft = Dispatch::query()->create(['name' => 'Draft', 'active' => false]);
        $away = Dispatch::query()->create(['name' => 'Away']);
        $token = $this->token(self::ALL, ['dispatches:read', 'dispatches:write']);

        $this->withToken($token)->postJson("/api/v1/dispatches/{$draft->id}/actions/publish")
            ->assertOk()->assertExactJson(['message' => 'Published Draft.', 'redirect' => null]);
        $this->assertTrue($draft->fresh()->active);

        $this->withToken($token)->postJson("/api/v1/dispatches/{$draft->id}/actions/publish")
            ->assertUnprocessable()->assertExactJson(['message' => 'Already published.', 'redirect' => null]);
        $this->withToken($token)->postJson("/api/v1/dispatches/{$away->id}/actions/publish")
            ->assertOk()->assertExactJson(['message' => 'Done.', 'redirect' => 'https://example.org/away']);
    }

    public function test_a_record_action_is_refused_like_in_the_panel(): void
    {
        $dispatch = Dispatch::query()->create(['name' => 'Draft', 'active' => false]);
        $write = $this->token(self::ALL, ['dispatches:read', 'dispatches:write']);

        $this->withToken($write)->postJson("/api/v1/dispatches/{$dispatch->id}/actions/nope")->assertNotFound();
        $this->withToken($write)->postJson("/api/v1/dispatches/{$dispatch->id}/actions/site")->assertNotFound();
        $this->withToken($write)->postJson("/api/v1/dispatches/{$dispatch->id}/actions/copy")->assertNotFound();
        $this->withToken($write)->postJson('/api/v1/dispatches/'.($dispatch->id + 1).'/actions/publish')->assertNotFound();
        $this->withToken($this->token(self::ALL, ['dispatches:read']))->postJson("/api/v1/dispatches/{$dispatch->id}/actions/publish")
            ->assertForbidden()->assertJsonPath('message', 'The token lacks the dispatches:write ability.');
        $this->withToken($this->token(['dispatches.view', 'dispatches.update'], ['dispatches:read', 'dispatches:write']))->postJson("/api/v1/dispatches/{$dispatch->id}/actions/publish")
            ->assertForbidden()->assertJsonPath('message', 'This action is not allowed.');
        $this->withToken($this->token(['dispatches.publish'], ['dispatches:read', 'dispatches:write']))->postJson("/api/v1/dispatches/{$dispatch->id}/actions/publish")
            ->assertForbidden()->assertJsonPath('message', 'The token owner lacks the dispatches.view permission.');
        $this->withToken($this->token(self::ALL, ['dispatches:write']))->postJson("/api/v1/dispatches/{$dispatch->id}/actions/publish")
            ->assertForbidden()->assertJsonPath('message', 'The token lacks the dispatches:read ability.');

        $this->assertFalse($dispatch->fresh()->active);
    }

    public function test_a_bulk_action_runs_on_the_records_named(): void
    {
        [$one, $two] = array_map(fn (string $name) => Dispatch::query()->create(['name' => $name]), ['One', 'Two']);
        $token = $this->token(self::ALL, ['dispatches:read', 'dispatches:write']);

        $this->withToken($token)->postJson('/api/v1/dispatches/bulk/flag', ['ids' => [$two->id, $one->id + 10]])
            ->assertOk()->assertExactJson(['message' => 'Hidden: Two.', 'redirect' => null]);
        $this->withToken($token)->postJson('/api/v1/dispatches/bulk/flag', ['ids' => [$one->id + 10]])
            ->assertUnprocessable()->assertExactJson(['message' => 'None of the selected records was found.', 'redirect' => null]);
        $this->withToken($token)->postJson('/api/v1/dispatches/bulk/flag', ['ids' => 'all'])
            ->assertUnprocessable()->assertJsonValidationErrors('ids');
        $this->withToken($token)->postJson('/api/v1/dispatches/bulk/delete', ['ids' => [$one->id]])
            ->assertOk()->assertJsonPath('message', '1 record deleted.');

        $this->assertSame(['Two'], Dispatch::query()->pluck('name')->all());
        $this->assertFalse($two->fresh()->active);
    }

    public function test_a_bulk_action_is_refused_like_in_the_panel(): void
    {
        $dispatch = Dispatch::query()->create(['name' => 'One']);

        $this->withToken($this->token(self::ALL, ['dispatches:read']))->postJson('/api/v1/dispatches/bulk/delete', ['ids' => [$dispatch->id]])
            ->assertForbidden()->assertJsonPath('message', 'The token lacks the dispatches:write ability.');
        $this->withToken($this->token(['dispatches.view', 'dispatches.update'], ['dispatches:read', 'dispatches:write']))->postJson('/api/v1/dispatches/bulk/delete', ['ids' => [$dispatch->id]])
            ->assertForbidden()->assertJsonPath('message', 'This action is not allowed.');
        $this->withToken($this->token(['dispatches.delete'], ['dispatches:read', 'dispatches:write']))->postJson('/api/v1/dispatches/bulk/delete', ['ids' => [$dispatch->id]])
            ->assertForbidden()->assertJsonPath('message', 'The token owner lacks the dispatches.view permission.');
        $this->withToken($this->token(self::ALL, ['dispatches:write']))->postJson('/api/v1/dispatches/bulk/delete', ['ids' => [$dispatch->id]])
            ->assertForbidden()->assertJsonPath('message', 'The token lacks the dispatches:read ability.');
        $this->withToken($this->token(self::ALL, ['dispatches:read', 'dispatches:write']))->postJson('/api/v1/dispatches/bulk/publish', ['ids' => [$dispatch->id]])
            ->assertNotFound();

        $this->assertSame(1, Dispatch::query()->count());
    }

    public function test_a_queued_action_is_started_through_the_api_too(): void
    {
        Queue::fake();
        $dispatch = Dispatch::query()->create(['name' => 'One']);

        $this->withToken($this->token(self::ALL, ['dispatches:read', 'dispatches:write']))->postJson('/api/v1/dispatches/bulk/queued', ['ids' => [$dispatch->id]])
            ->assertOk()->assertJsonPath('message', 'Started for 1 record. It runs in the background.');

        Queue::assertPushed(RunBulkAction::class, fn (RunBulkAction $job): bool => $job->ids === [$dispatch->id]);
    }

    public function test_the_site_parameter_decides_which_records_a_bulk_action_reaches(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        $main = Site::query()->where('is_default', true)->sole();
        $second = Site::query()->create(['code' => 'de', 'name' => 'Deutschland', 'domains' => ['example.de'], 'languages' => ['de']]);
        $mine = Note::query()->create(['name' => 'Mine', 'code' => 'a', 'site_id' => $main->id]);
        $theirs = Note::query()->create(['name' => 'Theirs', 'code' => 'b', 'site_id' => $second->id]);
        $token = $this->token(['notes.view', 'notes.delete'], ['notes:read', 'notes:write']);

        $this->withToken($token)->postJson('/api/v1/notes/bulk/delete?site=main', ['ids' => [$theirs->id]])->assertUnprocessable();
        $this->withToken($token)->postJson('/api/v1/notes/bulk/delete?site=de', ['ids' => [$mine->id, $theirs->id]])
            ->assertOk()->assertJsonPath('message', '1 record deleted.');

        $this->assertSame(['Mine'], Note::query()->withoutGlobalScopes()->pluck('name')->all());
    }

    public function test_an_assistant_lists_and_runs_actions_over_mcp(): void
    {
        [$one, $two] = array_map(fn (string $name) => Dispatch::query()->create(['name' => $name, 'active' => false]), ['One', 'Two']);
        $token = $this->token(self::ALL, ['dispatches:read', 'dispatches:write']);

        $this->assertSame(['record' => [self::PUBLISH, self::INSPECT], 'bulk' => [self::DELETE, self::FLAG, self::QUEUED]], $this->callTool($token, 'list_actions', ['module' => 'dispatches'])['data']);
        $this->assertSame(['message' => 'Published One.', 'redirect' => null], $this->callTool($token, 'run_record_action', ['module' => 'dispatches', 'id' => $one->id + 0.5, 'action' => 'publish']));
        $this->mcp($token, 'tools/call', ['name' => 'run_record_action', 'arguments' => ['module' => 'dispatches', 'id' => $one->id, 'action' => 'publish']])
            ->assertJsonPath('result.isError', true)
            ->assertJsonPath('result.content.0.text', 'HTTP 422. Already published.');
        $this->assertSame(['message' => 'Hidden: One, Two.', 'redirect' => null], $this->callTool($token, 'run_bulk_action', ['module' => 'dispatches', 'ids' => [$one->id, $two->id], 'action' => 'flag']));
        $this->assertSame(['message' => 'Hidden: Two.', 'redirect' => null], $this->callTool($token, 'run_bulk_action', ['module' => 'dispatches', 'ids' => $two->id, 'action' => 'flag']), 'One id is a list of one.');
        $this->mcp($token, 'tools/call', ['name' => 'run_bulk_action', 'arguments' => ['module' => 'dispatches', 'action' => 'flag']])
            ->assertJsonPath('result.isError', true)
            ->assertJsonPath('result.content.0.text', "HTTP 422. The ids field is required.\n- ids: The ids field is required.");
    }

    public function test_mcp_takes_the_site_and_refuses_unknown_modules(): void
    {
        $token = $this->token(self::ALL, ['dispatches:read', 'dispatches:write']);

        foreach (['list_actions' => [], 'run_record_action' => ['id' => 1, 'action' => 'publish'], 'run_bulk_action' => ['ids' => [1], 'action' => 'flag']] as $tool => $arguments) {
            $this->mcp($token, 'tools/call', ['name' => $tool, 'arguments' => ['module' => 'nope'] + $arguments])
                ->assertJsonPath('result.isError', true)
                ->assertJsonPath('result.content.0.text', 'No module "nope". See list_modules.');
            $this->mcp($token, 'tools/call', ['name' => $tool, 'arguments' => ['module' => 'dispatches', 'site' => 'nope'] + $arguments])
                ->assertJsonPath('result.isError', true)
                ->assertJsonPath('result.content.0.text', 'HTTP 404. No site [nope].');
        }
    }

    public function test_mcp_refuses_an_action_name_that_could_point_elsewhere(): void
    {
        $dispatch = Dispatch::query()->create(['name' => 'One', 'active' => false]);
        $token = $this->token(self::ALL, ['dispatches:read', 'dispatches:write']);

        foreach (['run_record_action' => ['id' => $dispatch->id], 'run_bulk_action' => ['ids' => [$dispatch->id]]] as $tool => $arguments) {
            foreach (['', '../publish', 'publish/..', 'pub lish', 'a.b'] as $action) {
                $this->mcp($token, 'tools/call', ['name' => $tool, 'arguments' => ['module' => 'dispatches', 'action' => $action] + $arguments])
                    ->assertJsonPath('result.isError', true)
                    ->assertJsonPath('result.content.0.text', 'No action "'.$action.'". See list_actions.');
            }
            $this->mcp($token, 'tools/call', ['name' => $tool, 'arguments' => ['module' => 'dispatches'] + $arguments])
                ->assertJsonPath('result.isError', true)
                ->assertJsonPath('result.content.0.text', 'No action "". See list_actions.');
            $this->mcp($token, 'tools/call', ['name' => $tool, 'arguments' => ['module' => 'dispatches', 'action' => 123] + $arguments])
                ->assertJsonPath('result.isError', true)
                ->assertJsonPath('result.content.0.text', 'HTTP 404. No such action.');
        }

        $this->assertFalse($dispatch->fresh()->active);
        $this->assertSame(1, Dispatch::query()->count());
    }

    /**
     * @param  array<int,string>  $permissions
     * @param  array<int,string>  $abilities
     */
    private function token(array $permissions, array $abilities): string
    {
        $email = 'owner'.AdminUser::query()->count().'@example.com';
        $user = AdminUser::query()->create(['name' => 'Owner', 'email' => $email, 'password' => 'secret-password', 'is_active' => true]);
        $user->roles()->attach(AdminRole::query()->create(['name' => 'Role '.$email, 'permissions' => $permissions]));

        return $user->createToken('agent', $abilities)->plainTextToken;
    }

    /**
     * @param  array<string,mixed>  $params
     */
    private function mcp(string $token, string $method, array $params = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params])->assertOk();
    }

    /**
     * @param  array<string,mixed>  $arguments
     * @return array<mixed>
     */
    private function callTool(string $token, string $tool, array $arguments): array
    {
        $result = $this->mcp($token, 'tools/call', ['name' => $tool, 'arguments' => $arguments])->json('result');

        $this->assertFalse($result['isError'], (string) ($result['content'][0]['text'] ?? ''));

        return json_decode($result['content'][0]['text'], true);
    }
}
