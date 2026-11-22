<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Actions\ActionResult;
use Atrium\Admin\Actions\DeleteRecords;
use Atrium\Admin\Actions\LinkAction;
use Atrium\Admin\Actions\ModuleActions;
use Atrium\Admin\Actions\RunBulkAction;
use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\Sites;
use Atrium\Admin\Tests\Concerns\PinsExtensionSurface;
use Atrium\Admin\Tests\Fixtures\Actions\Explode;
use Atrium\Admin\Tests\Fixtures\Actions\Flag;
use Atrium\Admin\Tests\Fixtures\Actions\Inspect;
use Atrium\Admin\Tests\Fixtures\Actions\Publish;
use Atrium\Admin\Tests\Fixtures\Actions\Stamp;
use Atrium\Admin\Tests\Fixtures\Bare;
use Atrium\Admin\Tests\Fixtures\Dispatch;
use Atrium\Admin\Tests\Fixtures\Note;
use Atrium\Admin\Tests\Fixtures\PanelAccount;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use RuntimeException;

/**
 * Buttons on a record (links and server actions) and bulk actions over the
 * records checked in a list: who sees them, how they run, what they may touch.
 */
final class ModuleActionsTest extends TestCase
{
    use PinsExtensionSurface;

    private const DELETE = ['type' => 'bulk', 'name' => 'delete', 'label' => 'Delete', 'confirm' => 'Delete the selected records? This cannot be undone.'];

    private const FLAG = ['type' => 'bulk', 'name' => 'flag', 'label' => 'Hide', 'confirm' => null];

