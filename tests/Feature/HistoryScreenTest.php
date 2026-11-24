<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Http\Controllers\Api\ResourceController;
use Atrium\Admin\Http\Controllers\HistoryController;
use Atrium\Admin\Models\Activity;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Tests\Fixtures\Dispatch;
use Atrium\Admin\Tests\Fixtures\Draft;
use Atrium\Admin\Tests\Fixtures\Kit;
use Atrium\Admin\Tests\Fixtures\Note;
use Atrium\Admin\Tests\Fixtures\PanelAccount;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Untracked;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

/**
 * The activity log on screen: the journal with its filters, the history of
 * one record, and who sees which entries.
 */
final class HistoryScreenTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Post::class, Dispatch::class, Note::class, Draft::class, Untracked::class, Kit::class]);
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    private function entry(array $attributes): Activity
    {
        return Activity::query()->create([
            'module' => 'posts', 'record_id' => 1, 'record_title' => 'Apple', 'event' => 'updated', 'source' => 'admin', ...$attributes,
        ]);
    }

    private function editor(array $permissions): AdminUser
    {
        $editor = AdminUser::query()->create(['name' => 'Editor', 'email' => 'editor@example.com', 'password' => 'secret-password', 'is_active' => true]);
        $editor->roles()->attach(AdminRole::query()->create(['name' => 'Editors', 'permissions' => $permissions]));

        return $editor;
    }

    public function test_the_journal_shows_the_newest_entries_first_with_labels_and_links(): void
    {
        $admin = $this->admin();
        $post = Post::query()->create(['name' => 'Apple']);
        Carbon::setTestNow('2026-11-20 10:00:00');
        $this->entry(['record_id' => $post->id, 'event' => 'created', 'admin_user_id' => $admin->id, 'admin_name' => 'Admin',
            'changes' => ['name' => ['old' => null, 'new' => 'Apple'], 'gone_field' => ['old' => 1, 'new' => 2], 'pin' => ['secret' => true]]]);
        $this->entry(['record_id' => $post->id + 1, 'record_title' => 'Pear', 'event' => 'deleted', 'source' => 'api']);
        Carbon::setTestNow();

        $this->actingAs($admin, 'admin')->get('/admin/activity')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/History/Index')
            ->where('filters', ['module' => '', 'admin' => '', 'from' => '', 'to' => ''])
            ->where('modules', [
                ['key' => 'users', 'label' => 'Administrators'],
                ['key' => 'dispatches', 'label' => 'Dispatches'],
                ['key' => 'drafts', 'label' => 'drafts'],
                ['key' => 'kits', 'label' => 'Kits'],
                ['key' => 'languages', 'label' => 'Languages'],
                ['key' => 'notes', 'label' => 'Notes'],
                ['key' => 'posts', 'label' => 'Posts'],
                ['key' => 'sites', 'label' => 'Sites'],
            ])
            ->where('admins', [['id' => $admin->id, 'name' => 'Admin']])
            ->has('entries.data', 2)
            ->where('entries.data.0.record', 'Pear')
            ->where('entries.data.0.url', null)
            ->where('entries.data.0.changes', [])
            ->where('entries.data.0.admin', null)
            ->where('entries.data.0.source', 'api')
            ->where('entries.data.1.id', Activity::query()->min('id'))
            ->where('entries.data.1.at', '2026-11-20T10:00:00+00:00')
            ->where('entries.data.1.event', 'created')
            ->where('entries.data.1.module', 'Posts')
            ->where('entries.data.1.admin', 'Admin')
            ->where('entries.data.1.url', route('admin.posts.edit', $post->id))
            ->where('entries.data.1.changes', [
                ['field' => 'name', 'label' => 'Name', 'old' => null, 'new' => 'Apple', 'secret' => false],
                ['field' => 'gone_field', 'label' => 'Gone Field', 'old' => 1, 'new' => 2, 'secret' => false],
                ['field' => 'pin', 'label' => 'Pin', 'old' => null, 'new' => null, 'secret' => true],
            ]));
    }

    public function test_the_journal_is_narrowed_by_module_administrator_and_days_inclusive(): void
    {
        $admin = $this->admin();
        $other = AdminUser::query()->create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'secret-password', 'is_active' => true]);

        foreach (['2026-11-09 23:59:59', '2026-11-10 00:00:00', '2026-11-12 23:59:59', '2026-11-13 00:00:00'] as $i => $at) {
            Carbon::setTestNow($at);
            $this->entry(['record_title' => "At {$i}", 'admin_user_id' => $i === 1 ? $other->id : $admin->id]);
        }
        $this->entry(['module' => 'dispatches', 'record_title' => 'Dispatch']);
        Carbon::setTestNow();

        $titles = fn (string $query): array => $this->actingAs($admin, 'admin')->get('/admin/activity?'.$query)->assertOk()
            ->viewData('page')['props']['entries']['data'];
        $names = static fn (array $entries): array => array_column($entries, 'record');

        $this->assertSame(['At 2', 'At 1'], $names($titles('from=2026-11-10&to=2026-11-12')));
        $this->assertSame(['At 1'], $names($titles('admin='.$other->id)));
        $this->assertSame(['Dispatch'], $names($titles('module=dispatches')));
        $this->assertSame(['Dispatch', 'At 3', 'At 2', 'At 1', 'At 0'], $names($titles('')));

        $this->get('/admin/activity?module=posts&admin='.$admin->id.'&from=2026-11-01&to=2026-11-30')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters', ['module' => 'posts', 'admin' => (string) $admin->id, 'from' => '2026-11-01', 'to' => '2026-11-30']));
    }

    public function test_wrong_filters_are_refused(): void
    {
        $this->actingAs($this->admin(), 'admin')->from('/admin/activity')
            ->get('/admin/activity?admin=bob&from=never&to=later&module='.str_repeat('m', 101))
            ->assertSessionHasErrors(['admin', 'from', 'to', 'module']);
        $this->get('/admin/activity?module='.str_repeat('m', 100))->assertOk();
        $this->get('/admin/activity?module=&admin=&from=&to=')->assertOk()->assertSessionHasNoErrors()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('filters', ['module' => '', 'admin' => '', 'from' => '', 'to' => '']));
    }

    public function test_the_journal_comes_in_pages_of_fifty_that_keep_the_filters(): void
    {
        foreach (range(1, 51) as $i) {
            $this->entry(['record_title' => "Entry {$i}"]);
        }

        $this->actingAs($this->admin(), 'admin')->get('/admin/activity?module=posts')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('entries.data', 50)
            ->where('entries.data.0.record', 'Entry 51')
            ->where('entries.last_page', 2)
            ->where('entries.next_page_url', url('/admin/activity').'?module=posts&page=2'));
    }

    public function test_an_administrator_sees_the_modules_they_may_view_of_their_site(): void
    {
        $main = Site::query()->where('is_default', true)->sole();
        $second = Site::query()->create(['code' => 'de', 'name' => 'Deutschland', 'domains' => ['example.de'], 'languages' => ['de']]);
        $this->entry(['record_title' => 'Shared']);
        $this->entry(['module' => 'notes', 'record_title' => 'Here', 'site_id' => $main->id, 'changes' => ['name' => ['old' => 'A', 'new' => 'B'], 'code' => ['old' => 'a', 'new' => 'b']]]);
        $this->entry(['module' => 'notes', 'record_title' => 'There', 'site_id' => $second->id]);
        $this->entry(['module' => 'dispatches', 'record_title' => 'Hidden']);

        $editor = $this->editor(['posts.view', 'notes.view']);

        $this->actingAs($editor, 'admin')->get('/admin/activity')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('modules', [['key' => 'notes', 'label' => 'Notes'], ['key' => 'posts', 'label' => 'Posts']])
            ->has('entries.data', 2)
            ->where('entries.data.0.record', 'Here')
            ->where('entries.data.0.changes.0.label', 'Title')
            ->where('entries.data.0.changes.1.label', 'Code')
            ->where('entries.data.1.record', 'Shared'));
    }

    public function test_an_account_that_is_no_administrator_model_sees_every_module(): void
    {
        $this->admin();
        config(['auth.providers.admin_users.model' => PanelAccount::class]);
        $this->actingAs(PanelAccount::query()->sole(), 'admin');

        $this->get('/admin/activity')->assertInertia(fn (AssertableInertia $page) => $page->has('modules', 8));
    }

    public function test_a_record_shows_its_own_history_to_whoever_may_view_it(): void
    {
        $post = Post::query()->create(['name' => 'Apple']);
        $this->entry(['record_id' => $post->id, 'record_title' => 'Old name']);
        $this->entry(['record_id' => $post->id + 1, 'record_title' => 'Another']);
        $this->entry(['module' => 'dispatches', 'record_id' => $post->id, 'record_title' => 'Other module']);

        $this->actingAs($this->editor(['posts.view']), 'admin')->get("/admin/posts/{$post->id}/history")->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/History/Record')
                ->where('title', 'History: Apple')
                ->where('moduleLabel', 'Posts')
                ->where('indexUrl', route('admin.posts.index'))
                ->where('editUrl', route('admin.posts.edit', $post->id))
                ->has('entries.data', 1)
                ->where('entries.data.0.record', 'Old name'));

        $this->get("/admin/dispatches/{$post->id}/history")->assertForbidden();
        $this->get('/admin/posts/'.($post->id + 50).'/history')->assertNotFound();
    }

    public function test_a_record_of_another_site_has_no_history_here(): void
    {
        $second = Site::query()->create(['code' => 'de', 'name' => 'Deutschland', 'domains' => ['example.de'], 'languages' => ['de']]);
        $note = Note::query()->create(['name' => 'There', 'code' => 'there', 'site_id' => $second->id]);

        $this->actingAs($this->admin(), 'admin')->get("/admin/notes/{$note->id}/history")->assertNotFound();
    }

    public function test_the_form_links_to_the_history_unless_the_module_keeps_none(): void
    {
        $admin = $this->admin();
        $post = Post::query()->create(['name' => 'Apple']);
        $quiet = Untracked::query()->create(['name' => 'Quiet']);
        $this->actingAs($admin, 'admin');

        $this->get("/admin/posts/{$post->id}/edit")->assertInertia(fn (AssertableInertia $page) => $page->where('historyUrl', route('admin.posts.history', $post->id)));
        $this->get("/admin/users/{$admin->id}/edit")->assertInertia(fn (AssertableInertia $page) => $page->where('historyUrl', route('admin.users.history', $admin->id)));
        $this->get("/admin/untrackeds/{$quiet->id}/edit")->assertInertia(fn (AssertableInertia $page) => $page->where('historyUrl', null));
        $this->get("/admin/untrackeds/{$quiet->id}/history")->assertNotFound();
        $this->get("/admin/users/{$admin->id}/history")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('title', 'History: Admin'));
    }

    public function test_a_change_is_named_by_the_label_of_its_field_on_the_form(): void
    {
        $this->entry(['module' => 'kits', 'changes' => ['name' => ['old' => 'A', 'new' => 'B'], 'post_id' => ['old' => 1, 'new' => 2], 'gone' => ['old' => 1, 'new' => 2]]]);

        $this->actingAs($this->admin(), 'admin')->get('/admin/activity')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('entries.data.0.changes.0.label', 'Name')
            ->where('entries.data.0.changes.1.label', 'Main post')
            ->where('entries.data.0.changes.2.label', 'Gone'));
    }

    public function test_the_actions_of_the_screens_are_public(): void
    {
        $post = Post::query()->create(['name' => 'Apple']);
        $this->actingAs($this->admin(), 'admin')->get('/admin/activity')->assertOk();
        $this->get("/admin/posts/{$post->id}/history")->assertOk();

        $actions = $this->assertRouteActionsArePublic();

        $this->assertArrayHasKey(HistoryController::class.'::index', $actions);
        $this->assertArrayHasKey(HistoryController::class.'::record', $actions);
        $this->assertArrayHasKey(HistoryController::class.'::revert', $actions);
        $this->assertArrayHasKey(ResourceController::class.'::listVersions', $actions);
        $this->assertArrayHasKey(ResourceController::class.'::restoreVersion', $actions);
    }

    public function test_the_menu_has_the_journal_once(): void
    {
        $journal = fn (): array => array_values(array_filter(
            $this->get('/admin/posts')->viewData('page')['props']['menu'],
            static fn (array $item): bool => ($item['route'] ?? null) === 'admin.activity.index',
        ));
        $this->actingAs($this->admin(), 'admin');

        $this->assertSame([['label' => 'Activity log', 'route' => 'admin.activity.index', 'icon' => 'clock']], array_map(
            static fn (array $item): array => array_intersect_key($item, ['label' => 1, 'route' => 1, 'icon' => 1]),
            $journal(),
        ));

        config(['atrium.menu' => [['label' => 'History', 'route' => 'admin.activity.index']]]);

        $this->assertSame(['History'], array_column($journal(), 'label'));
    }
}
