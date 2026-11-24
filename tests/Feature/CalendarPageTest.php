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
use Atrium\Admin\Models\Site;
use Atrium\Admin\Tests\Concerns\PinsExtensionSurface;
use Atrium\Admin\Tests\Fixtures\Holiday;
use Atrium\Admin\Tests\Fixtures\Meeting;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Task;
use Atrium\Admin\Tests\TestCase;
use Atrium\Admin\Views\CalendarView;
use Atrium\Admin\Views\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

/**
 * A module's list as a calendar: what the page carries for a month, a week
 * and a list, what dropping a record on a day saves, and what a click on a
 * day opens.
 */
final class CalendarPageTest extends TestCase
{
    use PinsExtensionSurface;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.locale', 'en');
        $app['config']->set('atrium.resources', [Post::class, Task::class, Meeting::class, Holiday::class]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-12-15 12:00:00'));
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

    private function meeting(string $title, ?string $start, ?string $end = null): Meeting
    {
        return Meeting::query()->create(['title' => $title, 'starts_at' => $start, 'ends_at' => $end]);
    }

    private function default(TestResponse $response, string $field): mixed
    {
        $props = $response->assertOk()->viewData('page')['props'];

        return collect($props['fields'])->firstWhere('name', $field)['default'] ?? null;
    }

    public function test_a_module_with_a_calendar_offers_it_next_to_the_table(): void
    {
        $this->meeting('Standup', '2026-12-15 09:00:00');
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/meetings')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('views', ['table', 'calendar'])
            ->where('view', 'table')
            ->has('items.data', 1)
            ->missing('calendar')
            ->missing('board'));
        $this->get('/admin/meetings?view=calendar')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Resource/Index')
            ->where('view', 'calendar')
            ->missing('items')
            ->missing('board')
            ->has('calendar'));
        $this->get('/admin/tasks')->assertInertia(fn (AssertableInertia $page) => $page->where('views', ['table', 'kanban']));
    }

    public function test_the_month_is_six_weeks_around_today_with_the_records_that_fall_in_them(): void
    {
        $in = $this->meeting('Standup', '2026-12-15 09:30:00', '2026-12-15 10:00:00');
        $this->meeting('Last month', '2026-11-15 09:30:00');
        $this->meeting('Next year', '2027-01-20 09:30:00');
        $this->meeting('Undated', null);
        $first = $this->meeting('First day of the grid', '2026-11-30 08:00:00');
        $last = $this->meeting('Last day of the grid', '2027-01-10 23:00:00');
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/meetings?view=calendar')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('calendar.mode', 'month')
            ->where('calendar.date', '2026-12-15')
            ->where('calendar.from', '2026-11-30')
            ->where('calendar.to', '2027-01-10')
            ->where('calendar.today', '2026-12-15')
            ->where('calendar.field', 'starts_at')
            ->where('calendar.timed', true)
            ->where('calendar.truncated', false)
            ->has('calendar.events', 3)
            ->where('calendar.events.0.id', $first->id)
            ->where('calendar.events.0.title', 'First day of the grid')
            ->where('calendar.events.1.id', $in->id)
            ->where('calendar.events.1.title', 'Standup')
            ->where('calendar.events.1.start', '2026-12-15 09:30:00')
            ->where('calendar.events.1.end', '2026-12-15 10:00:00')
            ->where('calendar.events.1.url', route('admin.meetings.edit', $in->id))
            ->where('calendar.events.2.id', $last->id)
            ->where('calendar.events.2.end', null));
    }

    public function test_the_address_picks_the_mode_and_the_day(): void
    {
        $this->meeting('Early', '2026-12-14 08:00:00');
        $this->meeting('Late in the month', '2026-12-28 08:00:00');
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/meetings?view=calendar&mode=week&date=2026-12-16')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('calendar.mode', 'week')
            ->where('calendar.date', '2026-12-16')
            ->where('calendar.from', '2026-12-14')
            ->where('calendar.to', '2026-12-20')
            ->has('calendar.events', 1)
            ->where('calendar.events.0.title', 'Early'));
        $this->get('/admin/meetings?view=calendar&mode=list&date=2026-12-02')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('calendar.mode', 'list')
            ->where('calendar.from', '2026-12-01')
            ->where('calendar.to', '2026-12-31')
            ->has('calendar.events', 2));
        $this->get('/admin/meetings?view=calendar&mode=year&date=2026-02-30')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('calendar.mode', 'month')
            ->where('calendar.date', '2026-12-15'));
    }

    public function test_a_date_field_has_days_and_no_time(): void
    {
        $holiday = Holiday::query()->create(['name' => 'New Year', 'on_day' => '2026-12-31']);
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/holidays?view=calendar')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('calendar.timed', false)
            ->where('calendar.field', 'on_day')
            ->has('calendar.events', 1)
            ->where('calendar.events.0.id', $holiday->id)
            ->where('calendar.events.0.title', 'New Year')
            ->where('calendar.events.0.start', '2026-12-31')
            ->where('calendar.events.0.end', null));
    }

    public function test_an_event_is_titled_by_the_field_the_view_names_else_by_the_record(): void
    {
        $this->meeting('Standup', '2026-12-15 09:00:00');
        $this->actingAs($this->admin(), 'admin');

        Hook::filter('atrium.module.views', static fn (array $views, string $model): array => $model === Meeting::class ? [CalendarView::make('starts_at')->title('nope')] : $views, 20);

        $this->get('/admin/meetings?view=calendar')->assertInertia(fn (AssertableInertia $page) => $page->where('calendar.events.0.title', 'Standup'));

        Hook::filter('atrium.module.views', static fn (array $views, string $model): array => $model === Meeting::class ? [CalendarView::make('starts_at')] : $views, 30);

        $this->get('/admin/meetings?view=calendar')->assertInertia(fn (AssertableInertia $page) => $page->where('calendar.events.0.title', 'Standup'));
    }

    public function test_a_range_with_more_records_than_the_most_says_so(): void
    {
        $rows = array_map(static fn (int $n): array => ['title' => "m{$n}", 'starts_at' => '2026-12-15 09:00:00', 'ends_at' => null, 'created_at' => now(), 'updated_at' => now()], range(1, Schedule::MAX_EVENTS));
        Meeting::query()->insert($rows);
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/meetings?view=calendar')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('calendar.truncated', false)
            ->has('calendar.events', Schedule::MAX_EVENTS));

        $this->meeting('One too many', '2026-12-15 09:00:00');

        $this->get('/admin/meetings?view=calendar')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('calendar.truncated', true)
            ->has('calendar.events', Schedule::MAX_EVENTS)
            ->where('calendar.events.499.title', 'm500'));
    }

    public function test_the_calendar_is_narrowed_by_the_search_of_the_table(): void
    {
        $this->meeting('Apples', '2026-12-15 09:00:00');
        $this->meeting('Pears', '2026-12-16 09:00:00');
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/meetings?view=calendar&search=Apple')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('calendar.events', 1)
            ->where('calendar.events.0.title', 'Apples'));
    }

    public function test_dropping_a_record_on_a_day_saves_it_there_with_its_time_and_length(): void
    {
        $a = $this->meeting('Workshop', '2026-12-15 09:30:00', '2026-12-16 17:00:00');
        $this->actingAs($this->admin(), 'admin');

        $this->patchJson("/admin/meetings/{$a->id}/move", ['view' => 'calendar', 'day' => '2026-12-22'])->assertOk()
            ->assertJsonPath('event.id', $a->id)
            ->assertJsonPath('event.title', 'Workshop')
            ->assertJsonPath('event.start', '2026-12-22 09:30:00')
            ->assertJsonPath('event.end', '2026-12-23 17:00:00')
            ->assertJsonPath('event.url', route('admin.meetings.edit', $a->id));

        $meeting = Meeting::query()->findOrFail($a->id);
        $this->assertSame('2026-12-22 09:30:00', $meeting->starts_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-23 17:00:00', $meeting->ends_at?->format('Y-m-d H:i:s'));
        $this->assertSame('Workshop', $meeting->title, 'What the move does not write stays.');
    }

    public function test_a_field_the_view_does_not_write_is_not_taken_from_the_request(): void
    {
        $a = $this->meeting('Workshop', '2026-12-15 09:30:00');
        $this->actingAs($this->admin(), 'admin');

        $this->patchJson("/admin/meetings/{$a->id}/move?title=Hacked", ['view' => 'calendar', 'day' => '2026-12-22', 'title' => ''])->assertOk();

        $this->assertSame('Workshop', Meeting::query()->findOrFail($a->id)->title);
    }

    public function test_a_date_is_dropped_as_a_date(): void
    {
        $holiday = Holiday::query()->create(['name' => 'New Year', 'on_day' => '2026-12-31']);
        $this->actingAs($this->admin(), 'admin');

        $this->patchJson("/admin/holidays/{$holiday->id}/move", ['view' => 'calendar', 'day' => '2026-12-24'])->assertOk()
            ->assertJsonPath('event.start', '2026-12-24');

        $this->assertSame('2026-12-24', Holiday::query()->findOrFail($holiday->id)->on_day?->format('Y-m-d'));
    }

    public function test_a_day_that_is_no_day_or_a_record_with_no_date_is_refused_and_nothing_is_saved(): void
    {
        $a = $this->meeting('Workshop', '2026-12-15 09:30:00');
        $undated = $this->meeting('Undated', null);
        $this->actingAs($this->admin(), 'admin');

        foreach (['2026-02-30', 'tomorrow', '', null, ['2026-12-22']] as $day) {
            $this->patchJson("/admin/meetings/{$a->id}/move", ['view' => 'calendar', 'day' => $day])->assertUnprocessable()
                ->assertJsonValidationErrors(['day'])
                ->assertJsonPath('errors.day.0', 'The day field must be a valid date.');
        }

        $this->patchJson("/admin/meetings/{$a->id}/move", ['view' => 'calendar'])->assertUnprocessable()->assertJsonValidationErrors(['day']);
        $this->patchJson("/admin/meetings/{$undated->id}/move", ['view' => 'calendar', 'day' => '2026-12-22'])->assertUnprocessable()
            ->assertJsonValidationErrors(['starts_at'])
            ->assertJsonPath('errors.starts_at.0', 'The record has no date, so it cannot be moved.');

        $this->assertSame('2026-12-15 09:30:00', Meeting::query()->findOrFail($a->id)->starts_at?->format('Y-m-d H:i:s'));
        $this->assertNull(Meeting::query()->findOrFail($undated->id)->starts_at);
    }

    public function test_a_view_that_is_not_declared_is_not_found(): void
    {
        $a = $this->meeting('Workshop', '2026-12-15 09:30:00');
        $this->actingAs($this->admin(), 'admin');

        $this->patchJson("/admin/meetings/{$a->id}/move", ['day' => '2026-12-22'])->assertNotFound();
        $this->patchJson("/admin/meetings/{$a->id}/move", ['view' => 'kanban', 'day' => '2026-12-22'])->assertNotFound();
        $this->patchJson("/admin/meetings/{$a->id}/move", ['view' => 'table', 'day' => '2026-12-22'])->assertNotFound();
        $this->patchJson("/admin/meetings/{$a->id}/move", ['view' => ['calendar'], 'day' => '2026-12-22'])->assertNotFound();
        $this->patchJson('/admin/meetings/999/move', ['view' => 'calendar', 'day' => '2026-12-22'])->assertNotFound();
        $this->patchJson("/admin/tasks/{$a->id}/move", ['view' => 'calendar', 'day' => '2026-12-22'])->assertNotFound();
        $this->assertSame('2026-12-15 09:30:00', Meeting::query()->findOrFail($a->id)->starts_at?->format('Y-m-d H:i:s'));
    }

    public function test_a_hook_may_refuse_a_drop_like_it_refuses_a_save_and_hears_it_when_it_is_saved(): void
    {
        $a = $this->meeting('Workshop', '2026-12-15 09:30:00');
        $heard = [];
        Hook::filter('atrium.record.data', static fn (array $data): array => str_starts_with((string) ($data['starts_at'] ?? ''), '2026-12-25') ? throw new Refused('Not on Christmas.', 'starts_at') : $data);
        Hook::on('atrium.record.saved', static function (Meeting $meeting, string $context) use (&$heard): void {
            $heard[] = [$meeting->id, $context, $meeting->starts_at?->format('Y-m-d')];
        });
        $this->actingAs($this->admin(), 'admin');

        $this->patchJson("/admin/meetings/{$a->id}/move", ['view' => 'calendar', 'day' => '2026-12-25'])->assertUnprocessable()->assertJsonValidationErrors(['starts_at']);
        $this->assertSame([], $heard);

        $this->patchJson("/admin/meetings/{$a->id}/move", ['view' => 'calendar', 'day' => '2026-12-26'])->assertOk();
        $this->assertSame([[$a->id, 'edit', '2026-12-26']], $heard);
    }

    public function test_who_may_not_update_may_look_but_not_move(): void
    {
        $a = $this->meeting('Workshop', '2026-12-15 09:30:00');
        $this->actingAs($this->editor(['meetings.view']), 'admin');

        $this->get('/admin/meetings?view=calendar')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('can.update', false)
            ->where('can.create', false)
            ->where('calendar.events.0.id', $a->id));
        $this->patchJson("/admin/meetings/{$a->id}/move", ['view' => 'calendar', 'day' => '2026-12-22'])->assertForbidden();
        $this->assertSame('2026-12-15 09:30:00', Meeting::query()->findOrFail($a->id)->starts_at?->format('Y-m-d H:i:s'));
    }

    public function test_who_may_update_and_view_may_move_and_who_may_only_update_sees_nothing(): void
    {
        $a = $this->meeting('Workshop', '2026-12-15 09:30:00');
        $this->actingAs($this->editor(['meetings.view', 'meetings.update', 'meetings.create']), 'admin');

        $this->get('/admin/meetings?view=calendar')->assertInertia(fn (AssertableInertia $page) => $page->where('can.update', true)->where('can.create', true));
        $this->patchJson("/admin/meetings/{$a->id}/move", ['view' => 'calendar', 'day' => '2026-12-22'])->assertOk();

        $this->actingAs($this->editor(['meetings.update']), 'admin');

        $this->get('/admin/meetings?view=calendar')->assertForbidden();
    }

    public function test_nobody_signed_in_gets_nothing(): void
    {
        $a = $this->meeting('Workshop', '2026-12-15 09:30:00');

        $this->patchJson("/admin/meetings/{$a->id}/move", ['view' => 'calendar', 'day' => '2026-12-22'])->assertUnauthorized();
        $this->assertSame('2026-12-15 09:30:00', Meeting::query()->findOrFail($a->id)->starts_at?->format('Y-m-d H:i:s'));
    }

    public function test_a_calendar_shows_and_moves_only_the_records_of_the_site_in_the_header(): void
    {
        $second = Site::query()->create(['code' => 'second', 'name' => 'Second']);
        $main = Site::query()->where('code', 'main')->sole();
        $mine = Holiday::query()->create(['name' => 'Mine', 'on_day' => '2026-12-20', 'site_id' => $main->id]);
        $theirs = Holiday::query()->create(['name' => 'Theirs', 'on_day' => '2026-12-21', 'site_id' => $second->id]);
        $this->actingAs($this->admin(), 'admin');

        $this->post('/admin/site', ['site_id' => $main->id]);
        $this->get('/admin/holidays?view=calendar')->assertInertia(fn (AssertableInertia $page) => $page->has('calendar.events', 1)->where('calendar.events.0.id', $mine->id));
        $this->patchJson("/admin/holidays/{$theirs->id}/move", ['view' => 'calendar', 'day' => '2026-12-22'])->assertNotFound();
        $this->patchJson("/admin/holidays/{$mine->id}/move", ['view' => 'calendar', 'day' => '2026-12-22'])->assertOk();

        $this->post('/admin/site', ['site_id' => $second->id]);
        $this->get('/admin/holidays?view=calendar')->assertInertia(fn (AssertableInertia $page) => $page->has('calendar.events', 1)->where('calendar.events.0.id', $theirs->id));
    }

    public function test_a_click_on_a_day_opens_the_form_with_that_day_in_the_field_of_the_calendar(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->assertSame('2026-12-20 09:00:00', $this->default($this->get('/admin/meetings/create?starts_at=2026-12-20'), 'starts_at'));
        $this->assertSame('2026-12-20', $this->default($this->get('/admin/holidays/create?on_day=2026-12-20'), 'on_day'));
    }

    public function test_only_a_real_day_in_the_field_of_a_calendar_fills_the_form(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->assertNull($this->default($this->get('/admin/meetings/create?starts_at=2026-02-30'), 'starts_at'));
        $this->assertNull($this->default($this->get('/admin/meetings/create?starts_at[]=2026-12-20'), 'starts_at'));
        $this->assertNull($this->default($this->get('/admin/meetings/create?ends_at=2026-12-20'), 'ends_at'), 'Only the field the calendar is on.');
        $this->assertNull($this->default($this->get('/admin/meetings/create?title=Injected'), 'title'));
        $this->assertNull($this->default($this->get('/admin/meetings/create'), 'starts_at'));
        $this->assertNull($this->default($this->get('/admin/tasks/create?due_on=2026-12-20'), 'due_on'), 'A module with no calendar fills nothing.');
    }

    public function test_a_calendar_after_another_view_still_fills_the_form(): void
    {
        $this->actingAs($this->admin(), 'admin');
        Hook::filter('atrium.module.views', static fn (array $views, string $model): array => $model === Task::class ? [...$views, CalendarView::make('due_on')->title('title')] : $views, 20);

        $this->assertSame('2026-12-20', $this->default($this->get('/admin/tasks/create?due_on=2026-12-20'), 'due_on'));
    }

    public function test_the_actions_of_a_calendar_are_public_for_the_router(): void
    {
        $a = $this->meeting('Workshop', '2026-12-15 09:30:00');
        $this->actingAs($this->admin(), 'admin');
        $this->patchJson("/admin/meetings/{$a->id}/move", ['view' => 'calendar', 'day' => '2026-12-22'])->assertOk();

        $this->assertArrayHasKey('Atrium\\Admin\\Http\\Controllers\\AdminController::move', $this->assertRouteActionsArePublic());
    }
}