    private const QUEUED = ['type' => 'bulk', 'name' => 'queued', 'label' => 'Hide later', 'confirm' => null];

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Post::class, Dispatch::class, Note::class, Bare::class]);
    }

    public function test_an_action_describes_itself(): void
    {
        $link = LinkAction::make('site')->label('Site')->url('https://example.org/');
        $this->assertSame(['type' => 'link', 'name' => 'site', 'label' => 'Site', 'confirm' => null, 'new_tab' => false], $link->toArray());
        $this->assertSame('https://example.org/', $link->href(new Dispatch));
        $this->assertSame('dispatches.view', $link->permissionIn('dispatches'), 'A link only looks.');
        $this->assertTrue($link->newTab()->toArray()['new_tab']);
        $this->assertSame('', LinkAction::make('bare')->href(new Dispatch), 'No address yet: nowhere.');
        $seven = (new Dispatch)->forceFill(['id' => 7]);
        $this->assertSame('/d/7', LinkAction::make('d')->url(fn (Dispatch $d): string => '/d/'.$d->id)->href($seven));
        $this->assertSame('7', LinkAction::make('id')->url(fn (Dispatch $d): int => $d->id)->href($seven), 'Whatever the closure gives is an address.');

        $publish = Publish::make('publish');
        $this->assertSame(['type' => 'record', 'name' => 'publish', 'label' => '', 'confirm' => null], $publish->toArray());
        $this->assertSame('publish', $publish->getName());
        $this->assertSame('dispatches.update', $publish->permissionIn('dispatches'), 'Anything else changes the record.');
        $this->assertSame('dispatches.publish', $publish->permission('publish')->permissionIn('dispatches'));
        $this->assertSame('menus.update', $publish->permission('menus.update')->permissionIn('dispatches'), 'A full name is taken as it is.');
        $this->assertSame('Are you sure?', $publish->confirm()->toArray()['confirm']);
        $this->assertSame('Really?', $publish->label('Publish')->confirm('Really?')->toArray()['confirm']);
        $this->assertSame('Publish', $publish->toArray()['label']);

        $this->assertSame('bulk', Flag::make('flag')->type());
        $this->assertSame('dispatches.delete', DeleteRecords::make('delete')->permissionIn('dispatches'));
    }

    public function test_a_result_is_a_message_and_maybe_an_address(): void
    {
        $done = ActionResult::success();
        $this->assertTrue($done->isSuccessful());
        $this->assertSame('Done.', $done->message());
        $this->assertNull($done->redirectUrl());

        $failed = ActionResult::error();
        $this->assertFalse($failed->isSuccessful());
        $this->assertSame('The action failed.', $failed->message());

        $this->assertSame('Saved.', ActionResult::success('Saved.')->message());
        $this->assertSame('Nope.', ActionResult::error('Nope.')->message());
        $this->assertSame('/next', ActionResult::error()->redirect('/next')->redirectUrl());
    }

    public function test_the_list_and_the_form_get_every_button_and_the_addresses_of_the_links(): void
    {
        $one = Dispatch::query()->create(['name' => 'One']);
        $two = Dispatch::query()->create(['name' => 'Two']);
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/dispatches')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->where('actions', [
                ['type' => 'link', 'name' => 'site', 'label' => 'Elsewhere', 'confirm' => null, 'new_tab' => false],
                ['type' => 'link', 'name' => 'preview', 'label' => 'Preview', 'confirm' => null, 'new_tab' => true],
                ['type' => 'record', 'name' => 'publish', 'label' => 'Publish', 'confirm' => 'Publish it?'],
                ['type' => 'record', 'name' => 'inspect', 'label' => 'Inspect', 'confirm' => null],
                ['type' => 'create', 'name' => 'copy', 'label' => 'Copy', 'fill' => ['name' => 'name']],
            ])
            ->where('bulkActions', [self::DELETE, self::FLAG, self::QUEUED])
            ->where('actionUrls', [
                $two->id => ['site' => 'https://example.org/', 'preview' => '/preview/'.$two->id],
                $one->id => ['site' => 'https://example.org/', 'preview' => '/preview/'.$one->id],
            ]));

        $this->get("/admin/dispatches/{$one->id}/edit")->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->where('actions.2.name', 'publish')
            ->where('actionUrls', [$one->id => ['site' => 'https://example.org/', 'preview' => '/preview/'.$one->id]]));

        // "Copy" fills the create form; the links and server buttons beside it fill nothing.
        $props = $this->get('/admin/dispatches/create?name=Copied')->assertOk()->viewData('page')['props'];
        $this->assertSame('Copied', collect($props['fields'])->firstWhere('name', 'name')['default'] ?? null);
    }

    public function test_an_empty_list_and_a_module_without_buttons_still_have_delete(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/dispatches')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->where('actionUrls', []));
        $this->get('/admin/posts')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->where('actions', [])->where('bulkActions', [self::DELETE]));
        $this->get('/admin/bares')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->where('actions', [])->where('bulkActions', [self::DELETE]));
    }

    public function test_an_administrator_sees_only_the_buttons_they_may_use(): void
    {
        $dispatch = Dispatch::query()->create(['name' => 'One']);

        $this->actingAs($this->editor(['dispatches.view']), 'admin');
        $this->get('/admin/dispatches')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->where('actions', [
                ['type' => 'link', 'name' => 'site', 'label' => 'Elsewhere', 'confirm' => null, 'new_tab' => false],
                ['type' => 'link', 'name' => 'preview', 'label' => 'Preview', 'confirm' => null, 'new_tab' => true],
                ['type' => 'record', 'name' => 'inspect', 'label' => 'Inspect', 'confirm' => null],
            ])
            ->where('bulkActions', [])
            ->where('actionUrls', [$dispatch->id => ['site' => 'https://example.org/', 'preview' => '/preview/'.$dispatch->id]]));

        $this->actingAs($this->editor(['dispatches.update', 'dispatches.publish', 'dispatches.create']), 'admin');
        $this->get('/admin/dispatches')->assertForbidden();
        $this->actingAs($this->editor(['dispatches.view', 'dispatches.update', 'dispatches.publish', 'dispatches.create']), 'admin');
        $this->get("/admin/dispatches/{$dispatch->id}/edit")->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->where('actions', [
                ['type' => 'link', 'name' => 'site', 'label' => 'Elsewhere', 'confirm' => null, 'new_tab' => false],
                ['type' => 'link', 'name' => 'preview', 'label' => 'Preview', 'confirm' => null, 'new_tab' => true],
                ['type' => 'record', 'name' => 'publish', 'label' => 'Publish', 'confirm' => 'Publish it?'],
                ['type' => 'record', 'name' => 'inspect', 'label' => 'Inspect', 'confirm' => null],
                ['type' => 'create', 'name' => 'copy', 'label' => 'Copy', 'fill' => ['name' => 'name']],
            ]));
        $this->get('/admin/dispatches')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->where('bulkActions', [self::FLAG, self::QUEUED]));

        $this->actingAs($this->editor(['dispatches.view', 'dispatches.delete']), 'admin');
        $this->get('/admin/dispatches')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->where('bulkActions', [self::DELETE]));
    }

    public function test_an_account_that_is_not_an_administrator_model_gets_every_button(): void
    {
        $dispatch = Dispatch::query()->create(['name' => 'One', 'active' => false]);
        $this->admin();
        config(['auth.providers.admin_users.model' => PanelAccount::class]);
        $this->actingAs(PanelAccount::query()->sole(), 'admin');

        $this->get('/admin/dispatches')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->has('actions', 5)->where('bulkActions', [self::DELETE, self::FLAG, self::QUEUED]));
        $this->post("/admin/dispatches/{$dispatch->id}/actions/publish")->assertSessionHas('success', 'Published One.');
    }

    public function test_a_server_action_runs_on_the_record_and_says_how_it_went(): void
    {
        $draft = Dispatch::query()->create(['name' => 'Draft', 'active' => false]);
        $this->actingAs($this->admin(), 'admin');

        $this->from('/admin/dispatches')->post("/admin/dispatches/{$draft->id}/actions/publish")
            ->assertRedirect('/admin/dispatches')
            ->assertSessionHas('success', 'Published Draft.')
            ->assertSessionMissing('error');
        $this->assertTrue($draft->fresh()->active);

        $this->from("/admin/dispatches/{$draft->id}/edit")->post("/admin/dispatches/{$draft->id}/actions/publish")
            ->assertRedirect("/admin/dispatches/{$draft->id}/edit")
            ->assertSessionHas('error', 'Already published.')
            ->assertSessionMissing('success');
    }

    public function test_an_action_may_send_the_administrator_elsewhere(): void
    {
        $away = Dispatch::query()->create(['name' => 'Away']);
        $this->actingAs($this->admin(), 'admin');

        $this->from('/admin/dispatches')->post("/admin/dispatches/{$away->id}/actions/publish")
            ->assertRedirect('https://example.org/away')
            ->assertSessionHas('success', 'Done.');
        $this->from('/admin/dispatches')->post("/admin/dispatches/{$away->id}/actions/publish", [], ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://example.org/away');
    }

    public function test_a_button_that_is_not_there_or_not_theirs_is_refused(): void
    {
        $dispatch = Dispatch::query()->create(['name' => 'One', 'active' => false]);

        $this->actingAs($this->admin(), 'admin');
        $this->post("/admin/dispatches/{$dispatch->id}/actions/nope")->assertNotFound();
        $this->post("/admin/dispatches/{$dispatch->id}/actions/site")->assertNotFound();
        $this->post("/admin/dispatches/{$dispatch->id}/actions/copy")->assertNotFound();
        $this->post('/admin/dispatches/bulk/publish', ['ids' => [$dispatch->id]])->assertNotFound();
        $this->post('/admin/dispatches/'.($dispatch->id + 1).'/actions/publish')->assertNotFound();
        $this->post("/admin/dispatches/{$dispatch->id}/actions/a.b")->assertNotFound();

        $this->actingAs($this->editor(['dispatches.view', 'dispatches.update']), 'admin');
        $this->post("/admin/dispatches/{$dispatch->id}/actions/publish")->assertForbidden();
        $this->post('/admin/dispatches/bulk/delete', ['ids' => [$dispatch->id]])->assertForbidden();
        $this->post("/admin/dispatches/{$dispatch->id}/actions/inspect")->assertSessionHas('success', 'It is One.');

        $this->actingAs($this->editor(['posts.view']), 'admin');
        $this->post("/admin/dispatches/{$dispatch->id}/actions/inspect")->assertForbidden();
        $this->post('/admin/dispatches/bulk/flag', ['ids' => [$dispatch->id]])->assertForbidden();

        $this->assertFalse($dispatch->fresh()->active);
        $this->assertSame(1, Dispatch::query()->count());
    }

    public function test_a_bulk_action_gets_the_checked_records(): void
    {
        [$one, $two, $three] = array_map(fn (string $name) => Dispatch::query()->create(['name' => $name]), ['One', 'Two', 'Three']);
        $this->actingAs($this->admin(), 'admin');

        $this->from('/admin/dispatches')->post('/admin/dispatches/bulk/flag', ['ids' => [$three->id, (string) $one->id, $one->id + 100]])
            ->assertRedirect('/admin/dispatches')
            ->assertSessionHas('success', 'Hidden: One, Three.');

        $this->assertSame([false, true, false], [$one->fresh()->active, $two->fresh()->active, $three->fresh()->active]);
    }

    public function test_the_selection_is_checked_before_anything_runs(): void
    {
        $dispatch = Dispatch::query()->create(['name' => 'One']);
        $this->actingAs($this->admin(), 'admin');

        $this->post('/admin/dispatches/bulk/flag', [])->assertSessionHasErrors('ids');
        $this->post('/admin/dispatches/bulk/flag', ['ids' => []])->assertSessionHasErrors('ids');
        $this->post('/admin/dispatches/bulk/flag', ['ids' => (string) $dispatch->id])->assertSessionHasErrors('ids');
        $this->post('/admin/dispatches/bulk/flag', ['ids' => [$dispatch->id, 'x']])->assertSessionHasErrors('ids.1');
        $this->post('/admin/dispatches/bulk/flag', ['ids' => range(1, ModuleActions::MAX_SELECTED + 1)])->assertSessionHasErrors('ids');
        $this->assertTrue($dispatch->fresh()->active);

        $this->post('/admin/dispatches/bulk/flag', ['ids' => [...range($dispatch->id + 1, $dispatch->id + ModuleActions::MAX_SELECTED - 1), $dispatch->id]])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Hidden: One.');
        $this->assertSame(1000, ModuleActions::MAX_SELECTED);

        $this->post('/admin/dispatches/bulk/flag', ['ids' => [$dispatch->id + 5]])->assertSessionHas('error', 'None of the selected records was found.');
    }

    public function test_records_of_another_site_are_left_alone(): void
    {
        $main = Site::query()->where('is_default', true)->sole();
        $second = Site::query()->create(['code' => 'de', 'name' => 'Deutschland', 'domains' => ['example.de'], 'languages' => ['de']]);
        $mine = Note::query()->create(['name' => 'Mine', 'code' => 'a', 'site_id' => $main->id]);
        $theirs = Note::query()->create(['name' => 'Theirs', 'code' => 'b', 'site_id' => $second->id]);
        $this->actingAs($this->admin(), 'admin');

        $this->post('/admin/notes/bulk/delete', ['ids' => [$theirs->id]])->assertSessionHas('error', 'None of the selected records was found.');
        $this->post('/admin/notes/bulk/delete', ['ids' => [$mine->id, $theirs->id]])->assertSessionHas('success', '1 record deleted.');

        $this->assertSame(['Theirs'], Note::query()->withoutGlobalScopes()->pluck('name')->all());
    }

    public function test_delete_removes_what_may_be_removed_and_counts_the_rest(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $make = fn (string ...$names): array => array_map(fn (string $name): int => Dispatch::query()->create(['name' => $name])->id, $names);
        $deleted = [];
        Hook::on('atrium.record.deleted', function (Dispatch $record) use (&$deleted): void {
            $deleted[] = $record->name;
        });

        $this->post('/admin/dispatches/bulk/delete', ['ids' => $make('A', 'B')])->assertSessionHas('success', '2 records deleted.');
        $this->assertSame(['A', 'B'], $deleted);

        $this->post('/admin/dispatches/bulk/delete', ['ids' => $make('C', 'Locked', 'Pinned')])
            ->assertSessionHas('success', '1 record deleted. 2 records could not be deleted.');

        $this->post('/admin/dispatches/bulk/delete', ['ids' => Dispatch::query()->pluck('id')->all()])
            ->assertSessionHas('error', '2 records could not be deleted.');
        $this->post('/admin/dispatches/bulk/delete', ['ids' => Dispatch::query()->where('name', 'Locked')->pluck('id')->all()])
            ->assertSessionHas('error', '1 record could not be deleted.');

        $this->assertSame(['Locked', 'Pinned'], Dispatch::query()->orderBy('name')->pluck('name')->all());
        $this->assertSame(['A', 'B', 'C'], $deleted);
    }

    public function test_a_queued_action_goes_to_the_queue_with_the_site_it_was_started_in(): void
    {
        Queue::fake();
        [$one, $two] = array_map(fn (string $name) => Dispatch::query()->create(['name' => $name]), ['One', 'Two']);
        $site = Site::query()->where('is_default', true)->sole();
        $this->actingAs($this->admin(), 'admin');

        $this->post('/admin/dispatches/bulk/queued', ['ids' => [$two->id, $one->id, $two->id + 50]])
            ->assertSessionHas('success', 'Started for 2 records. They are processed in the background.');
        $this->post('/admin/dispatches/bulk/queued', ['ids' => [$one->id]])
            ->assertSessionHas('success', 'Started for 1 record. It runs in the background.');

        Queue::assertPushed(RunBulkAction::class, 2);
        Queue::assertPushed(RunBulkAction::class, fn (RunBulkAction $job): bool => $job->action->getName() === 'queued'
            && $job->model === Dispatch::class
            && $job->ids === [$one->id, $two->id]
            && $job->site === $site->id);
        $this->assertTrue($one->fresh()->active, 'Nothing has run yet.');
    }

    public function test_the_queued_job_runs_in_its_site_and_forgets_it_afterwards(): void
    {
        $main = Site::query()->where('is_default', true)->sole();
        $second = Site::query()->create(['code' => 'de', 'name' => 'Deutschland', 'domains' => ['example.de'], 'languages' => ['de']]);
        $mine = Note::query()->create(['name' => 'Mine', 'code' => 'a', 'site_id' => $main->id]);
        $theirs = Note::query()->create(['name' => 'Theirs', 'code' => 'b', 'site_id' => $second->id]);
        $sites = app(Sites::class);

        (new RunBulkAction(Stamp::make('stamp'), Note::class, [$mine->id, $theirs->id], $second->id))->handle($sites, app(ModuleActions::class));

        $this->assertSame(['Mine', 'Theirs (stamped)'], Note::query()->withoutGlobalScopes()->orderBy('id')->pluck('name')->all());
        $this->assertFalse($sites->isResolved());

        try {
            (new RunBulkAction(Explode::make('boom'), Note::class, [$theirs->id], $second->id))->handle($sites, app(ModuleActions::class));
            $this->fail('The failure reaches the queue.');
        } catch (RuntimeException $e) {
            $this->assertSame('Boom.', $e->getMessage());
        }
        $this->assertFalse($sites->isResolved(), 'Forgotten even when the action fails.');
    }

    public function test_other_modules_add_buttons_through_hooks(): void
    {
        $post = Post::query()->create(['name' => 'Hello', 'active' => false]);
        Hook::filter('atrium.module.actions', fn (array $actions, string $model): array => $model === Post::class
            ? [...$actions, 'junk', Publish::make('publish')->label('Publish'), Inspect::make('inspect')->permission('dispatches.view')]
            : $actions);
        Hook::filter('atrium.module.bulk_actions', fn (array $actions, string $model): array => $model === Post::class
            ? [...$actions, Flag::make('flag')->label('Hide'), 'junk']
            : $actions);

        $this->actingAs($this->editor(['posts.view', 'posts.update']), 'admin');
        $this->get('/admin/posts')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->where('actions', [['type' => 'record', 'name' => 'publish', 'label' => 'Publish', 'confirm' => null]])
            ->where('bulkActions', [self::FLAG]));

        $this->post("/admin/posts/{$post->id}/actions/publish")->assertSessionHas('success', 'Published Hello.');
        $this->post("/admin/posts/{$post->id}/actions/inspect")->assertForbidden();
        $this->post('/admin/posts/bulk/flag', ['ids' => [$post->id]])->assertSessionHas('success', 'Hidden: Hello.');
        $this->assertFalse($post->fresh()->active);
    }

    public function test_a_hook_that_returns_nothing_leaves_no_buttons(): void
    {
        Hook::filter('atrium.module.actions', fn (): mixed => null);
        Hook::filter('atrium.module.bulk_actions', fn (): mixed => null);
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/dispatches')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->where('actions', [])->where('bulkActions', []));
        $this->post('/admin/dispatches/bulk/delete', ['ids' => [1]])->assertNotFound();
    }

    /**
     * @param  array<int,string>  $permissions
     */
    private function editor(array $permissions): AdminUser
    {
        $email = 'editor'.AdminUser::query()->count().'@example.com';
        $user = AdminUser::query()->create(['name' => 'Editor', 'email' => $email, 'password' => 'secret-password', 'is_active' => true]);
        $user->roles()->attach(AdminRole::query()->create(['name' => 'Role '.$email, 'permissions' => $permissions]));

        return $user->fresh();
    }
}
