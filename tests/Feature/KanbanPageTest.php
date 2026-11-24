<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Filters\BooleanFilter;
use Atrium\Admin\Hooks\Refused;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Tests\Concerns\PinsExtensionSurface;
use Atrium\Admin\Tests\Fixtures\Chapter;
use Atrium\Admin\Tests\Fixtures\Plain;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Task;
use Atrium\Admin\Tests\Fixtures\Ticket;
use Atrium\Admin\Tests\TestCase;
use Atrium\Admin\Views\Board;
use Atrium\Admin\Views\KanbanView;
use Inertia\Testing\AssertableInertia;

/**
 * A module's list as a board: what the page carries, the cards a column
 * loads on request, and what dropping a card in a column saves.
 */
final class KanbanPageTest extends TestCase
{
    use PinsExtensionSurface;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.locale', 'en');
        $app['config']->set('atrium.resources', [Post::class, Task::class, Ticket::class, Plain::class]);
    }

    /**
     * @param  array<int,string>  $permissions
     */
    private function editor(array $permissions): AdminUser
    {
        $user = AdminUser::query()->create(['name' => 'Editor', 'email' => 'editor'.AdminUser::query()->count().'@example.com', 'password' => 'secret-password', 'is_active' => true]);
        $user->roles()->attach(AdminRole::query()->create(['name' => 'Role '.$user->email, 'permissions' => $permissions]));

        return $user->fresh();
    }

    /**
     * @param  array<int,int>  $ranks
     * @return array<int,int>
     */
    private function tasks(string $status, array $ranks): array
    {
        return array_map(static fn (int $rank): int => Task::query()->create(['title' => "{$status} {$rank}", 'status' => $status, 'rank' => $rank])->id, $ranks);
    }

    private function board(AssertableInertia $page): AssertableInertia
    {
        return $page->where('view', 'kanban')->missing('items');
    }

    public function test_a_module_with_a_board_offers_it_next_to_the_table(): void
    {
        $this->tasks('todo', [10]);
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/tasks')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('views', ['table', 'kanban'])
            ->where('view', 'table')
            ->has('items.data', 1)
            ->missing('board'));
        $this->get('/admin/posts')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('views', ['table'])
            ->where('view', 'table')
            ->has('items.data', 0));
        $this->get('/admin/posts?view=kanban')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('view', 'table')->has('items'));
    }

    public function test_the_board_has_a_column_for_every_option_with_its_first_cards(): void
    {
        [$a, $b, $c] = $this->tasks('todo', [30, 10, 20]);
        $this->tasks('done', [5]);
        $lane = Chapter::query()->create(['name' => 'Lane one']);
        Task::query()->whereKey($b)->update(['lane_id' => $lane->id, 'due_on' => '2026-12-31', 'urgent' => true]);
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/tasks?view=kanban')->assertOk()->assertInertia(fn (AssertableInertia $page) => $this->board($page)
            ->component('Admin/Resource/Index')
            ->where('board.field', 'status')
            ->where('board.ordered', true)
            ->has('board.columns', 3)
            ->where('board.columns.0.value', 'todo')
            ->where('board.columns.0.label', 'To do')
            ->where('board.columns.0.total', 3)
            ->where('board.columns.0.more', true)
            ->has('board.columns.0.cards', 2)
            ->where('board.columns.0.cards.0.id', $b)
            ->where('board.columns.0.cards.0.title', 'todo 10')
            ->where('board.columns.0.cards.0.url', route('admin.tasks.edit', $b))
            ->where('board.columns.0.cards.0.fields', [
                ['label' => 'Lane', 'text' => 'Lane one'],
                ['label' => 'Due', 'text' => '31 Dec 2026'],
                ['label' => 'Urgent', 'text' => '✓'],
            ])
            ->where('board.columns.0.cards.1.id', $c)
            ->where('board.columns.1.value', 'doing')
            ->where('board.columns.1.total', 0)
            ->where('board.columns.1.more', false)
            ->has('board.columns.1.cards', 0)
            ->where('board.columns.2.total', 1)
            ->where('board.columns.2.more', false)
            ->has('board.columns.2.cards', 1));
        $this->assertNotSame($a, $b);
    }

    public function test_records_no_column_takes_get_a_column_of_their_own_while_there_are_any(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/tasks?view=kanban')->assertInertia(fn (AssertableInertia $page) => $page->has('board.columns', 3));

        Task::query()->create(['title' => 'Nothing', 'status' => null]);
        Task::query()->create(['title' => 'Old', 'status' => 'archived']);

        $this->get('/admin/tasks?view=kanban')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('board.columns', 4)
            ->where('board.columns.3.value', null)
            ->where('board.columns.3.label', 'No value')
            ->where('board.columns.3.total', 2)
            ->has('board.columns.3.cards', 2));
    }

    public function test_the_board_is_narrowed_by_the_search_and_the_filters_of_the_table(): void
    {
        $one = Task::query()->create(['title' => 'Apples', 'status' => 'todo', 'urgent' => true])->id;
        Task::query()->create(['title' => 'Pears', 'status' => 'todo']);
        Task::query()->create(['title' => 'Apple pie', 'status' => 'done']);
        Hook::filter('atrium.module.filters', static fn (array $filters, string $model): array => $model === Task::class ? [...$filters, BooleanFilter::make('urgent')->label('Urgent')] : $filters);
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/tasks?view=kanban&search=Apple')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.search', 'Apple')
            ->where('board.columns.0.total', 1)
            ->where('board.columns.2.total', 1));
        $this->get('/admin/tasks?view=kanban&urgent=1')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters', ['search' => '', 'urgent' => '1'])
            ->where('filterControls.0.name', 'urgent')
            ->where('board.columns.0.total', 1)
            ->where('board.columns.0.cards.0.id', $one)
            ->where('board.columns.2.total', 0));
    }

    public function test_the_look_an_administrator_chose_is_the_one_they_find_next_time(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin');

        $this->get('/admin/tasks')->assertInertia(fn (AssertableInertia $page) => $page->where('view', 'table'));
        $this->get('/admin/tasks?view=kanban')->assertInertia(fn (AssertableInertia $page) => $page->where('view', 'kanban'));
        $this->get('/admin/tasks')->assertInertia(fn (AssertableInertia $page) => $page->where('view', 'kanban')->where('views', ['table', 'kanban']));
        $this->assertSame(['tasks' => 'kanban'], $admin->fresh()->list_views);
        $this->get('/admin/tasks?view=table')->assertInertia(fn (AssertableInertia $page) => $page->where('view', 'table')->has('items'));
        $this->get('/admin/tasks')->assertInertia(fn (AssertableInertia $page) => $page->where('view', 'table'));
        $this->get('/admin/tasks?view=nonsense')->assertInertia(fn (AssertableInertia $page) => $page->where('view', 'table'));

        $other = $this->editor(['tasks.view']);
        $this->actingAs($other, 'admin')->get('/admin/tasks')->assertInertia(fn (AssertableInertia $page) => $page->where('view', 'table'));
    }

    public function test_more_cards_of_a_column_come_a_page_at_a_time(): void
    {
        [$a, $b, $c, $d, $e] = $this->tasks('todo', [10, 20, 30, 40, 50]);
        $this->tasks('done', [10]);
        $this->actingAs($this->admin(), 'admin');

        $this->getJson('/admin/tasks/cards?column=todo&page=2')->assertOk()
            ->assertJsonPath('more', true)
            ->assertJsonCount(2, 'cards')
            ->assertJsonPath('cards.0.id', $c)
            ->assertJsonPath('cards.0.title', 'todo 30')
            ->assertJsonPath('cards.0.url', route('admin.tasks.edit', $c))
            ->assertJsonPath('cards.1.id', $d);
        $this->getJson('/admin/tasks/cards?column=todo&page=3')->assertOk()
            ->assertJsonPath('more', false)
            ->assertJsonPath('cards.0.id', $e);
        $this->getJson('/admin/tasks/cards?column=todo')->assertJsonPath('cards.0.id', $a)->assertJsonPath('cards.1.id', $b);
        $this->getJson('/admin/tasks/cards?column=todo&page=junk')->assertJsonPath('cards.0.id', $a);
        $this->getJson('/admin/tasks/cards?column=todo&page=0')->assertOk()->assertJsonPath('cards.0.id', $a);
        $this->getJson('/admin/tasks/cards?column=todo&page=-3')->assertOk()->assertJsonPath('cards.0.id', $a);
        $this->get('/admin/tasks?view=kanban&page=2')->assertInertia(fn (AssertableInertia $page) => $page->where('board.columns.0.cards.0.id', $a));
        $this->getJson('/admin/tasks/cards?column=done')->assertJsonCount(1, 'cards')->assertJsonPath('more', false);
    }

    public function test_the_cards_of_no_column_and_of_a_column_that_is_not_there(): void
    {
        $this->tasks('todo', [10]);
        $none = Task::query()->create(['title' => 'Nothing', 'status' => null])->id;
        $this->actingAs($this->admin(), 'admin');

        $this->getJson('/admin/tasks/cards')->assertOk()->assertJsonCount(1, 'cards')->assertJsonPath('cards.0.id', $none);
        $this->getJson('/admin/tasks/cards?column=later')->assertNotFound();
        $this->getJson('/admin/tasks/cards?column[]=todo')->assertNotFound();
        $this->getJson('/admin/posts/cards')->assertNotFound();
    }

    public function test_more_cards_are_narrowed_like_the_board(): void
    {
        $one = Task::query()->create(['title' => 'Apples', 'status' => 'todo'])->id;
        Task::query()->create(['title' => 'Pears', 'status' => 'todo']);
        $this->actingAs($this->admin(), 'admin');

        $this->getJson('/admin/tasks/cards?column=todo&search=Apple')->assertJsonCount(1, 'cards')->assertJsonPath('cards.0.id', $one);
    }

    public function test_dropping_a_card_in_another_column_saves_it_there_in_the_place_it_was_dropped(): void
    {
        [$a, $b] = $this->tasks('todo', [10, 20]);
        [$x] = $this->tasks('doing', [10]);
        Task::query()->whereKey($a)->update(['due_on' => '2026-12-31', 'urgent' => true]);
        $this->actingAs($this->admin(), 'admin');

        $this->patchJson("/admin/tasks/{$a}/move", ['value' => 'doing', 'after' => $x])->assertOk()
            ->assertJsonPath('card.id', $a)
            ->assertJsonPath('card.title', 'todo 10');

        $task = Task::query()->findOrFail($a);
        $this->assertSame('doing', $task->status);
        $this->assertSame(10 + Board::STEP, $task->rank);
        $this->assertSame('todo 10', $task->title, 'What the move does not send stays.');
        $this->assertTrue($task->urgent);
        $this->assertSame('2026-12-31', $task->due_on?->format('Y-m-d'));
        $this->assertSame('todo', Task::query()->findOrFail($b)->status);

        $this->patchJson("/admin/tasks/{$b}/move", ['value' => 'doing'])->assertOk();
        $this->assertSame('doing', Task::query()->findOrFail($b)->status);
        $this->assertLessThan(Task::query()->findOrFail($x)->rank, Task::query()->findOrFail($b)->rank, 'Without "after" the card goes to the top.');
    }

    public function test_the_numbers_of_a_request_may_come_as_text(): void
    {
        [$a, $b] = $this->tasks('todo', [10, 20]);
        [$x] = $this->tasks('doing', [10]);
        $this->actingAs($this->admin(), 'admin');

        $this->patchJson("/admin/tasks/{$a}/move", ['value' => 'doing', 'after' => (string) $x])->assertOk();
        $this->assertSame(10 + Board::STEP, Task::query()->findOrFail($a)->rank);

        $this->patchJson("/admin/tasks/{$b}/move", ['value' => 5, 'after' => $x])->assertUnprocessable()->assertJsonValidationErrors(['status']);
    }

    public function test_the_address_of_a_move_sets_nothing(): void
    {
        [$a] = $this->tasks('todo', [10]);
        $this->actingAs($this->admin(), 'admin');

        $this->patchJson("/admin/tasks/{$a}/move?title=Hacked&urgent=1", ['value' => 'done'])->assertOk();

        $task = Task::query()->findOrFail($a);
        $this->assertSame('todo 10', $task->title);
        $this->assertFalse($task->urgent);
        $this->assertSame('done', $task->status);
    }

    public function test_a_card_dropped_in_its_own_column_changes_only_its_place(): void
    {
        [$a, $b, $c] = $this->tasks('todo', [10, 20, 30]);
        $this->actingAs($this->admin(), 'admin');

        $this->patchJson("/admin/tasks/{$a}/move", ['value' => 'todo', 'after' => $c])->assertOk();

        $this->assertSame([$b, $c, $a], Task::query()->where('status', 'todo')->orderBy('rank')->pluck('id')->all());
        $this->assertSame(40, Task::query()->findOrFail($a)->rank);
    }

    public function test_the_cards_that_make_room_are_saved_with_it(): void
    {
        [$a, $b] = $this->tasks('todo', [10, 11]);
        [$x] = $this->tasks('doing', [1]);
        $this->actingAs($this->admin(), 'admin');

        $this->patchJson("/admin/tasks/{$x}/move", ['value' => 'todo', 'after' => $a])->assertOk();

        $this->assertSame(['doing' => 0, 'todo' => 3], [
            'doing' => Task::query()->where('status', 'doing')->count(),
            'todo' => Task::query()->where('status', 'todo')->count(),
        ]);
        $this->assertSame([$a, $x, $b], Task::query()->where('status', 'todo')->orderBy('rank')->pluck('id')->all());
        $this->assertSame([10, 20, 30], Task::query()->where('status', 'todo')->orderBy('rank')->pluck('rank')->all());
    }

    public function test_a_value_the_field_does_not_take_is_refused_and_nothing_is_saved(): void
    {
        [$a, $b] = $this->tasks('todo', [10, 11]);
        [$x] = $this->tasks('doing', [1]);
        $this->actingAs($this->admin(), 'admin');

        $this->patchJson("/admin/tasks/{$x}/move", ['value' => 'later', 'after' => $a])->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->patchJson("/admin/tasks/{$x}/move", ['after' => $a])->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->patchJson("/admin/tasks/{$x}/move", ['value' => null, 'after' => $a])->assertUnprocessable();

        $this->assertSame('doing', Task::query()->findOrFail($x)->status);
        $this->assertSame([10, 11], [Task::query()->findOrFail($a)->rank, Task::query()->findOrFail($b)->rank], 'The cards that were to make room stay.');
    }

    public function test_a_move_validates_only_what_it_sends(): void
    {
        [$a] = $this->tasks('todo', [10]);
        $this->actingAs($this->admin(), 'admin');

        $this->patchJson("/admin/tasks/{$a}/move", ['value' => 'done', 'title' => '', 'rank' => 'text', 'urgent' => 'maybe'])->assertOk();

        $task = Task::query()->findOrFail($a);
        $this->assertSame('todo 10', $task->title, 'A field of the form that the view does not write is not taken from the request.');
        $this->assertSame(10, $task->rank);
        $this->assertFalse($task->urgent);
        $this->assertSame('done', $task->status);
    }

    public function test_a_card_that_is_not_there_or_a_board_that_is_not_declared_is_not_found(): void
    {
        $this->tasks('todo', [10]);
        $post = Post::query()->create(['name' => 'A', 'active' => true, 'sort' => 1]);
        $this->actingAs($this->admin(), 'admin');

        $this->patchJson('/admin/tasks/999/move', ['value' => 'done'])->assertNotFound();
        $this->patchJson("/admin/posts/{$post->id}/move", ['value' => 'done'])->assertNotFound();
        $this->patchJson('/admin/tasks/abc/move', ['value' => 'done'])->assertNotFound();
        $this->get('/admin/tasks/1/move')->assertStatus(405);
    }

    public function test_a_column_that_is_a_record_of_another_module_can_be_emptied(): void
    {
        $one = Chapter::query()->create(['name' => 'Lane one'])->id;
        $two = Chapter::query()->create(['name' => 'Lane two'])->id;
        $ticket = Ticket::query()->create(['name' => 'T', 'lane_id' => $one])->id;
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/tickets?view=kanban')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('board.ordered', false)
            ->where('board.columns.0.value', (string) $one)
            ->where('board.columns.0.label', 'Lane one')
            ->where('board.columns.0.total', 1)
            ->where('board.columns.0.cards.0.title', 'T')
            ->where('board.columns.0.cards.0.fields', [])
            ->where('board.columns.1.total', 0));

        $this->patchJson("/admin/tickets/{$ticket}/move", ['value' => (string) $two])->assertOk();
        $this->assertSame($two, Ticket::query()->findOrFail($ticket)->lane_id);

        $this->patchJson("/admin/tickets/{$ticket}/move", ['value' => null])->assertOk();
        $this->assertNull(Ticket::query()->findOrFail($ticket)->lane_id);

        $this->get('/admin/tickets?view=kanban')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('board.columns.2.value', null)
            ->where('board.columns.2.total', 1));
    }

    public function test_a_card_is_titled_by_the_field_the_view_names_else_by_the_record(): void
    {
        $ticket = Ticket::query()->create(['name' => 'Named']);
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/tickets?view=kanban')->assertInertia(fn (AssertableInertia $page) => $page->where('board.columns.0.cards.0.title', 'Named'));

        Hook::filter('atrium.module.views', static fn (array $views, string $model): array => $model === Ticket::class ? [KanbanView::make('lane_id')->title('id')] : $views);
        $this->get('/admin/tickets?view=kanban')->assertInertia(fn (AssertableInertia $page) => $page->where('board.columns.0.cards.0.title', (string) $ticket->id));

        Hook::filter('atrium.module.views', static fn (array $views, string $model): array => $model === Ticket::class ? [KanbanView::make('lane_id')->title('absent')] : $views, 20);
        $this->get('/admin/tickets?view=kanban')->assertInertia(fn (AssertableInertia $page) => $page->where('board.columns.0.cards.0.title', 'Named'));
    }

    public function test_a_module_with_no_title_of_its_own_titles_a_card_by_its_key(): void
    {
        $plain = Plain::query()->create(['name' => 'Plain one', 'site_id' => null]);
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/plains?view=kanban')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('board.columns.0.cards.0.id', $plain->id)
            ->where('board.columns.0.cards.0.title', (string) $plain->id));
    }

    public function test_a_hook_may_refuse_a_move_like_it_refuses_a_save(): void
    {
        [$a] = $this->tasks('todo', [10]);
        Hook::filter('atrium.record.data', static fn (array $data): array => ($data['status'] ?? null) === 'done' ? throw new Refused('Not done yet.', 'status') : $data);
        $this->actingAs($this->admin(), 'admin');

        $this->patchJson("/admin/tasks/{$a}/move", ['value' => 'done'])->assertUnprocessable()->assertJsonValidationErrors(['status']);
        $this->assertSame('todo', Task::query()->findOrFail($a)->status);

        $this->patchJson("/admin/tasks/{$a}/move", ['value' => 'doing'])->assertOk();
        $this->assertSame('doing', Task::query()->findOrFail($a)->status);
    }

    public function test_a_hook_that_hears_a_save_hears_a_move(): void
    {
        [$a] = $this->tasks('todo', [10]);
        $heard = [];
        Hook::on('atrium.record.saved', static function (Task $task, string $context) use (&$heard): void {
            $heard[] = [$task->id, $context, $task->status];
        });
        $this->actingAs($this->admin(), 'admin');

        $this->patchJson("/admin/tasks/{$a}/move", ['value' => 'done'])->assertOk();

        $this->assertSame([[$a, 'edit', 'done']], $heard);
    }

    public function test_who_may_not_update_may_look_but_not_move(): void
    {
        [$a] = $this->tasks('todo', [10]);
        $this->actingAs($this->editor(['tasks.view']), 'admin');

        $this->get('/admin/tasks?view=kanban')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('can.update', false)
            ->where('board.columns.0.cards.0.id', $a));
        $this->getJson('/admin/tasks/cards?column=todo')->assertOk();
        $this->patchJson("/admin/tasks/{$a}/move", ['value' => 'done'])->assertForbidden();
        $this->assertSame('todo', Task::query()->findOrFail($a)->status);
    }

    public function test_who_may_update_but_not_view_sees_nothing_of_the_board(): void
    {
        [$a] = $this->tasks('todo', [10]);
        $this->actingAs($this->editor(['tasks.update']), 'admin');

        $this->get('/admin/tasks?view=kanban')->assertForbidden();
        $this->getJson('/admin/tasks/cards?column=todo')->assertForbidden();
        $this->patchJson("/admin/tasks/{$a}/move", ['value' => 'done'])->assertOk();
    }

    public function test_who_may_update_may_move(): void
    {
        [$a] = $this->tasks('todo', [10]);
        $this->actingAs($this->editor(['tasks.view', 'tasks.update']), 'admin');

        $this->get('/admin/tasks?view=kanban')->assertInertia(fn (AssertableInertia $page) => $page->where('can.update', true));
        $this->patchJson("/admin/tasks/{$a}/move", ['value' => 'done'])->assertOk();
        $this->assertSame('done', Task::query()->findOrFail($a)->status);
    }

    public function test_nobody_signed_in_gets_nothing(): void
    {
        [$a] = $this->tasks('todo', [10]);

        $this->patchJson("/admin/tasks/{$a}/move", ['value' => 'done'])->assertUnauthorized();
        $this->getJson('/admin/tasks/cards')->assertUnauthorized();
        $this->assertSame('todo', Task::query()->findOrFail($a)->status);
    }

    public function test_a_board_shows_and_moves_only_the_records_of_the_site_in_the_header(): void
    {
        $second = Site::query()->create(['code' => 'second', 'name' => 'Second']);
        $main = Site::query()->where('code', 'main')->sole();
        $mine = Ticket::query()->create(['name' => 'Mine', 'site_id' => $main->id]);
        $theirs = Ticket::query()->create(['name' => 'Theirs', 'site_id' => $second->id]);
        $this->actingAs($this->admin(), 'admin');

        $this->post('/admin/site', ['site_id' => $main->id]);
        $this->get('/admin/tickets?view=kanban')->assertInertia(fn (AssertableInertia $page) => $page->where('board.columns.0.total', 1)->where('board.columns.0.cards.0.id', $mine->id));
        $this->patchJson("/admin/tickets/{$theirs->id}/move", ['value' => null])->assertNotFound();
        $this->patchJson("/admin/tickets/{$mine->id}/move", ['value' => null])->assertOk();

        $this->post('/admin/site', ['site_id' => $second->id]);
        $this->get('/admin/tickets?view=kanban')->assertInertia(fn (AssertableInertia $page) => $page->where('board.columns.0.cards.0.id', $theirs->id)->has('board.columns.0.cards', 1));
    }

    public function test_the_actions_of_a_board_are_public_for_the_router(): void
    {
        $this->tasks('todo', [10]);
        $this->actingAs($this->admin(), 'admin');
        $this->getJson('/admin/tasks/cards')->assertOk();
        $this->patchJson('/admin/tasks/1/move', ['value' => 'done'])->assertOk();

        $actions = $this->assertRouteActionsArePublic();

        $this->assertArrayHasKey('Atrium\\Admin\\Http\\Controllers\\AdminController::cards', $actions);
        $this->assertArrayHasKey('Atrium\\Admin\\Http\\Controllers\\AdminController::move', $actions);
    }
}
