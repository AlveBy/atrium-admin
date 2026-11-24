<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Actions\ModuleActions;
use Atrium\Admin\Actions\RunBulkAction;
use Atrium\Admin\History\ActivityLog;
use Atrium\Admin\Models\Activity;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\Sites;
use Atrium\Admin\Tests\Concerns\PinsExtensionSurface;
use Atrium\Admin\Tests\Fixtures\Actions\Flag;
use Atrium\Admin\Tests\Fixtures\Dispatch;
use Atrium\Admin\Tests\Fixtures\Draft;
use Atrium\Admin\Tests\Fixtures\Kit;
use Atrium\Admin\Tests\Fixtures\NotAdministrable;
use Atrium\Admin\Tests\Fixtures\Note;
use Atrium\Admin\Tests\Fixtures\PanelAccount;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Receipt;
use Atrium\Admin\Tests\Fixtures\Untracked;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use ReflectionClass;
use RuntimeException;

/**
 * The activity log writes who changed what in a record, from the form, the
 * API, an import and a module's buttons, and never a password.
 */
final class ActivityLogTest extends TestCase
{
    use PinsExtensionSurface;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Post::class, Dispatch::class, Note::class, Draft::class, Receipt::class, Untracked::class, Kit::class]);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Sanctum::class))->getFileName(), 2).'/database/migrations');
    }

    private function log(): ActivityLog
    {
        return app(ActivityLog::class);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function entries(): array
    {
        return Activity::query()->orderBy('id')->get()
            ->map(static fn (Activity $entry): array => $entry->only(['module', 'record_id', 'record_title', 'event', 'changes', 'admin_name', 'source']))
            ->all();
    }

    public function test_a_record_created_in_the_form_is_logged_with_its_values_and_who_did_it(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post('/admin/posts', ['name' => 'Apple', 'active' => false, 'sort' => 3])->assertSessionHasNoErrors();
        $post = Post::query()->sole();

        $this->assertSame([[
            'module' => 'posts', 'record_id' => $post->id, 'record_title' => 'Apple', 'event' => 'created',
            'changes' => ['name' => ['old' => null, 'new' => 'Apple'], 'active' => ['old' => null, 'new' => false], 'sort' => ['old' => null, 'new' => 3]],
            'admin_name' => 'Admin', 'source' => 'admin',
        ]], $this->entries());

        $entry = Activity::query()->sole();
        $this->assertSame($admin->id, $entry->admin_user_id);
        $this->assertNull($entry->site_id, 'Posts are shared by every site.');
        $this->assertNotNull($entry->created_at);
    }

    public function test_a_change_logs_only_the_fields_that_differ_and_a_save_without_one_logs_nothing(): void
    {
        $post = Post::query()->create(['name' => 'Apple', 'active' => true, 'sort' => 3]);
        $this->actingAs($this->admin(), 'admin');

        $this->put("/admin/posts/{$post->id}", ['name' => 'Apple', 'active' => true, 'sort' => 3])->assertSessionHasNoErrors();
        $this->assertSame([], $this->entries(), 'Nothing changed: nothing to log.');

        $this->put("/admin/posts/{$post->id}", ['name' => 'Pear', 'active' => false, 'sort' => 3])->assertSessionHasNoErrors();

        $this->assertSame([[
            'module' => 'posts', 'record_id' => $post->id, 'record_title' => 'Pear', 'event' => 'updated',
            'changes' => ['name' => ['old' => 'Apple', 'new' => 'Pear'], 'active' => ['old' => true, 'new' => false]],
            'admin_name' => 'Admin', 'source' => 'admin',
        ]], $this->entries());
    }

    public function test_a_new_record_lists_the_values_it_came_with_too(): void
    {
        $this->actingAs($this->admin(), 'admin')->post('/admin/drafts', ['name' => 'Plan'])->assertSessionHasNoErrors();

        $this->assertSame(['sort' => ['old' => null, 'new' => 7], 'name' => ['old' => null, 'new' => 'Plan']], Activity::query()->sole()->changes);
    }

    public function test_read_only_fields_are_not_compared(): void
    {
        $receipt = Receipt::query()->create(['name' => 'One']);

        $this->actingAs($this->admin(), 'admin')->put("/admin/receipts/{$receipt->id}", ['name' => 'Two'])->assertSessionHasNoErrors();

        $this->assertSame(['name' => ['old' => 'One', 'new' => 'Two']], Activity::query()->sole()->changes);
    }

    public function test_a_password_is_logged_as_changed_never_with_its_value(): void
    {
        $this->actingAs($this->admin(), 'admin')->post('/admin/users', [
            'name' => 'Editor', 'email' => 'editor@example.com', 'password' => 'long-password', 'password_confirmation' => 'long-password', 'is_active' => true,
        ])->assertSessionHasNoErrors();

        $entry = Activity::query()->where('module', 'users')->sole();
        $this->assertSame(['secret' => true], $entry->changes['password']);
        $this->assertArrayNotHasKey('password_confirmation', $entry->changes, 'Never saved, never logged.');
        $this->assertSame('editor@example.com', $entry->changes['email']['new']);
        $this->assertStringNotContainsString('long-password', (string) json_encode(Activity::query()->get()));
    }

    public function test_a_field_whose_name_says_it_is_secret_is_logged_without_its_value(): void
    {
        config(['atrium.history.secrets' => ['NAM', 'sOr']]);
        $post = Post::query()->create(['name' => 'Apple', 'sort' => 3]);
        $this->actingAs($this->admin(), 'admin');

        $this->put("/admin/posts/{$post->id}", ['name' => 'Pear', 'active' => false, 'sort' => 4])->assertSessionHasNoErrors();

        $this->assertSame(['active' => ['old' => true, 'new' => false], 'name' => ['secret' => true], 'sort' => ['secret' => true]], Activity::query()->sole()->changes);
    }

    public function test_a_module_or_the_whole_panel_can_go_without_history(): void
    {
        $this->actingAs($this->admin(), 'admin')->post('/admin/untrackeds', ['name' => 'Quiet'])->assertSessionHasNoErrors();
        $this->delete('/admin/untrackeds/'.Untracked::query()->sole()->id)->assertSessionHasNoErrors();
        $this->assertSame([], $this->entries());

        config(['atrium.history.enabled' => false]);
        $this->post('/admin/posts', ['name' => 'Apple'])->assertSessionHasNoErrors();
        $this->delete('/admin/posts/'.Post::query()->sole()->id)->assertSessionHasNoErrors();
        $this->assertSame([], $this->entries());

        $this->assertFalse($this->log()->enabledFor(Post::class));
        config(['atrium.history.enabled' => true]);
        $this->assertTrue($this->log()->enabledFor(Post::class));
        $this->assertTrue($this->log()->enabledFor(NotAdministrable::class), 'A model without the switch keeps history.');
        $this->assertFalse($this->log()->enabledFor(Untracked::class));
    }

    public function test_a_deleted_record_is_logged_with_its_title_and_no_changes(): void
    {
        $post = Post::query()->create(['name' => str_repeat('a', 300)]);

        $this->actingAs($this->admin(), 'admin')->delete("/admin/posts/{$post->id}")->assertSessionHasNoErrors();

        $entry = Activity::query()->sole();
        $this->assertSame(['deleted', null, $post->id], [$entry->event, $entry->changes, $entry->record_id]);
        $this->assertSame(str_repeat('a', 250).'...', $entry->record_title, 'A long title is cut to fit.');
    }

    public function test_a_record_of_one_site_is_logged_in_its_site(): void
    {
        $this->actingAs($this->admin(), 'admin')->post('/admin/notes', ['name' => 'Local', 'code' => 'local'])->assertSessionHasNoErrors();

        $this->assertSame(Site::query()->where('is_default', true)->sole()->id, Activity::query()->sole()->site_id);
    }

    public function test_the_api_is_named_as_the_source(): void
    {
        $token = $this->admin()->createToken('agent', ['posts:write'])->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/posts', ['name' => 'Robot'])->assertCreated();

        $entry = Activity::query()->sole();
        $this->assertSame(['api', 'Admin', 'created'], [$entry->source, $entry->admin_name, $entry->event]);
    }

    public function test_a_button_of_a_module_is_logged_field_by_field_from_the_panel_and_the_api(): void
    {
        $dispatch = Dispatch::query()->create(['name' => 'One', 'active' => false]);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post("/admin/dispatches/{$dispatch->id}/actions/publish")->assertSessionHas('success');
        $this->assertSame([[
            'module' => 'dispatches', 'record_id' => $dispatch->id, 'record_title' => 'One', 'event' => 'updated',
            'changes' => ['active' => ['old' => false, 'new' => true]], 'admin_name' => 'Admin', 'source' => 'admin',
        ]], $this->entries());

        $dispatch->refresh()->update(['active' => false]);
        $this->assertSame(1, Activity::query()->count(), 'Outside a button nothing is watched.');

        $this->app['auth']->forgetGuards();
        $token = $admin->createToken('agent', ['dispatches:read', 'dispatches:write'])->plainTextToken;
        $this->withToken($token)->postJson("/api/v1/dispatches/{$dispatch->id}/actions/publish")->assertOk();
        $this->assertSame('api', Activity::query()->latest('id')->first()->source);
    }

    public function test_a_bulk_action_is_logged_now_or_on_the_queue_by_whoever_started_it(): void
    {
        [$one, $two] = array_map(fn (string $name) => Dispatch::query()->create(['name' => $name, 'active' => true]), ['One', 'Two']);
        $admin = $this->admin();
        $this->actingAs($admin, 'admin');

        $this->post('/admin/dispatches/bulk/flag', ['ids' => [$one->id, $two->id]])->assertSessionHas('success');
        $this->assertSame([[$one->id, 'admin', 'Admin'], [$two->id, 'admin', 'Admin']], Activity::query()->orderBy('record_id')->get()->map(fn (Activity $a) => [$a->record_id, $a->source, $a->admin_name])->all());

        Queue::fake();
        $this->post('/admin/dispatches/bulk/queued', ['ids' => [$one->id]])->assertSessionHas('success');
        Queue::assertPushed(RunBulkAction::class, fn (RunBulkAction $job): bool => $job->admin === $admin->id && $job->source === 'admin');

        $this->app['auth']->forgetGuards();
        Dispatch::query()->whereKey($one->id)->update(['active' => true]);
        (new RunBulkAction(Flag::make('flag'), Dispatch::class, [$one->id], Site::query()->sole()->id, $admin->id, 'api'))
            ->handle(app(Sites::class), app(ModuleActions::class), $this->log());

        $last = Activity::query()->latest('id')->first();
        $this->assertSame([$one->id, 'api', 'Admin', $admin->id], [$last->record_id, $last->source, $last->admin_name, $last->admin_user_id]);
        $this->assertNull($this->log()->actor(), 'The job forgets whom it ran for.');
        $this->assertSame('admin', $this->log()->source(), 'Back to what the last request was.');
    }

    public function test_a_bulk_action_queued_by_an_account_that_is_no_administrator_names_nobody(): void
    {
        $dispatch = Dispatch::query()->create(['name' => 'One', 'active' => true]);
        $this->admin();
        config(['auth.providers.admin_users.model' => PanelAccount::class]);
        $this->actingAs(PanelAccount::query()->firstOrFail(), 'admin');

        Queue::fake();
        $this->post('/admin/dispatches/bulk/queued', ['ids' => [$dispatch->id]])->assertSessionHas('success');
        Queue::assertPushed(RunBulkAction::class, static fn (RunBulkAction $job): bool => $job->admin === null && $job->source === 'admin');
    }

    public function test_a_change_of_the_records_a_relation_field_chose_is_logged_by_their_ids(): void
    {
        [$one, $two] = array_map(static fn (string $name): Post => Post::query()->create(['name' => $name, 'active' => true]), ['One', 'Two']);
        $this->actingAs($this->admin(), 'admin');
        $this->post('/admin/kits', ['name' => 'Fruit', 'posts' => [$one->id]])->assertSessionHasNoErrors();
        $kit = Kit::query()->sole();

        $this->put("/admin/kits/{$kit->id}", ['name' => 'Fruit', 'posts' => [$two->id]])->assertSessionHasNoErrors();

        $this->assertSame(['posts' => ['old' => [$one->id], 'new' => [$two->id]]], Activity::query()->latest('id')->first()->changes);
    }

    public function test_a_queued_job_of_someone_gone_is_logged_by_nobody(): void
    {
        $dispatch = Dispatch::query()->create(['name' => 'One', 'active' => true]);

        (new RunBulkAction(Flag::make('flag'), Dispatch::class, [$dispatch->id], Site::query()->sole()->id))
            ->handle(app(Sites::class), app(ModuleActions::class), $this->log());

        $entry = Activity::query()->sole();
        $this->assertSame(['admin', null, null], [$entry->source, $entry->admin_name, $entry->admin_user_id]);
    }

    public function test_the_watch_logs_what_eloquent_writes_and_nothing_once_it_is_over(): void
    {
        $post = $this->log()->watch(static fn (): Post => Post::query()->create(['name' => '', 'active' => true, 'sort' => 5]));
        $this->assertSame(['created', ['active' => ['old' => null, 'new' => true], 'sort' => ['old' => null, 'new' => 5]]], [Activity::query()->sole()->event, Activity::query()->sole()->changes], 'An empty text is no value.');

        $this->log()->watch(fn () => $this->log()->observed(ActivityLog::UPDATED, $post->fresh()));
        $this->assertSame(1, Activity::query()->count(), 'An update that changed nothing.');

        $this->log()->watch(fn () => $this->log()->quietly(static fn () => $post->update(['name' => 'Quiet'])));
        $this->assertSame(1, Activity::query()->count(), 'A save that logs itself is not logged twice.');

        $this->log()->watch(static fn () => (new NotAdministrable)->setTable('posts')->forceFill(['name' => 'x'])->save());
        $this->assertSame(1, Activity::query()->count(), 'Not a module.');

        $this->log()->watch(static fn () => Untracked::query()->create(['name' => 'Opted out']));
        $this->assertSame(1, Activity::query()->count(), 'A module without history.');

        $post->update(['name' => 'Unwatched']);
        $this->log()->quietly(static fn () => null);
        $post->update(['name' => 'Still unwatched']);
        $this->assertSame(1, Activity::query()->count());

        try {
            $this->log()->watch(static fn () => throw new RuntimeException('boom'));
        } catch (RuntimeException) {
        }

        try {
            $this->log()->quietly(static fn () => throw new RuntimeException('boom'));
        } catch (RuntimeException) {
        }

        $post->update(['name' => 'After a failure']);
        $this->assertSame(1, Activity::query()->count(), 'A failure ends the watch too.');

        $this->log()->watch(static fn () => $post->update(['name' => 'Watched', 'sort' => 9]));
        $this->assertSame(['name' => ['old' => 'After a failure', 'new' => 'Watched'], 'sort' => ['old' => 5, 'new' => 9]], Activity::query()->latest('id')->first()->changes);
    }

    public function test_the_watch_reads_values_as_they_are_and_hides_secrets(): void
    {
        config(['atrium.history.secrets' => ['cod']]);

        $this->log()->watch(fn () => $this->log()->observed(ActivityLog::CREATED, (new Post)->forceFill([
            'id' => 9, 'name' => '["a",1]', 'code' => 'x', 'active' => null, 'sort' => 4, 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01',
        ])));
        $this->assertSame(['name' => ['old' => null, 'new' => ['a', 1]], 'code' => ['secret' => true], 'sort' => ['old' => null, 'new' => 4]], Activity::query()->sole()->changes);

        $this->log()->watch(fn () => $this->log()->observed(ActivityLog::CREATED, (new Post)->forceFill(['id' => 10, 'name' => '{"en":"Hi"}'])));
        $this->log()->watch(fn () => $this->log()->observed(ActivityLog::CREATED, (new Post)->forceFill(['id' => 11, 'name' => '{broken'])));
        $this->log()->watch(fn () => $this->log()->observed(ActivityLog::CREATED, (new Post)->forceFill(['id' => 12, 'name' => 'plain [text]'])));
        $this->log()->watch(fn () => $this->log()->observed(ActivityLog::CREATED, (new Post)->forceFill(['id' => 13, 'name' => ' [1]'])));

        $this->assertSame([['en' => 'Hi'], '{broken', 'plain [text]', ' [1]'], Activity::query()->where('id', '>', 1)->orderBy('id')->get()->map(fn (Activity $a) => $a->changes['name']['new'])->all());

        $this->log()->watch(fn () => $this->log()->observed(ActivityLog::CREATED, (new Post)->mergeCasts(['active' => 'bool'])->forceFill(['id' => 14, 'active' => 1])));
        $this->assertSame(['old' => null, 'new' => true], Activity::query()->latest('id')->first()->changes['active'], 'A "bool" cast as well as a "boolean" one.');

        $user = AdminUser::query()->create(['name' => 'Ed', 'email' => 'ed@example.com', 'password' => 'secret-password', 'is_active' => true]);
        $this->log()->watch(static fn () => $user->forceFill(['password' => 'other-password', 'remember_token' => 'abc'])->save());
        $this->assertSame(['password' => ['secret' => true], 'remember_token' => ['secret' => true]], Activity::query()->latest('id')->first()->changes);
    }

    public function test_during_names_the_source_and_the_actor_and_puts_them_back(): void
    {
        $admin = $this->admin();

        $seen = $this->log()->during(ActivityLog::SCHEDULE, $admin, fn (): array => [$this->log()->source(), $this->log()->actor()?->id]);
        $this->assertSame(['schedule', $admin->id], $seen);
        $this->assertSame(['console', null], [$this->log()->source(), $this->log()->actor()]);

        try {
            $this->log()->during(ActivityLog::IMPORT, $admin, static fn () => throw new RuntimeException('boom'));
        } catch (RuntimeException) {
        }

        $this->assertSame(['console', null], [$this->log()->source(), $this->log()->actor()]);

        $this->actingAs($admin, 'admin');
        $this->assertSame($admin->id, $this->log()->actor()?->id, 'Else whoever is signed in.');
        $other = AdminUser::query()->create(['name' => 'Ed', 'email' => 'ed@example.com', 'password' => 'secret-password', 'is_active' => true]);
        $this->assertSame($other->id, $this->log()->during(ActivityLog::IMPORT, $other, fn () => $this->log()->actor()?->id), 'The one during() names comes first.');

        config(['auth.providers.admin_users.model' => PanelAccount::class]);
        $this->app['auth']->forgetGuards();
        $this->actingAs(PanelAccount::query()->firstOrFail(), 'admin');
        $this->assertNull($this->log()->actor(), 'Only an administrator of the panel is named.');
    }

    public function test_two_values_are_the_same_as_the_form_shows_them(): void
    {
        $same = [[null, ''], [null, []], ['', []], [true, '1'], [false, '0'], [1, '1'], [1.5, '1.5'], [[1, true], ['1', '1']], ['a', 'a'], [['x' => null], ['x' => '']]];
        $different = [[true, '0'], [false, '1'], [false, null], [0, null], ['0', ''], [1, '2'], ['a', 'b'], [['x' => 1], ['x' => 2]], [['x' => 1], ['y' => 1]], [[1], 1]];

        foreach ($same as [$a, $b]) {
            $this->assertTrue(ActivityLog::same($a, $b), json_encode([$a, $b]));
        }

        foreach ($different as [$a, $b]) {
            $this->assertFalse(ActivityLog::same($a, $b), json_encode([$a, $b]));
        }
    }

    public function test_changes_are_the_fields_that_differ_and_the_secrets_given(): void
    {
        $this->assertSame(
            ['a' => ['old' => 1, 'new' => null], 'c' => ['old' => null, 'new' => 3], 'pin' => ['secret' => true]],
            $this->log()->changes(['a' => 1, 'b' => 2, 'd' => ''], ['b' => '2', 'c' => 3, 'd' => null], ['pin']),
        );
        $this->assertSame([], $this->log()->changes(['b' => 2], ['b' => 2]));
    }

    public function test_a_record_is_called_by_its_title_or_its_number(): void
    {
        $this->assertSame('Apple', ActivityLog::titleOf((new Post)->forceFill(['id' => 3, 'name' => 'Apple'])));
        $this->assertSame('#3', ActivityLog::titleOf((new NotAdministrable)->forceFill(['id' => 3])));
    }

    public function test_a_hidden_attribute_or_a_secret_name_in_any_case_is_secret(): void
    {
        config(['atrium.history.secrets' => ['', 'TOKEN']]);
        $user = new AdminUser;

        $this->assertTrue(ActivityLog::isSecretName('remember_token', $user));
        $this->assertTrue(ActivityLog::isSecretName('two_factor_code', $user), 'Hidden by the model.');
        $this->assertTrue(ActivityLog::isSecretName('Api_Token_Hash', new Post));
        $this->assertFalse(ActivityLog::isSecretName('name', new Post), 'An empty pattern matches nothing.');
    }

    public function test_the_source_is_where_the_request_came_from(): void
    {
        $this->assertSame('console', $this->log()->source());

        $this->actingAs($this->admin(), 'admin')->get('/admin/posts')->assertOk();
        $this->assertSame('admin', $this->log()->source());

        $this->log()->during(ActivityLog::IMPORT, null, fn () => $this->assertSame('import', $this->log()->source()));
    }
}
