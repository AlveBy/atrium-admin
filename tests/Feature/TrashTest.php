<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Facades\Hook;
use Atrium\Admin\History\Trash;
use Atrium\Admin\Hooks\Refused;
use Atrium\Admin\Http\Controllers\Api\ResourceController;
use Atrium\Admin\Http\Controllers\TrashController;
use Atrium\Admin\Models\Activity;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\Revision;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Models\TrashedRecord;
use Atrium\Admin\Support\RecordDeleter;
use Atrium\Admin\Tests\Fixtures\Dispatch;
use Atrium\Admin\Tests\Fixtures\Note;
use Atrium\Admin\Tests\Fixtures\Pamphlet;
use Atrium\Admin\Tests\Fixtures\PanelAccount;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Untracked;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;
use ReflectionClass;

/**
 * The trash: a deleted record of a content module is hidden everywhere and
 * kept, to be restored or deleted for good, by hand or after
 * `atrium.trash.days`; service modules delete at once.
 */
final class TrashTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Post::class, Dispatch::class, Note::class, Untracked::class]);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Sanctum::class))->getFileName(), 2).'/database/migrations');
    }

    private function editor(array $permissions): AdminUser
    {
        $editor = AdminUser::query()->create(['name' => 'Editor', 'email' => 'editor@example.com', 'password' => 'secret-password', 'is_active' => true]);
        $editor->roles()->attach(AdminRole::query()->create(['name' => 'Editors', 'permissions' => $permissions]));

        return $editor;
    }

    /**
     * @param  Model&Administrable  $record
     */
    private function trash(Model $record): TrashedRecord
    {
        $this->assertTrue(app(RecordDeleter::class)->delete($record));

        return TrashedRecord::query()->latest('id')->firstOrFail();
    }

    public function test_a_deleted_record_is_hidden_everywhere_and_kept_in_its_table(): void
    {
        $admin = $this->admin();
        $post = Post::query()->create(['name' => 'Apple']);
        Carbon::setTestNow('2026-11-20 10:00:00');

        $this->actingAs($admin, 'admin')->delete("/admin/posts/{$post->id}")->assertRedirect();
        Carbon::setTestNow();

        $this->assertNull(Post::query()->find($post->id));
        $this->assertSame(1, DB::table('posts')->where('id', $post->id)->count());
        $this->get("/admin/posts/{$post->id}/edit")->assertNotFound();
        $this->getJson('/admin/posts')->assertOk();
        $this->assertSame([], $this->get('/admin/posts')->viewData('page')['props']['items']['data']);

        $entry = TrashedRecord::query()->sole();
        $this->assertSame(['posts', $post->id, null, 'Apple', $admin->id, 'Admin', '2026-11-20 10:00:00'], [
            $entry->module, $entry->record_id, $entry->site_id, $entry->record_title, $entry->admin_user_id, $entry->admin_name, $entry->created_at->format('Y-m-d H:i:s'),
        ]);
        $this->assertSame('deleted', Activity::query()->latest('id')->value('event'));
    }

    public function test_the_trash_hides_only_the_records_of_its_own_module(): void
    {
        // Untracked keeps its records in the table of posts.
        $post = Post::query()->create(['name' => 'Apple']);
        $this->trash(Untracked::query()->findOrFail($post->id));

        $this->assertNull(Untracked::query()->find($post->id));
        $this->assertSame('Apple', Post::query()->findOrFail($post->id)->name);
        $this->assertSame('untrackeds', TrashedRecord::query()->value('module'));
    }

    public function test_a_module_that_reads_its_address_from_a_record_boots_and_hides_its_trash(): void
    {
        $post = Post::query()->create(['name' => 'Apple']);
        $this->trash(Pamphlet::query()->findOrFail($post->id));

        $this->assertNull(Pamphlet::query()->find($post->id));
        $this->assertSame('pamphlets-posts', TrashedRecord::query()->value('module'));
        $this->assertSame('Apple', Post::query()->findOrFail($post->id)->name);
    }

    public function test_the_entry_keeps_the_site_of_the_record_a_short_title_and_no_one_from_the_console(): void
    {
        $site = Site::query()->where('is_default', true)->sole();
        $note = Note::query()->create(['name' => str_repeat('a', 300), 'code' => 'long', 'site_id' => $site->id]);

        $entry = $this->trash($note);

        $this->assertSame($site->id, $entry->site_id);
        $this->assertSame(str_repeat('a', 250).'...', $entry->record_title);
        $this->assertNull($entry->admin_user_id);
        $this->assertNull($entry->admin_name);
    }

    public function test_the_trash_tells_the_site_and_the_caches_the_record_is_gone_and_back(): void
    {
        $post = Post::query()->create(['name' => 'Apple']);
        $heard = [];

        foreach (['deleting', 'deleted', 'restored', 'saved'] as $event) {
            Event::listen("eloquent.{$event}: ".Post::class, static function (Post $record) use ($event, &$heard): void {
                $heard[] = [$event, $record->name];
            });
        }

        $entry = $this->trash($post);
        $this->assertSame([['deleted', 'Apple']], $heard);

        $heard = [];
        app(Trash::class)->restore($entry, $post);
        $this->assertSame([['restored', 'Apple'], ['saved', 'Apple']], $heard);
    }

    public function test_service_modules_and_other_models_delete_at_once(): void
    {
        $language = Language::query()->create(['code' => 'fr', 'name' => 'Français']);

        $this->assertTrue(app(RecordDeleter::class)->delete($language));

        $this->assertSame(0, DB::table('admin_languages')->where('code', 'fr')->count());
        $this->assertSame(0, TrashedRecord::query()->count());
        $this->assertFalse(app(Trash::class)->enabledFor(Language::class));
        $this->assertFalse(app(Trash::class)->enabledFor(AdminUser::class));
        $this->assertFalse(app(Trash::class)->enabledFor(TrashedRecord::class));
        $this->assertTrue(app(Trash::class)->enabledFor(Post::class));
    }

    public function test_the_trash_lists_the_newest_first_with_what_the_administrator_may_do(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin');
        Carbon::setTestNow('2026-11-20 10:00:00');
        $apple = $this->trash(Post::query()->create(['name' => 'Apple']));
        Carbon::setTestNow();
        $letter = $this->trash(Dispatch::query()->create(['name' => 'Letter']));

        $this->get('/admin/trash')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Trash/Index')
            ->where('filters', ['module' => ''])
            ->where('modules', [
                ['key' => 'dispatches', 'label' => 'Dispatches'],
                ['key' => 'notes', 'label' => 'Notes'],
                ['key' => 'posts', 'label' => 'Posts'],
                ['key' => 'sites', 'label' => 'Sites'],
                ['key' => 'untrackeds', 'label' => 'Untracked'],
            ])
            ->where('days', 30)
            ->has('entries.data', 2)
            ->where('entries.data.0.id', $letter->id)
            ->where('entries.data.0.module', 'Dispatches')
            ->where('entries.data.0.record', 'Letter')
            ->where('entries.data.1.id', $apple->id)
            ->where('entries.data.1.at', '2026-11-20T10:00:00+00:00')
            ->where('entries.data.1.admin', 'Admin')
            ->where('entries.data.1.module', 'Posts')
            ->where('entries.data.1.record', 'Apple')
            ->where('entries.data.1.restoreUrl', route('admin.trash.restore', $apple->id))
            ->where('entries.data.1.deleteUrl', route('admin.trash.destroy', $apple->id)));

        $this->get('/admin/trash?module=posts')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters', ['module' => 'posts'])
            ->has('entries.data', 1)
            ->where('entries.data.0.record', 'Apple'));

        config(['atrium.trash.days' => 0]);
        $this->get('/admin/trash')->assertInertia(fn (AssertableInertia $page) => $page->where('days', 0));
    }

    public function test_the_trash_is_read_fifty_at_a_time_and_keeps_the_filter_in_the_links(): void
    {
        foreach (range(1, 51) as $i) {
            TrashedRecord::query()->create(['module' => 'posts', 'record_id' => $i, 'record_title' => "Post {$i}"]);
        }

        $this->actingAs($this->admin(), 'admin')->get('/admin/trash?module=posts')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('entries.data', 50)
            ->where('entries.data.0.record', 'Post 51')
            ->where('entries.next_page_url', url('/admin/trash').'?module=posts&page=2'));
    }

    public function test_an_administrator_sees_and_acts_on_what_their_roles_allow_on_their_site(): void
    {
        $main = Site::query()->where('is_default', true)->sole();
        $second = Site::query()->create(['code' => 'de', 'name' => 'Deutschland', 'domains' => ['example.de'], 'languages' => ['de']]);
        $here = $this->trash(Note::query()->create(['name' => 'Here', 'code' => 'here', 'site_id' => $main->id]));
        $there = $this->trash(Note::query()->create(['name' => 'There', 'code' => 'there', 'site_id' => $second->id]));
        $post = $this->trash(Post::query()->create(['name' => 'Apple']));
        $letter = $this->trash(Dispatch::query()->create(['name' => 'Letter']));

        $this->actingAs($this->editor(['notes.view', 'notes.restore', 'posts.view', 'posts.force_delete']), 'admin');

        $this->get('/admin/trash')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('modules', [['key' => 'notes', 'label' => 'Notes'], ['key' => 'posts', 'label' => 'Posts']])
            ->has('entries.data', 2)
            ->where('entries.data.0.record', 'Apple')
            ->where('entries.data.0.restoreUrl', null)
            ->where('entries.data.0.deleteUrl', route('admin.trash.destroy', $post->id))
            ->where('entries.data.1.record', 'Here')
            ->where('entries.data.1.restoreUrl', route('admin.trash.restore', $here->id))
            ->where('entries.data.1.deleteUrl', null));

        $this->post("/admin/trash/{$post->id}/restore")->assertForbidden();
        $this->delete("/admin/trash/{$here->id}")->assertForbidden();
        $this->post("/admin/trash/{$there->id}/restore")->assertNotFound();
        $this->post("/admin/trash/{$letter->id}/restore")->assertNotFound();
        $this->assertSame(4, TrashedRecord::query()->count());

        $this->post("/admin/trash/{$here->id}/restore")->assertRedirect(route('admin.notes.edit', $here->record_id));
        $this->delete("/admin/trash/{$post->id}")->assertRedirect(route('admin.trash.index'));
        $this->assertSame([$there->id, $letter->id], TrashedRecord::query()->orderBy('id')->pluck('id')->all());
    }

    public function test_an_account_that_is_no_administrator_model_may_do_everything(): void
    {
        $this->admin();
        $entry = $this->trash(Post::query()->create(['name' => 'Apple']));
        config(['auth.providers.admin_users.model' => PanelAccount::class]);
        $this->actingAs(PanelAccount::query()->sole(), 'admin');

        $this->get('/admin/trash')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('modules', 5)
            ->where('entries.data.0.restoreUrl', route('admin.trash.restore', $entry->id))
            ->where('entries.data.0.deleteUrl', route('admin.trash.destroy', $entry->id)));
    }

    public function test_a_restored_record_is_back_as_it_was_and_opens(): void
    {
        $admin = $this->admin();
        $post = Post::query()->create(['name' => 'Apple']);
        $entry = $this->trash($post);

        $this->actingAs($admin, 'admin')->post("/admin/trash/{$entry->id}/restore")
            ->assertRedirect(route('admin.posts.edit', $post->id))
            ->assertSessionHas('success', 'The record is restored.');

        $this->assertSame('Apple', Post::query()->findOrFail($post->id)->name);
        $this->assertSame(0, TrashedRecord::query()->count());
        $restored = Activity::query()->latest('id')->firstOrFail();
        $this->assertSame(['restored', $admin->id, 'admin'], [$restored->event, $restored->admin_user_id, $restored->source]);
    }

    public function test_a_module_without_history_is_restored_without_an_entry_in_the_log(): void
    {
        $entry = $this->trash(Untracked::query()->create(['name' => 'Quiet']));

        $this->actingAs($this->admin(), 'admin')->post("/admin/trash/{$entry->id}/restore")->assertRedirect();

        $this->assertSame(0, Activity::query()->count());
        $this->assertSame(0, TrashedRecord::query()->count());
    }

    public function test_an_entry_whose_record_is_gone_cannot_be_restored_but_goes(): void
    {
        $post = Post::query()->create(['name' => 'Apple']);
        $gone = $this->trash($post);
        DB::table('posts')->delete();
        $removed = TrashedRecord::query()->create(['module' => 'removed', 'record_id' => 1, 'record_title' => 'Old']);
        $this->actingAs($this->admin(), 'admin');

        $this->post("/admin/trash/{$gone->id}/restore")->assertNotFound();
        $this->assertNull(app(Trash::class)->record($removed));

        $this->delete("/admin/trash/{$gone->id}")->assertRedirect(route('admin.trash.index'))->assertSessionHas('success', 'The record is deleted for good.');
        $this->assertTrue(app(Trash::class)->purge($removed));
        $this->assertSame(0, TrashedRecord::query()->count());
    }

    public function test_a_record_deleted_for_good_takes_its_versions_and_is_logged(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin')->post('/admin/posts', ['name' => 'Apple', 'active' => true, 'sort' => 10]);
        $post = Post::query()->sole();
        $entry = $this->trash($post);
        $this->assertSame(1, Revision::query()->count(), 'the trash keeps the versions');

        $this->delete("/admin/trash/{$entry->id}")->assertRedirect(route('admin.trash.index'));

        $this->assertSame(0, DB::table('posts')->count());
        $this->assertSame(0, TrashedRecord::query()->count());
        $this->assertSame(0, Revision::query()->count());
        $this->assertSame(['created', 'deleted', 'purged'], Activity::query()->orderBy('id')->pluck('event')->all());
    }

    public function test_what_the_module_refuses_to_delete_stays_in_the_trash(): void
    {
        $pinned = Dispatch::query()->create(['name' => 'Letter']);
        $entry = $this->trash($pinned);
        $pinned->update(['name' => 'Pinned']);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->from('/admin/trash')->delete("/admin/trash/{$entry->id}")
            ->assertRedirect('/admin/trash')
            ->assertSessionHas('error', __('atrium::resource.cannot_delete'));
        $this->assertSame(1, TrashedRecord::query()->count());

        $kept = $this->trash(Post::query()->create(['name' => 'Apple']));
        Hook::on('atrium.record.deleting', static fn () => throw new Refused('Still needed.'));

        $this->from('/admin/trash')->delete("/admin/trash/{$kept->id}")
            ->assertRedirect('/admin/trash')
            ->assertSessionHas('error', 'Still needed.');
        $this->assertSame(2, TrashedRecord::query()->count());
    }

    public function test_the_command_empties_what_has_been_in_the_trash_long_enough(): void
    {
        Carbon::setTestNow('2026-10-01 12:00:00');
        $old = $this->trash(Post::query()->create(['name' => 'Old']));
        $refused = $this->trash(Dispatch::query()->create(['name' => 'Kept']));
        Dispatch::query()->withoutGlobalScopes()->whereKey($refused->record_id)->update(['name' => 'Pinned']);
        Carbon::setTestNow('2026-10-02 12:00:00');
        $edge = $this->trash(Post::query()->create(['name' => 'Edge']));
        Carbon::setTestNow('2026-11-01 12:00:00');

        $this->artisan('atrium:trash:empty')->expectsOutput('1 deleted for good.')->assertSuccessful();

        $this->assertSame([$refused->id, $edge->id], TrashedRecord::query()->orderBy('id')->pluck('id')->all());
        $this->assertSame(0, DB::table('posts')->where('id', $old->record_id)->count());

        Carbon::setTestNow('2026-11-01 12:00:01');
        $this->artisan('atrium:trash:empty')->expectsOutput('1 deleted for good.')->assertSuccessful();
        $this->assertSame([$refused->id], TrashedRecord::query()->pluck('id')->all());
        Carbon::setTestNow();
    }

    public function test_the_command_keeps_what_a_listener_refuses(): void
    {
        $this->trash(Post::query()->create(['name' => 'Apple']));
        Hook::on('atrium.record.deleting', static fn () => throw new Refused('Still needed.'));

        $this->assertSame(0, app(Trash::class)->emptyOlderThan(now()->addDay()));
        $this->assertSame(1, TrashedRecord::query()->count());
    }

    public function test_the_trash_keeps_everything_when_the_days_are_zero(): void
    {
        Carbon::setTestNow('2020-01-01');
        $this->trash(Post::query()->create(['name' => 'Apple']));
        Carbon::setTestNow();
        config(['atrium.trash.days' => 0]);

        $this->artisan('atrium:trash:empty')->expectsOutput('The trash keeps everything: atrium.trash.days is 0.')->assertSuccessful();

        $this->assertSame(1, TrashedRecord::query()->count());
    }

    public function test_the_scheduler_empties_the_trash_every_night(): void
    {
        $events = array_values(array_filter(
            app(Schedule::class)->events(),
            static fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'atrium:trash:empty'),
        ));

        $this->assertCount(1, $events);
        $this->assertSame('0 0 * * *', $events[0]->expression);
        $this->assertTrue($events[0]->withoutOverlapping);
    }

    public function test_the_actions_of_the_trash_are_public(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $restored = $this->trash(Post::query()->create(['name' => 'Apple']));
        $deleted = $this->trash(Post::query()->create(['name' => 'Pear']));
        $this->get('/admin/trash')->assertOk();
        $this->post("/admin/trash/{$restored->id}/restore")->assertRedirect();
        $this->delete("/admin/trash/{$deleted->id}")->assertRedirect();

        $actions = $this->assertRouteActionsArePublic();

        $this->assertArrayHasKey(TrashController::class.'::index', $actions);
        $this->assertArrayHasKey(TrashController::class.'::restore', $actions);
        $this->assertArrayHasKey(TrashController::class.'::destroy', $actions);
    }

    public function test_the_api_lists_restores_and_deletes_for_good_what_is_in_the_trash(): void
    {
        Carbon::setTestNow('2026-11-20 10:00:00');
        $apple = $this->trash(Post::query()->create(['name' => 'Apple']));
        Carbon::setTestNow();
        $pear = $this->trash(Post::query()->create(['name' => 'Pear']));
        $owner = $this->editor(['posts.view', 'posts.restore', 'posts.force_delete']);
        $read = $owner->createToken('reader', ['posts:read'])->plainTextToken;
        $write = $owner->createToken('writer', ['posts:read', 'posts:write'])->plainTextToken;

        $this->withToken($read)->getJson('/api/v1/posts/trash')->assertOk()
            ->assertJsonPath('data.0.id', $pear->record_id)
            ->assertJsonPath('data.0.deleted_by', null)
            ->assertJsonPath('data.1', ['id' => $apple->record_id, 'title' => 'Apple', 'deleted_at' => '2026-11-20T10:00:00+00:00', 'deleted_by' => null])
            ->assertJsonPath('meta', ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 2]);
        $this->getJson('/api/v1/posts/trash?per_page=1&page=2')->assertJsonPath('data.0.title', 'Apple')->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/v1/posts/trash?per_page=0')->assertJsonPath('meta.per_page', 1);
        $this->getJson('/api/v1/posts/trash?per_page=500')->assertJsonPath('meta.per_page', 100);

        $this->postJson("/api/v1/posts/trash/{$apple->record_id}/restore")->assertForbidden();
        $this->deleteJson("/api/v1/posts/trash/{$pear->record_id}")->assertForbidden();
        $this->app['auth']->forgetGuards();

        $this->withToken($write)->postJson("/api/v1/posts/trash/{$apple->record_id}/restore")->assertOk()
            ->assertJsonPath('data.id', $apple->record_id)
            ->assertJsonPath('data.name', 'Apple');
        $this->assertSame(['restored', 'api', 'Editor'], [Activity::query()->latest('id')->value('event'), Activity::query()->latest('id')->value('source'), Activity::query()->latest('id')->value('admin_name')]);

        $this->deleteJson("/api/v1/posts/trash/{$pear->record_id}")->assertNoContent();
        $this->assertSame(0, DB::table('posts')->where('id', $pear->record_id)->count());
        $this->assertSame(0, TrashedRecord::query()->count());

        $this->postJson("/api/v1/posts/trash/{$pear->record_id}/restore")->assertNotFound();
        $this->deleteJson("/api/v1/posts/trash/{$apple->record_id}")->assertNotFound();
    }

    public function test_the_api_deletes_into_the_trash(): void
    {
        $post = Post::query()->create(['name' => 'Apple']);
        $token = $this->editor(['posts.view', 'posts.delete'])->createToken('writer', ['posts:read', 'posts:write'])->plainTextToken;

        $this->withToken($token)->deleteJson("/api/v1/posts/{$post->id}")->assertNoContent();

        $this->assertSame($post->id, TrashedRecord::query()->sole()->record_id);
        $this->getJson("/api/v1/posts/{$post->id}")->assertNotFound();
    }

    public function test_the_api_asks_for_the_permissions_of_the_trash(): void
    {
        $entry = $this->trash(Post::query()->create(['name' => 'Apple']));
        $token = $this->editor(['posts.view', 'posts.update', 'posts.delete'])->createToken('writer', ['posts:read', 'posts:write'])->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/posts/trash/{$entry->record_id}/restore")
            ->assertForbidden()->assertJsonPath('message', 'The token owner lacks the posts.restore permission.');
        $this->deleteJson("/api/v1/posts/trash/{$entry->record_id}")
            ->assertForbidden()->assertJsonPath('message', 'The token owner lacks the posts.force_delete permission.');
        $this->assertSame(1, TrashedRecord::query()->count());
    }

    public function test_the_api_finds_only_the_trash_of_the_module_and_the_site(): void
    {
        $second = Site::query()->create(['code' => 'de', 'name' => 'Deutschland', 'domains' => ['example.de'], 'languages' => ['de']]);
        $there = $this->trash(Note::query()->create(['name' => 'There', 'code' => 'there', 'site_id' => $second->id]));
        $post = $this->trash(Post::query()->create(['name' => 'Apple']));
        $token = $this->editor(['notes.view', 'notes.restore', 'dispatches.view', 'dispatches.restore', 'languages.view'])
            ->createToken('writer', ['notes:read', 'notes:write', 'dispatches:read', 'dispatches:write', 'languages:read'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/notes/trash')->assertOk()->assertJsonPath('meta.total', 0);
        $this->postJson("/api/v1/notes/trash/{$there->record_id}/restore")->assertNotFound();
        $this->postJson("/api/v1/dispatches/trash/{$post->record_id}/restore")->assertNotFound();
        $this->getJson('/api/v1/languages/trash')->assertNotFound()->assertJsonPath('message', 'This module has no trash: its records are deleted at once.');
        $this->assertSame(2, TrashedRecord::query()->count());
    }

    public function test_the_api_says_what_cannot_come_back_or_go_for_good(): void
    {
        $gone = $this->trash(Post::query()->create(['name' => 'Apple']));
        DB::table('posts')->delete();
        $pinned = Dispatch::query()->create(['name' => 'Letter']);
        $kept = $this->trash($pinned);
        DB::table('posts')->where('id', $kept->record_id)->update(['name' => 'Pinned']);
        $refused = $this->trash(Post::query()->create(['name' => 'Pear']));
        $token = $this->editor(['posts.view', 'posts.restore', 'posts.force_delete', 'dispatches.view', 'dispatches.force_delete'])
            ->createToken('writer', ['posts:read', 'posts:write', 'dispatches:read', 'dispatches:write'])->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/posts/trash/{$gone->record_id}/restore")
            ->assertNotFound()->assertJsonPath('message', 'The record is not there any more.');
        $this->deleteJson("/api/v1/dispatches/trash/{$kept->record_id}")
            ->assertConflict()->assertJsonPath('message', 'This record cannot be deleted.');
        Hook::on('atrium.record.deleting', static fn () => throw new Refused('Still needed.'));
        $this->deleteJson("/api/v1/posts/trash/{$refused->record_id}")
            ->assertConflict()->assertJsonPath('message', 'Still needed.');
        $this->assertSame(3, TrashedRecord::query()->count());
    }

    public function test_the_api_actions_of_the_trash_are_public(): void
    {
        $restored = $this->trash(Post::query()->create(['name' => 'Apple']));
        $deleted = $this->trash(Post::query()->create(['name' => 'Pear']));
        $token = $this->editor(['posts.view', 'posts.restore', 'posts.force_delete'])->createToken('writer', ['posts:read', 'posts:write'])->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/posts/trash')->assertOk();
        $this->postJson("/api/v1/posts/trash/{$restored->record_id}/restore")->assertOk();
        $this->deleteJson("/api/v1/posts/trash/{$deleted->record_id}")->assertNoContent();

        $actions = $this->assertRouteActionsArePublic();

        $this->assertArrayHasKey(ResourceController::class.'::listTrash', $actions);
        $this->assertArrayHasKey(ResourceController::class.'::restoreFromTrash', $actions);
        $this->assertArrayHasKey(ResourceController::class.'::deleteFromTrash', $actions);
    }
}
