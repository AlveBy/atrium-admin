<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Fields\Field;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\History\PublishingSchedule;
use Atrium\Admin\History\Trash;
use Atrium\Admin\Hooks\Refused;
use Atrium\Admin\Http\Controllers\AdminController;
use Atrium\Admin\Models\Activity;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Revision;
use Atrium\Admin\Models\ScheduledRecord;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Models\TrashedRecord;
use Atrium\Admin\Support\RecordDeleter;
use Atrium\Admin\Tests\Fixtures\Journal;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Poster;
use Atrium\Admin\Tests\Fixtures\Unscheduled;
use Atrium\Admin\Tests\Fixtures\Untracked;
use Atrium\Admin\Tests\TestCase;
use Atrium\Admin\Transfer\RecordWriter;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use ReflectionClass;
use ReflectionMethod;

/**
 * Scheduled publishing: "Publish at" and "Unpublish at" next to the
 * "Active" field of every content module, kept in admin_schedules and
 * applied every minute by atrium:publish through the form's own save.
 */
final class PublishingScheduleTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Post::class, Poster::class, Unscheduled::class, Untracked::class, Journal::class]);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Sanctum::class))->getFileName(), 2).'/database/migrations');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function schedule(): PublishingSchedule
    {
        return app(PublishingSchedule::class);
    }

    /**
     * @param  class-string  $model
     * @return array<int,string>
     */
    private function names(string $model): array
    {
        return array_map(static fn (Field $field): string => $field->getName(), AdminController::fieldsOf($model));
    }

    /**
     * @param  class-string  $model
     */
    private function field(string $model, string $name): Field
    {
        foreach (AdminController::fieldsOf($model) as $field) {
            if ($field->getName() === $name) {
                return $field;
            }
        }

        $this->fail("No field {$name}.");
    }

    private function plan(Post $post, ?string $publish, ?string $unpublish, ?AdminUser $by = null): ScheduledRecord
    {
        ScheduledRecord::query()->create([
            'module' => 'posts', 'record_id' => $post->id, 'publish_at' => $publish, 'unpublish_at' => $unpublish, 'admin_user_id' => $by?->id,
        ]);

        return ScheduledRecord::query()->where('record_id', $post->id)->sole();
    }

    public function test_the_times_come_right_after_the_active_field_on_its_tab(): void
    {
        $this->assertSame(['name', 'active', 'publish_at', 'unpublish_at', 'sort'], $this->names(Post::class));
        $this->assertSame(['name', 'active', 'publish_at', 'unpublish_at'], $this->names(Poster::class));

        $active = $this->field(Poster::class, 'active');
        $publish = $this->field(Poster::class, 'publish_at');
        $unpublish = $this->field(Poster::class, 'unpublish_at');

        $this->assertSame(['datetime', 'Publish at', 'The record switches on by itself at this time.', 'publishing'], [$publish->getType(), $publish->getLabel(), $publish->toArray()['hint'], $publish->getTab()]);
        $this->assertSame(['datetime', 'Unpublish at', 'The record switches off by itself at this time.', 'publishing'], [$unpublish->getType(), $unpublish->getLabel(), $unpublish->toArray()['hint'], $unpublish->getTab()]);
        $this->assertSame([$active->getTabOrder(), $active->getTabOrder()], [$publish->getTabOrder(), $unpublish->getTabOrder()]);
        $this->assertSame(['nullable', 'date'], $publish->getValidationRules());
        $this->assertSame(['nullable', 'date'], $unpublish->getValidationRules());
    }

    public function test_a_module_that_opts_out_or_has_no_active_field_has_no_times(): void
    {
        $this->assertSame(['name', 'active'], $this->names(Unscheduled::class));
        $this->assertSame(['name'], $this->names(Untracked::class));
        $this->assertTrue($this->schedule()->enabledFor(Post::class));
        $this->assertFalse($this->schedule()->enabledFor(Unscheduled::class));
        // A service module is not published: no schedule by default.
        $this->assertFalse($this->schedule()->enabledFor(Journal::class));
        // Part of the model's API, as a module overrides it (a model would
        // answer through __callStatic otherwise).
        $this->assertTrue((new ReflectionMethod(Post::class, 'usesAdminSchedule'))->isPublic());
    }

    public function test_the_form_plans_shows_and_cancels_the_times(): void
    {
        $admin = $this->admin();
        Carbon::setTestNow('2026-12-01 09:00:00');

        $this->actingAs($admin, 'admin')->post('/admin/posts', ['name' => 'Apple', 'active' => false, 'sort' => 1, 'publish_at' => '2026-12-01 10:00:00', 'unpublish_at' => ''])->assertRedirect();

        $post = Post::query()->sole();
        $entry = ScheduledRecord::query()->sole();
        $this->assertSame(['posts', $post->id, null, $admin->id, '2026-12-01 10:00:00', null], [
            $entry->module, $entry->record_id, $entry->site_id, $entry->admin_user_id, $entry->publish_at->format('Y-m-d H:i:s'), $entry->unpublish_at,
        ]);
        $this->assertFalse($post->active);

        $item = $this->get("/admin/posts/{$post->id}/edit")->viewData('page')['props']['item'];
        $this->assertSame(['2026-12-01T10:00:00.000000Z', null], [$item['publish_at'], $item['unpublish_at']]);
        $this->assertSame(['publish_at' => ['old' => null, 'new' => '2026-12-01T10:00:00.000000Z']], Activity::query()->latest('id')->value('changes') ? array_intersect_key(Activity::query()->latest('id')->value('changes'), ['publish_at' => 1]) : []);

        $this->put("/admin/posts/{$post->id}", ['name' => 'Apple', 'active' => false, 'sort' => 1, 'publish_at' => '', 'unpublish_at' => null])->assertRedirect();

        $this->assertSame(0, ScheduledRecord::query()->count());
        $this->assertSame(['old' => '2026-12-01T10:00:00.000000Z', 'new' => null], Activity::query()->latest('id')->value('changes')['publish_at']);
    }

    public function test_the_same_times_saved_again_stay_planned_by_who_planned_them(): void
    {
        $first = $this->admin();
        $post = Post::query()->create(['name' => 'Apple']);
        Carbon::setTestNow('2026-12-01 09:00:00');
        $this->actingAs($first, 'admin')->put("/admin/posts/{$post->id}", ['name' => 'Apple', 'publish_at' => '2026-12-02 10:00:00', 'unpublish_at' => '2026-12-03 10:00:00'])->assertRedirect();

        $second = AdminUser::query()->create(['name' => 'Second', 'email' => 'second@example.com', 'password' => 'secret-password', 'is_active' => true]);
        $second->forceFill(['is_superadmin' => true])->save();
        Carbon::setTestNow('2026-12-01 09:30:00');
        $this->actingAs($second, 'admin')->put("/admin/posts/{$post->id}", ['name' => 'Pear', 'publish_at' => '2026-12-02T10:00:00.000000Z', 'unpublish_at' => '2026-12-03 10:00:00'])->assertRedirect();

        $entry = ScheduledRecord::query()->sole();
        $this->assertSame([$first->id, '2026-12-01 09:00:00'], [$entry->admin_user_id, $entry->updated_at->format('Y-m-d H:i:s')]);

        $this->put("/admin/posts/{$post->id}", ['name' => 'Pear', 'publish_at' => '2026-12-02 11:00:00', 'unpublish_at' => '2026-12-03 10:00:00'])->assertRedirect();
        $this->assertSame([$second->id, '2026-12-02 11:00:00'], [ScheduledRecord::query()->sole()->admin_user_id, ScheduledRecord::query()->sole()->publish_at->format('Y-m-d H:i:s')]);
    }

    public function test_the_end_must_come_after_the_start_and_both_must_be_times(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->post('/admin/posts', ['name' => 'Apple', 'publish_at' => '2026-12-02 10:00:00', 'unpublish_at' => '2026-12-02 10:00:00'])
            ->assertSessionHasErrors(['unpublish_at' => 'The Unpublish at field must be a date after Publish at.']);
        $this->post('/admin/posts', ['name' => 'Apple', 'publish_at' => '2026-12-02 10:00:00', 'unpublish_at' => '2026-12-02 09:59:59'])->assertSessionHasErrors('unpublish_at');
        $this->post('/admin/posts', ['name' => 'Apple', 'publish_at' => 'soon'])->assertSessionHasErrors('publish_at');
        $this->assertSame([0, 0], [Post::query()->count(), ScheduledRecord::query()->count()]);

        // The end alone is fine: the record is on until then.
        $this->post('/admin/posts', ['name' => 'Apple', 'unpublish_at' => '2026-12-02 10:00:00'])->assertSessionHasNoErrors();
        $this->assertNull(ScheduledRecord::query()->sole()->publish_at);
    }

    public function test_a_form_without_the_times_neither_shows_nor_takes_them(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $record = Unscheduled::query()->create(['name' => 'Apple']);

        $this->assertArrayNotHasKey('publish_at', $this->get("/admin/unscheduleds/{$record->id}/edit")->viewData('page')['props']['item']);
        $this->put("/admin/unscheduleds/{$record->id}", ['name' => 'Apple', 'publish_at' => '2026-12-02 10:00:00'])->assertRedirect();

        $this->assertSame(0, ScheduledRecord::query()->count());

        // A plan left from before the module opted out is not shown.
        ScheduledRecord::query()->create(['module' => 'unscheduleds', 'record_id' => $record->id, 'publish_at' => '2026-12-02 10:00:00']);
        $this->assertSame([], $this->get('/admin/unscheduleds')->viewData('page')['props']['scheduled']);

        // Times that are no fields of the form stay where they are.
        $data = ['name' => 'Apple', 'publish_at' => '2026-12-02 10:00:00'];
        $this->assertSame([], $this->schedule()->extract([TextField::make('name')], $data));
        $this->assertSame(['name' => 'Apple', 'publish_at' => '2026-12-02 10:00:00'], $data);
    }

    public function test_a_record_with_nothing_planned_shows_empty_times(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $post = Post::query()->create(['name' => 'Apple']);

        $item = $this->get("/admin/posts/{$post->id}/edit")->viewData('page')['props']['item'];

        $this->assertSame([null, null], [$item['publish_at'], $item['unpublish_at']]);
    }

    public function test_the_list_marks_the_planned_records_of_its_page(): void
    {
        $this->actingAs($this->admin(), 'admin');
        [$apple, $pear, $plum] = array_map(static fn (string $name): Post => Post::query()->create(['name' => $name]), ['Apple', 'Pear', 'Plum']);
        $this->plan($apple, '2026-12-02 10:00:00', null);
        $this->plan($plum, null, '2026-12-03 10:00:00');
        ScheduledRecord::query()->create(['module' => 'posters', 'record_id' => $pear->id, 'publish_at' => '2026-12-02 10:00:00']);

        $this->assertSame([
            $apple->id => ['publish_at' => '2026-12-02T10:00:00+00:00', 'unpublish_at' => null],
            $plum->id => ['publish_at' => null, 'unpublish_at' => '2026-12-03T10:00:00+00:00'],
        ], $this->get('/admin/posts')->viewData('page')['props']['scheduled']);

        // Only the records of the page.
        $this->assertSame([$plum->id], array_keys($this->get('/admin/posts?search=Plum')->viewData('page')['props']['scheduled']));
    }

    public function test_the_scheduler_switches_a_record_on_at_its_time_as_who_planned_it(): void
    {
        $admin = $this->admin();
        $post = Post::query()->create(['name' => 'Apple', 'active' => false]);
        $this->plan($post, '2026-12-01 10:00:00', null, $admin);

        Carbon::setTestNow('2026-12-01 09:59:59');
        $this->artisan('atrium:publish')->expectsOutput('0 switched.')->assertSuccessful();
        $this->assertFalse($post->fresh()->active);
        $this->assertSame(1, ScheduledRecord::query()->count());

        Carbon::setTestNow('2026-12-01 10:00:00');
        $this->artisan('atrium:publish')->expectsOutput('1 switched.')->doesntExpectOutputToContain('Not switched')->assertSuccessful();

        $this->assertTrue($post->fresh()->active);
        $this->assertSame(0, ScheduledRecord::query()->count());
        $entry = Activity::query()->latest('id')->firstOrFail();
        $this->assertSame(['updated', 'schedule', $admin->id, 'Admin', ['active' => ['old' => false, 'new' => true]]], [
            $entry->event, $entry->source, $entry->admin_user_id, $entry->admin_name, $entry->changes,
        ]);
        $this->assertSame($entry->id, Revision::query()->latest('id')->value('activity_id'));
    }

    public function test_the_scheduler_switches_a_record_on_and_keeps_the_end_still_to_come(): void
    {
        $post = Post::query()->create(['name' => 'Apple', 'active' => false]);
        $this->plan($post, '2026-12-01 10:00:00', '2026-12-05 10:00:00');

        $this->assertSame(['switched' => 1, 'refused' => []], $this->schedule()->run(Carbon::parse('2026-12-01 10:00:00')));

        $this->assertTrue($post->fresh()->active);
        $entry = ScheduledRecord::query()->sole();
        $this->assertSame([null, '2026-12-05 10:00:00'], [$entry->publish_at, $entry->unpublish_at->format('Y-m-d H:i:s')]);
    }

    public function test_the_scheduler_switches_a_record_off_at_its_end(): void
    {
        $post = Post::query()->create(['name' => 'Apple', 'active' => true]);
        $this->plan($post, null, '2026-12-01 10:00:00');

        $this->assertSame(['switched' => 0, 'refused' => []], $this->schedule()->run(Carbon::parse('2026-12-01 09:59:59')));
        $this->assertSame(['switched' => 1, 'refused' => []], $this->schedule()->run(Carbon::parse('2026-12-01 10:00:00')));

        $this->assertFalse($post->fresh()->active);
        $this->assertSame(0, ScheduledRecord::query()->count());
        // The planner is gone: the scheduler alone.
        $this->assertSame(['schedule', null], [Activity::query()->latest('id')->value('source'), Activity::query()->latest('id')->value('admin_user_id')]);
    }

    public function test_when_both_times_have_come_the_end_wins(): void
    {
        $post = Post::query()->create(['name' => 'Apple', 'active' => true]);
        $this->plan($post, '2026-12-01 09:00:00', '2026-12-01 10:00:00');

        $this->assertSame(['switched' => 1, 'refused' => []], $this->schedule()->run(Carbon::parse('2026-12-01 10:00:00')));

        $this->assertFalse($post->fresh()->active);
        $this->assertSame(0, ScheduledRecord::query()->count());
    }

    public function test_a_record_in_the_trash_waits_and_one_deleted_for_good_takes_its_plan_along(): void
    {
        $waiting = Post::query()->create(['name' => 'Waiting', 'active' => false]);
        $gone = Post::query()->create(['name' => 'Gone', 'active' => false]);
        $this->plan($waiting, '2026-12-01 10:00:00', null);
        $this->plan($gone, '2026-12-01 10:00:00', null);
        $this->assertTrue(app(RecordDeleter::class)->delete($waiting));
        DB::table('posts')->where('id', $gone->id)->delete();
        ScheduledRecord::query()->create(['module' => 'nowhere', 'record_id' => 1, 'publish_at' => '2026-12-01 10:00:00']);

        $this->assertSame(['switched' => 0, 'refused' => []], $this->schedule()->run(Carbon::parse('2026-12-01 10:00:00')));

        $this->assertSame([$waiting->id], ScheduledRecord::query()->pluck('record_id')->all());

        $entry = TrashedRecord::query()->sole();
        app(Trash::class)->restore($entry, app(Trash::class)->record($entry));
        $this->assertSame(['switched' => 1, 'refused' => []], $this->schedule()->run(Carbon::parse('2026-12-01 10:01:00')));
        $this->assertTrue($waiting->fresh()->active);
    }

    public function test_a_record_deleted_for_good_forgets_its_plan_one_put_in_the_trash_keeps_it(): void
    {
        $kept = Post::query()->create(['name' => 'Kept']);
        $gone = Post::query()->create(['name' => 'Gone']);
        $this->plan($kept, '2026-12-01 10:00:00', null);
        $this->plan($gone, '2026-12-01 10:00:00', null);
        ScheduledRecord::query()->create(['module' => 'posters', 'record_id' => $gone->id, 'publish_at' => '2026-12-01 10:00:00']);

        app(RecordDeleter::class)->delete($kept);
        app(RecordDeleter::class)->delete($gone, forever: true);

        $this->assertSame([['posts', $kept->id], ['posters', $gone->id]], ScheduledRecord::query()->orderBy('id')->get()->map(static fn (ScheduledRecord $entry): array => [$entry->module, $entry->record_id])->all());
    }

    public function test_a_switch_the_module_refuses_is_reported_once_and_not_tried_again(): void
    {
        $post = Post::query()->create(['name' => 'Apple', 'active' => false]);
        $this->plan($post, '2026-12-01 10:00:00', '2026-12-05 10:00:00');
        Hook::filter('atrium.record.data', static fn () => throw new Refused('Not before the editor reads it.'));
        Carbon::setTestNow('2026-12-01 10:00:00');

        $this->artisan('atrium:publish')
            ->expectsOutput('0 switched.')
            ->expectsOutput("Not switched, posts #{$post->id}: Not before the editor reads it.")
            ->assertSuccessful();

        $this->assertFalse($post->fresh()->active);
        $entry = ScheduledRecord::query()->sole();
        $this->assertSame([null, '2026-12-05 10:00:00'], [$entry->publish_at, $entry->unpublish_at->format('Y-m-d H:i:s')]);
        $this->assertSame(['switched' => 0, 'refused' => []], $this->schedule()->run(now()));
    }

    public function test_a_refused_switch_with_nothing_left_to_come_goes(): void
    {
        $post = Post::query()->create(['name' => 'Apple', 'active' => false]);
        $this->plan($post, '2026-12-01 10:00:00', null);
        Hook::filter('atrium.record.data', static fn () => throw new Refused('No.'));

        $this->assertSame(['switched' => 0, 'refused' => ["posts #{$post->id}: No."]], $this->schedule()->run(Carbon::parse('2026-12-01 10:00:00')));

        $this->assertSame(0, ScheduledRecord::query()->count());
    }

    public function test_the_scheduler_runs_it_every_minute_once_at_a_time(): void
    {
        $events = array_values(array_filter(
            app(Schedule::class)->events(),
            static fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'atrium:publish'),
        ));

        $this->assertCount(1, $events);
        $this->assertSame('* * * * *', $events[0]->expression);
        $this->assertTrue($events[0]->withoutOverlapping);
    }

    public function test_a_record_of_another_site_is_planned_on_its_site_and_switched_from_the_console(): void
    {
        $other = Site::query()->create(['name' => 'Other', 'code' => 'other', 'domain' => 'other.test', 'is_active' => true]);
        $poster = Poster::query()->create(['name' => 'Fair', 'code' => 'fair', 'active' => false, 'site_id' => $other->id]);

        (new RecordWriter(Poster::class))->write(['publish_at' => '2026-12-01 10:00:00'], $poster);

        $entry = ScheduledRecord::query()->sole();
        $this->assertSame(['posters', $other->id], [$entry->module, $entry->site_id]);

        $this->assertSame(['switched' => 1, 'refused' => []], $this->schedule()->run(Carbon::parse('2026-12-01 10:00:00')));
        $this->assertTrue($poster->fresh()->active);
    }

    public function test_an_import_without_the_times_leaves_the_plan_alone(): void
    {
        $post = Post::query()->create(['name' => 'Apple']);
        $this->plan($post, '2026-12-01 10:00:00', null);

        (new RecordWriter(Post::class))->write(['name' => 'Pear'], $post);
        (new RecordWriter(Post::class))->write(['unpublish_at' => '2026-12-02 10:00:00'], $post);

        $entry = ScheduledRecord::query()->sole();
        $this->assertSame(['2026-12-01 10:00:00', '2026-12-02 10:00:00'], [$entry->publish_at->format('Y-m-d H:i:s'), $entry->unpublish_at->format('Y-m-d H:i:s')]);

        // Empty cells of a file cancel the plan.
        (new RecordWriter(Post::class))->write(['publish_at' => '', 'unpublish_at' => ''], $post);
        $this->assertSame(0, ScheduledRecord::query()->count());
    }

    public function test_the_api_plans_and_shows_the_times(): void
    {
        $editor = AdminUser::query()->create(['name' => 'Editor', 'email' => 'editor@example.com', 'password' => 'secret-password', 'is_active' => true]);
        $editor->roles()->attach(AdminRole::query()->create(['name' => 'Editors', 'permissions' => ['posts.view', 'posts.create', 'posts.update']]));
        $token = $editor->createToken('writer', ['posts:read', 'posts:write'])->plainTextToken;

        $id = $this->withToken($token)->postJson('/api/v1/posts', ['name' => 'Apple', 'active' => false, 'publish_at' => '2026-12-01T10:00:00Z'])
            ->assertCreated()
            ->assertJsonPath('data.publish_at', '2026-12-01T10:00:00.000000Z')
            ->json('data.id');

        $this->patchJson("/api/v1/posts/{$id}", ['unpublish_at' => '2026-11-30 10:00:00'])->assertUnprocessable()->assertJsonValidationErrors('unpublish_at');
        $this->patchJson("/api/v1/posts/{$id}", ['publish_at' => null])->assertOk()->assertJsonPath('data.publish_at', null);

        $this->assertSame(0, ScheduledRecord::query()->count());
        $this->assertSame('api', Activity::query()->latest('id')->value('source'));
    }
}
