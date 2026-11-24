<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\History\Revisions;
use Atrium\Admin\Models\Activity;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Revision;
use Atrium\Admin\Tests\Fixtures\PanelAccount;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Untracked;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;
use ReflectionClass;

/**
 * Versions of a record: kept on every save, compared two at a time, and an
 * older one put back from the panel and the API.
 */
final class RevisionsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Post::class, Untracked::class]);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Sanctum::class))->getFileName(), 2).'/database/migrations');
    }

    /**
     * The names each version of the post holds, newest first.
     *
     * @return array<int,mixed>
     */
    private function names(Model $record): array
    {
        return app(Revisions::class)->of($record)->get()->map(static fn (Revision $version): mixed => $version->values['name'])->all();
    }

    private function editor(array $permissions): AdminUser
    {
        $editor = AdminUser::query()->create(['name' => 'Editor', 'email' => 'editor@example.com', 'password' => 'secret-password', 'is_active' => true]);
        $editor->roles()->attach(AdminRole::query()->create(['name' => 'Editors', 'permissions' => $permissions]));

        return $editor;
    }

    /**
     * A post that was there before its history began, changed twice in the
     * form: Apple, then Pear, then Plum.
     */
    private function changedTwice(): Post
    {
        $post = Post::query()->create(['name' => 'Apple', 'active' => true, 'sort' => 1]);
        $this->actingAs($this->admin(), 'admin');

        Carbon::setTestNow('2026-11-20 10:00:00');
        $this->put("/admin/posts/{$post->id}", ['name' => 'Pear', 'active' => true, 'sort' => 1])->assertSessionHasNoErrors();
        Carbon::setTestNow('2026-11-21 11:00:00');
        $this->put("/admin/posts/{$post->id}", ['name' => 'Plum', 'active' => true, 'sort' => 1])->assertSessionHasNoErrors();
        Carbon::setTestNow();

        return $post;
    }

    public function test_a_save_keeps_a_version_and_the_first_one_keeps_the_record_as_it_was_too(): void
    {
        $post = $this->changedTwice();

        $this->assertSame(['Plum', 'Pear', 'Apple'], $this->names($post));

        $versions = app(Revisions::class)->of($post)->get();
        $this->assertSame(['name' => 'Plum', 'active' => true, 'sort' => 1], array_intersect_key($versions[0]->values, ['name' => 1, 'active' => 1, 'sort' => 1]));
        $this->assertSame(Activity::query()->max('id'), $versions[0]->activity_id);
        $this->assertNull($versions[2]->activity_id, 'The record as it was before its history began.');
        $this->assertSame(['posts', $post->id], [$versions[2]->module, $versions[2]->record_id]);

        $this->put("/admin/posts/{$post->id}", ['name' => 'Plum', 'active' => true, 'sort' => 1])->assertSessionHasNoErrors();
        $this->assertSame(['Plum', 'Pear', 'Apple'], $this->names($post), 'A save that changes nothing keeps nothing.');
    }

    public function test_a_new_record_keeps_one_version_and_nothing_before_it(): void
    {
        $this->actingAs($this->admin(), 'admin')->post('/admin/posts', ['name' => 'Apple'])->assertSessionHasNoErrors();

        $version = Revision::query()->sole();
        $this->assertSame('Apple', $version->values['name']);
        $this->assertSame('created', $version->activity->event);
    }

    public function test_only_the_newest_versions_of_a_record_are_kept(): void
    {
        config(['atrium.history.revisions' => 2]);
        $other = Post::query()->create(['name' => 'Other']);
        $this->actingAs($this->admin(), 'admin')->put("/admin/posts/{$other->id}", ['name' => 'Other 2'])->assertSessionHasNoErrors();

        $post = Post::query()->create(['name' => 'Apple']);
        foreach (['Pear', 'Plum', 'Fig'] as $name) {
            $this->put("/admin/posts/{$post->id}", ['name' => $name])->assertSessionHasNoErrors();
        }

        $this->assertSame(['Fig', 'Plum'], $this->names($post));
        $this->assertSame(['Other 2', 'Other'], $this->names($other), 'Another record keeps its own.');
    }

    public function test_versions_are_kept_where_the_log_is_and_the_module_wants_them(): void
    {
        $revisions = app(Revisions::class);
        $plain = (new class extends Model {})::class;

        $this->assertTrue($revisions->enabledFor(Post::class));
        $this->assertTrue($revisions->enabledFor($plain), 'A model that does not say keeps them.');
        $this->assertFalse($revisions->enabledFor(AdminUser::class), 'Putting back a version would get around the checks of its form.');
        $this->assertFalse($revisions->enabledFor(Untracked::class), 'No log, no versions.');

        config(['atrium.history.revisions' => 0]);
        $this->assertFalse($revisions->enabledFor(Post::class));
        $this->actingAs($this->admin(), 'admin')->post('/admin/posts', ['name' => 'Apple'])->assertSessionHasNoErrors();
        $this->assertSame(0, Revision::query()->count());

        config(['atrium.history.revisions' => 1, 'atrium.history.enabled' => false]);
        $this->assertFalse($revisions->enabledFor(Post::class));
    }

    public function test_a_module_that_keeps_no_versions_keeps_none_on_save(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->put("/admin/users/{$admin->id}", ['name' => 'Boss', 'email' => 'admin@example.com', 'is_active' => true])->assertSessionHasNoErrors();

        $this->assertSame('Boss', $admin->fresh()->name);
        $this->assertSame(1, Activity::query()->count());
        $this->assertSame(0, Revision::query()->count());
    }

    public function test_a_record_deleted_for_good_takes_its_versions_along(): void
    {
        $post = $this->changedTwice();
        $other = Post::query()->create(['name' => 'Other']);
        $this->put("/admin/posts/{$other->id}", ['name' => 'Other 2'])->assertSessionHasNoErrors();

        $this->delete("/admin/posts/{$post->id}")->assertSessionHasNoErrors();

        $this->assertSame([], $this->names($post));
        $this->assertSame(['Other 2', 'Other'], $this->names($other));
    }

    public function test_the_history_of_a_record_lists_its_versions_and_who_may_put_them_back(): void
    {
        $post = $this->changedTwice();
        [$plum, $pear, $apple] = app(Revisions::class)->of($post)->pluck('id')->all();
        $restore = static fn (int $version): string => route('admin.posts.revert', ['id' => $post->id, 'revision' => $version]);

        $this->get("/admin/posts/{$post->id}/history")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('versions', [
                ['id' => $plum, 'at' => '2026-11-21T11:00:00+00:00', 'admin' => 'Admin', 'source' => 'admin', 'revertUrl' => null],
                ['id' => $pear, 'at' => '2026-11-20T10:00:00+00:00', 'admin' => 'Admin', 'source' => 'admin', 'revertUrl' => $restore($pear)],
                ['id' => $apple, 'at' => null, 'admin' => null, 'source' => null, 'revertUrl' => $restore($apple)],
            ])
            ->where('comparison', null));

        $editor = $this->editor(['posts.view']);
        $this->actingAs($editor, 'admin')->get("/admin/posts/{$post->id}/history")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('versions.1.revertUrl', null)->where('versions.2.revertUrl', null));

        AdminRole::query()->sole()->update(['permissions' => ['posts.view', 'posts.update']]);
        $this->actingAs($editor->fresh(), 'admin')->get("/admin/posts/{$post->id}/history")->assertInertia(fn (AssertableInertia $page) => $page->where('versions.2.revertUrl', $restore($apple)));

        config(['auth.providers.admin_users.model' => PanelAccount::class]);
        $this->app['auth']->forgetGuards();
        $this->actingAs(PanelAccount::query()->findOrFail(1), 'admin')->get("/admin/posts/{$post->id}/history")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('versions.2.revertUrl', $restore($apple)));
    }

    public function test_a_module_that_keeps_no_versions_lists_none(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->get("/admin/users/{$admin->id}/history")->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('versions', [])->where('comparison', null));
        $this->post("/admin/users/{$admin->id}/history/1")->assertNotFound();
    }

    public function test_two_versions_are_compared_field_by_field(): void
    {
        $post = $this->changedTwice();
        [$plum, $pear, $apple] = app(Revisions::class)->of($post)->pluck('id')->all();
        $url = "/admin/posts/{$post->id}/history";

        $this->get("{$url}?from={$apple}&to={$plum}")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('comparison', [
                'from' => $apple,
                'to' => $plum,
                'changes' => [['field' => 'name', 'label' => 'Name', 'old' => 'Apple', 'new' => 'Plum', 'secret' => false]],
            ]));
        $this->get("{$url}?from={$plum}&to={$pear}")->assertInertia(fn (AssertableInertia $page) => $page
            ->where('comparison.changes.0.old', 'Plum')->where('comparison.changes.0.new', 'Pear'));
        $this->get("{$url}?from={$pear}&to={$pear}")->assertInertia(fn (AssertableInertia $page) => $page->where('comparison.changes', []));
        $this->get("{$url}?from={$pear}")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('comparison', null));
        $this->get("{$url}?to={$pear}")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('comparison', null));

        $other = Post::query()->create(['name' => 'Other']);
        $this->put("/admin/posts/{$other->id}", ['name' => 'Other 2'])->assertSessionHasNoErrors();
        $foreign = app(Revisions::class)->of($other)->value('id');

        $this->from($url)->get("{$url}?from={$foreign}&to=abc")->assertRedirect($url)->assertSessionHasErrors(['from', 'to']);
    }

    public function test_an_older_version_is_put_back_through_the_form_and_logged(): void
    {
        $post = $this->changedTwice();
        $apple = app(Revisions::class)->of($post)->get()->last();

        $this->post("/admin/posts/{$post->id}/history/{$apple->id}")
            ->assertRedirect(route('admin.posts.history', $post->id))
            ->assertSessionHas('success', 'The version is restored.');

        $this->assertSame('Apple', $post->fresh()->name);
        $entry = Activity::query()->latest('id')->first();
        $this->assertSame(['updated', 'admin', 'Admin', ['name' => ['old' => 'Plum', 'new' => 'Apple']]], [$entry->event, $entry->source, $entry->admin_name, $entry->changes]);
        $this->assertSame(['Apple', 'Plum', 'Pear', 'Apple'], $this->names($post), 'What it held before stays as a version.');
    }

    public function test_a_version_the_form_no_longer_takes_is_not_put_back(): void
    {
        $post = $this->changedTwice();
        $broken = Revision::query()->create(['module' => 'posts', 'record_id' => $post->id, 'values' => ['name' => '', 'sort' => 'many']]);
        $url = "/admin/posts/{$post->id}/history";

        $this->from($url)->post("{$url}/{$broken->id}")
            ->assertRedirect($url)
            ->assertSessionHas('error', 'The version cannot be restored: The name field is required. The sort field must be a number.');

        $this->assertSame('Plum', $post->fresh()->name);
    }

    public function test_putting_a_version_back_needs_the_right_to_change_the_record(): void
    {
        $post = $this->changedTwice();
        $apple = app(Revisions::class)->of($post)->get()->last();
        $other = Post::query()->create(['name' => 'Other']);
        $this->put("/admin/posts/{$other->id}", ['name' => 'Other 2'])->assertSessionHasNoErrors();

        $this->post("/admin/posts/{$other->id}/history/{$apple->id}")->assertNotFound();
        $this->post('/admin/posts/'.($post->id + 50)."/history/{$apple->id}")->assertNotFound();

        $this->actingAs($this->editor(['posts.view']), 'admin')->post("/admin/posts/{$post->id}/history/{$apple->id}")->assertForbidden();
        $this->assertSame('Plum', $post->fresh()->name);
    }

    public function test_the_api_lists_the_versions_of_a_record_and_puts_one_back(): void
    {
        $post = $this->changedTwice();
        [$plum, , $apple] = app(Revisions::class)->of($post)->pluck('id')->all();
        $this->app['auth']->forgetGuards();
        $owner = $this->editor(['posts.view', 'posts.update']);
        $read = $owner->createToken('reader', ['posts:read'])->plainTextToken;
        $write = $owner->createToken('writer', ['posts:read', 'posts:write'])->plainTextToken;

        $versions = $this->withToken($read)->getJson("/api/v1/posts/{$post->id}/versions")->assertOk()->json('data');
        $this->assertSame([$plum, '2026-11-21T11:00:00+00:00', 'Admin', 'admin', 'Plum'], [$versions[0]['id'], $versions[0]['at'], $versions[0]['admin'], $versions[0]['source'], $versions[0]['values']['name']]);
        $this->assertSame([$apple, null, null, null, 'Apple'], [$versions[2]['id'], $versions[2]['at'], $versions[2]['admin'], $versions[2]['source'], $versions[2]['values']['name']]);
        $this->assertSame(['id', 'at', 'admin', 'source', 'values'], array_keys($versions[0]));

        $this->withToken($read)->postJson("/api/v1/posts/{$post->id}/versions/{$apple}/restore")->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($write)->postJson("/api/v1/posts/{$post->id}/versions/{$apple}/restore")->assertOk()
            ->assertJsonPath('data.id', $post->id)
            ->assertJsonPath('data.name', 'Apple');

        $this->assertSame('Apple', $post->fresh()->name);
        $this->assertSame(['api', 'Editor'], [Activity::query()->latest('id')->value('source'), Activity::query()->latest('id')->value('admin_name')]);
    }

    public function test_the_api_refuses_what_cannot_be_put_back(): void
    {
        $post = $this->changedTwice();
        $broken = Revision::query()->create(['module' => 'posts', 'record_id' => $post->id, 'values' => ['name' => '']]);
        $other = Post::query()->create(['name' => 'Other']);
        $this->app['auth']->forgetGuards();
        $owner = $this->editor(['posts.view', 'untrackeds.view']);
        $token = $owner->createToken('writer', ['posts:read', 'posts:write', 'untrackeds:read'])->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/posts/{$post->id}/versions/{$broken->id}/restore")->assertForbidden();
        $owner->roles()->sole()->update(['permissions' => ['posts.view', 'posts.update', 'untrackeds.view']]);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson("/api/v1/posts/{$post->id}/versions/{$broken->id}/restore")->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->withToken($token)->postJson("/api/v1/posts/{$other->id}/versions/{$broken->id}/restore")->assertNotFound();
        $this->withToken($token)->getJson("/api/v1/untrackeds/{$post->id}/versions")->assertNotFound()
            ->assertJsonPath('message', 'This module keeps no versions of its records.');
        $this->withToken($token)->getJson("/api/v1/posts/{$other->id}/versions")->assertOk()->assertExactJson(['data' => []]);
        $this->assertSame('Plum', $post->fresh()->name);
    }
}
