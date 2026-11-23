<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Hooks\Refused;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use ReflectionClass;

/**
 * The "before" hooks say no: `atrium.record.data` keeps a record unsaved,
 * `atrium.record.deleting` keeps it from being deleted, the same in the
 * panel, the API, MCP and the bulk "Delete".
 */
final class RecordRefusalTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Sanctum::class))->getFileName(), 2).'/database/migrations');
    }

    public function test_a_refusal_carries_its_message_and_maybe_a_field(): void
    {
        $this->assertSame('No.', (new Refused('No.'))->getMessage());
        $this->assertNull((new Refused('No.'))->field);
        $this->assertSame('name', (new Refused('No.', 'name'))->field);
    }

    public function test_a_refusal_without_a_field_keeps_the_record_unsaved_and_is_shown_above_the_form(): void
    {
        Hook::filter('atrium.record.data', static fn (array $data): array => throw new Refused('The shop is closed.'));

        $this->actingAs($this->admin(), 'admin')
            ->from('/admin/posts/create')
            ->post('/admin/posts', ['name' => 'First', 'active' => true, 'sort' => 10])
            ->assertRedirect('/admin/posts/create')
            ->assertSessionHasErrors(['_record' => 'The shop is closed.'])
            ->assertSessionHas('error', 'The shop is closed.');

        $this->assertSame(0, Post::query()->count());
    }

    public function test_a_refusal_of_a_field_is_shown_under_it_and_nowhere_else(): void
    {
        $post = Post::query()->create(['name' => 'Kept', 'active' => true, 'sort' => 10]);
        Hook::filter('atrium.record.data', static fn (array $data, Post $post, string $context): array => $data['name'] === 'Taken' && $context === 'edit'
            ? throw new Refused('This name is taken.', 'name')
            : $data);

        $this->actingAs($this->admin(), 'admin')
            ->from("/admin/posts/{$post->id}/edit")
            ->put("/admin/posts/{$post->id}", ['name' => 'Taken', 'active' => true, 'sort' => 10])
            ->assertRedirect("/admin/posts/{$post->id}/edit")
            ->assertSessionHasErrors(['name' => 'This name is taken.'])
            ->assertSessionMissing('error');
        $this->assertSame('Kept', $post->fresh()->name);

        $this->put("/admin/posts/{$post->id}", ['name' => 'Free', 'active' => true, 'sort' => 10])->assertSessionHasNoErrors();
        $this->assertSame('Free', $post->fresh()->name);
    }

    public function test_the_api_answers_a_refused_save_with_422_and_the_message(): void
    {
        Hook::filter('atrium.record.data', static fn (array $data): array => throw new Refused('The shop is closed.'));

        $this->withToken($this->token())->postJson('/api/v1/posts', ['name' => 'First', 'active' => true, 'sort' => 10])
            ->assertUnprocessable()
            ->assertExactJson(['message' => 'The shop is closed.', 'errors' => ['_record' => ['The shop is closed.']]]);

        $this->assertSame(0, Post::query()->count());
    }

    public function test_a_refused_delete_keeps_the_record_and_what_the_listener_wrote(): void
    {
        $post = Post::query()->create(['name' => 'Kept', 'active' => true, 'sort' => 10]);
        $deleted = [];
        Hook::on('atrium.record.deleted', function (Post $post) use (&$deleted): void {
            $deleted[] = $post->name;
        });
        Hook::on('atrium.record.deleting', function (Post $post): void {
            Post::query()->create(['name' => 'Archive of '.$post->name, 'active' => false, 'sort' => 1]);

            throw new Refused('Close its orders first.');
        });

        $this->actingAs($this->admin(), 'admin')
            ->from('/admin/posts')
            ->delete("/admin/posts/{$post->id}")
            ->assertRedirect('/admin/posts')
            ->assertSessionHas('error', 'Close its orders first.');

        $this->assertSame(['Kept'], Post::query()->pluck('name')->all());
        $this->assertSame([], $deleted);
    }

    public function test_a_delete_nobody_refuses_goes_ahead_after_the_listener_saw_the_record(): void
    {
        $post = Post::query()->create(['name' => 'Gone', 'active' => true, 'sort' => 10]);
        $seen = [];
        Hook::on('atrium.record.deleting', function (Post $post) use (&$seen): void {
            $seen[] = $post->name.' '.Post::query()->whereKey($post->getKey())->count();
        });

        $this->actingAs($this->admin(), 'admin')
            ->delete("/admin/posts/{$post->id}")
            ->assertRedirect('/admin/posts')
            ->assertSessionHas('success');

        $this->assertSame(['Gone 1'], $seen);
        $this->assertSame(0, Post::query()->count());
    }

    public function test_the_api_answers_a_refused_delete_with_409_and_the_message(): void
    {
        $post = Post::query()->create(['name' => 'Kept', 'active' => true, 'sort' => 10]);
        Hook::on('atrium.record.deleting', static fn () => throw new Refused('Close its orders first.'));

        $this->withToken($this->token())->deleteJson("/api/v1/posts/{$post->id}")
            ->assertStatus(409)
            ->assertExactJson(['message' => 'Close its orders first.']);

        $this->assertSame(1, Post::query()->count());
    }

    public function test_the_bulk_delete_keeps_the_refused_records_and_gives_each_reason_once(): void
    {
        [$one, $two, $three] = array_map(fn (string $name) => Post::query()->create(['name' => $name, 'active' => true, 'sort' => 10]), ['One', 'Two', 'Three']);
        Hook::on('atrium.record.deleting', static fn (Post $post) => $post->name === 'One' ? null : throw new Refused("{$post->name} has orders."));
        Hook::on('atrium.record.deleting', static fn (Post $post) => $post->name === 'Three' ? throw new Refused('Three has orders.') : null);
        $this->actingAs($this->admin(), 'admin');

        $this->from('/admin/posts')->post('/admin/posts/bulk/delete', ['ids' => [$one->id, $two->id, $three->id]])
            ->assertRedirect('/admin/posts')
            ->assertSessionHas('success', '1 record deleted. 2 records could not be deleted. Two has orders. Three has orders.');
        $this->assertSame(['Two', 'Three'], Post::query()->orderBy('id')->pluck('name')->all());

        $this->from('/admin/posts')->post('/admin/posts/bulk/delete', ['ids' => [$two->id, $three->id]])
            ->assertSessionHas('error', '2 records could not be deleted. Two has orders. Three has orders.');
        $this->assertSame(2, Post::query()->count());
    }

    public function test_the_bulk_delete_repeats_no_reason(): void
    {
        $ids = array_map(fn (string $name) => Post::query()->create(['name' => $name, 'active' => true, 'sort' => 10])->id, ['One', 'Two']);
        Hook::on('atrium.record.deleting', static fn () => throw new Refused('The shop is closed.'));

        $this->actingAs($this->admin(), 'admin')
            ->from('/admin/posts')->post('/admin/posts/bulk/delete', ['ids' => $ids])
            ->assertSessionHas('error', '2 records could not be deleted. The shop is closed.');
    }

    public function test_an_assistant_reads_why_a_save_or_a_delete_was_refused(): void
    {
        $post = Post::query()->create(['name' => 'Kept', 'active' => true, 'sort' => 10]);
        Hook::filter('atrium.record.data', static fn (array $data): array => throw new Refused('The shop is closed.'));
        Hook::on('atrium.record.deleting', static fn () => throw new Refused('Close its orders first.'));
        $token = $this->token();

        $this->mcp($token, 'create_record', ['module' => 'posts', 'data' => ['name' => 'New', 'active' => true, 'sort' => 1]])
            ->assertJsonPath('result.isError', true)
            ->assertJsonPath('result.content.0.text', "HTTP 422. The shop is closed.\n- _record: The shop is closed.");
        $this->mcp($token, 'delete_record', ['module' => 'posts', 'id' => $post->id])
            ->assertJsonPath('result.isError', true)
            ->assertJsonPath('result.content.0.text', 'HTTP 409. Close its orders first.');

        $this->assertSame(['Kept'], Post::query()->pluck('name')->all());
    }

    private function token(): string
    {
        $owner = AdminUser::query()->create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'secret-password', 'is_active' => true]);
        $owner->roles()->attach(AdminRole::query()->create(['name' => 'Editor', 'permissions' => ['posts.view', 'posts.create', 'posts.update', 'posts.delete']]));

        return $owner->createToken('assistant', ['posts:read', 'posts:write'])->plainTextToken;
    }

    /**
     * @param  array<string,mixed>  $arguments
     */
    private function mcp(string $token, string $tool, array $arguments): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => $arguments]])
            ->assertOk();
    }
}
