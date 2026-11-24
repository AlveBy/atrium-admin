<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Extensions\Event;
use Atrium\Admin\Extensions\Events;
use Atrium\Admin\Extensions\Occurrence;
use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Hooks\Hooks;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\SeoMeta;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/**
 * The event catalogue: the record events of every module, the events
 * modules declare, and the occurrences rules, webhooks and apps hear.
 */
final class EventCatalogueTest extends TestCase
{
    /** @var list<Occurrence> */
    private array $heard = [];

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Declared the way a module does, before the catalogue starts listening.
        $app->resolving(Hooks::class, static function (Hooks $hooks): void {
            $hooks->register('atrium.events', 'acme.post.published', Event::make('acme.post.published')
                ->label('Post published')
                ->subject(Post::class)
                ->payload(static fn (Post $post, string $channel): array => ['name' => $post->name, 'channel' => $channel]));
        });
    }

    private function listenToOccurrences(): void
    {
        Hook::on('atrium.event.occurred', function (Occurrence $occurrence): void {
            $this->heard[] = $occurrence;
        });
    }

    public function test_every_module_has_its_record_events_before_the_declared_ones(): void
    {
        $events = $this->app->make(Events::class)->all();

        $this->assertSame('Posts: a record was created', $events['posts.created']->labelText());
        $this->assertSame('Posts: a record was changed', $events['posts.updated']->labelText());
        $this->assertSame('Posts: a record was deleted', $events['posts.deleted']->labelText());
        $this->assertSame('atrium.record.saved', $events['posts.created']->hook());
        $this->assertSame('atrium.record.saved', $events['posts.updated']->hook());
        $this->assertSame('atrium.record.deleted', $events['posts.deleted']->hook());
        $this->assertSame(Post::class, $events['posts.created']->subjectModel());
        $this->assertSame(Post::class, $events['posts.deleted']->subjectModel());
        $this->assertSame(Language::class, $events['languages.updated']->subjectModel());
        $this->assertSame('acme.post.published', array_key_last($events));
        $this->assertSame('Post published', $events['acme.post.published']->labelText());
    }

    public function test_record_events_follow_the_language_of_the_panel(): void
    {
        $this->app->setLocale('ru');

        $this->assertSame('Posts: запись создана', $this->app->make(Events::class)->all()['posts.created']->labelText());
    }

    public function test_saving_and_deleting_in_the_panel_are_heard_as_one_record_event_each(): void
    {
        $this->listenToOccurrences();
        $admin = $this->admin();

        $heard = fn (): array => array_map(static fn (Occurrence $o): string => $o->event, $this->heard);

        $this->actingAs($admin, 'admin')->post('/admin/posts', ['name' => 'First', 'active' => true, 'sort' => 10]);
        $this->assertSame(['posts.created'], $heard());
        $post = Post::query()->sole();
        $this->put("/admin/posts/{$post->id}", ['name' => 'Renamed', 'active' => true, 'sort' => 10]);
        $this->assertSame(['posts.created', 'posts.updated'], $heard());
        $this->delete("/admin/posts/{$post->id}");
        $this->assertSame(['posts.created', 'posts.updated', 'posts.deleted'], $heard());
        $this->assertSame(['type' => 'user', 'id' => $admin->getKey()], $this->heard[0]->toArray()['actor']);

        foreach ($this->heard as $occurrence) {
            $this->assertInstanceOf(Post::class, $occurrence->subject);
            $this->assertSame($post->id, $occurrence->subject->getKey());
            $this->assertSame('posts', $occurrence->module);
            $this->assertSame([], $occurrence->data);
            $this->assertTrue($admin->is($occurrence->actor));
            $this->assertTrue(Str::isUuid($occurrence->id));
        }

        $this->assertCount(3, array_unique(array_map(static fn (Occurrence $o): string => $o->id, $this->heard)));
    }

    public function test_a_declared_event_carries_its_payload_and_no_actor_outside_the_panel(): void
    {
        $this->listenToOccurrences();
        $post = Post::query()->create(['name' => 'News']);
        CarbonImmutable::setTestNow('2026-11-25 10:15:00');

        Hook::fire('acme.post.published', $post, 'telegram');

        $this->assertCount(1, $this->heard);
        $this->assertSame([
            'id' => $this->heard[0]->id,
            'event' => 'acme.post.published',
            'occurred_at' => '2026-11-25T10:15:00+00:00',
            'site' => null,
            'actor' => null,
            'subject' => ['module' => 'posts', 'id' => $post->id],
            'data' => ['name' => 'News', 'channel' => 'telegram'],
        ], $this->heard[0]->toArray());
    }

    public function test_a_hook_with_another_record_or_none_is_not_the_event(): void
    {
        $this->listenToOccurrences();

        Hook::fire('acme.post.published', new Language, 'telegram');
        Hook::fire('acme.post.published');

        $this->assertSame([], $this->heard);
    }

    public function test_an_event_without_a_subject_and_an_event_with_a_condition(): void
    {
        Hook::register('atrium.events', 'acme.import.finished', Event::make('acme.import.done')->payload(static fn (int $rows): array => ['rows' => $rows]));
        Hook::register('atrium.events', 'acme.import.large', Event::make('acme.import.done')->when(static fn (int $rows): bool => $rows > 100));
        $this->app->make(Events::class)->listen();
        $this->listenToOccurrences();

        Hook::fire('acme.import.done', 5);
        Hook::fire('acme.import.done', 500);

        $this->assertSame(['acme.import.finished', 'acme.import.finished', 'acme.import.large'], array_map(static fn (Occurrence $o): string => $o->event, $this->heard));
        $this->assertNull($this->heard[0]->subject);
        $this->assertNull($this->heard[0]->module);
        $this->assertNull($this->heard[0]->toArray()['subject']);
        $this->assertSame(['rows' => 5], $this->heard[0]->data);
        $this->assertSame([], $this->heard[2]->data);
        $this->assertSame('acme.import.done', $this->app->make(Events::class)->all()['acme.import.large']->labelText());
    }

    public function test_listening_again_hears_each_hook_once(): void
    {
        Hook::register('atrium.events', 'acme.ping', Event::make('acme.ping'));
        $events = $this->app->make(Events::class);
        $events->listen();
        $events->listen();
        $this->listenToOccurrences();

        Hook::fire('acme.ping');
        Hook::fire('acme.post.published', Post::query()->create(['name' => 'Once']), 'mail');

        $this->assertSame(['acme.ping', 'acme.post.published'], array_map(static fn (Occurrence $o): string => $o->event, $this->heard));
    }

    public function test_an_event_declared_after_boot_is_not_heard_until_the_catalogue_listens_again(): void
    {
        Hook::register('atrium.events', 'acme.late', Event::make('acme.late'));
        $this->listenToOccurrences();

        Hook::fire('acme.late');
        $this->assertSame([], $this->heard);

        $this->app->make(Events::class)->listen();
        Hook::fire('acme.late');
        $this->assertCount(1, $this->heard);
    }

    public function test_nothing_is_built_while_nobody_listens(): void
    {
        $built = 0;
        Hook::register('atrium.events', 'acme.counted', Event::make('acme.counted')->payload(static function () use (&$built): array {
            $built++;

            return [];
        }));
        $this->app->make(Events::class)->listen();

        Hook::fire('acme.counted');
        $this->assertSame(0, $built);

        $this->listenToOccurrences();
        Hook::fire('acme.counted');
        $this->assertSame(1, $built);
    }

    public function test_a_payload_that_is_not_an_array_is_no_data_and_a_condition_must_be_true(): void
    {
        Hook::register('atrium.events', 'acme.odd', Event::make('acme.odd')->payload(static fn (): string => 'text'));
        Hook::register('atrium.events', 'acme.truthy', Event::make('acme.odd')->when(static fn (): int => 1));
        $this->app->make(Events::class)->listen();
        $this->listenToOccurrences();

        Hook::fire('acme.odd');

        $this->assertSame(['acme.odd'], array_map(static fn (Occurrence $o): string => $o->event, $this->heard));
        $this->assertSame([], $this->heard[0]->data);
    }

    public function test_what_is_not_an_event_is_left_out_of_the_catalogue(): void
    {
        Hook::register('atrium.events', 'acme.broken', 'not an event');

        $this->assertArrayNotHasKey('acme.broken', $this->app->make(Events::class)->all());
        $this->app->make(Events::class)->listen();
    }

    public function test_a_label_is_a_translation_key_or_the_hook(): void
    {
        $this->assertSame(':module: a record was created', Event::make('acme.x')->label('atrium::events.created')->labelText());
        $this->assertSame('acme.x', Event::make('acme.x')->labelText());
    }

    public function test_the_site_is_the_subjects_own(): void
    {
        $at = CarbonImmutable::parse('2026-11-25 10:15:00');
        $onSite = (new Post)->forceFill(['id' => 4, 'site_id' => '3']);

        $this->assertSame(3, (new Occurrence('id', 'posts.created', $onSite, 'posts', [], null, $at))->site());
        $this->assertSame(3, (new Occurrence('id', 'posts.created', $onSite, 'posts', [], null, $at))->toArray()['site']);
        $this->assertNull((new Occurrence('id', 'posts.created', new Post, 'posts', [], null, $at))->site());
        $this->assertNull((new Occurrence('id', 'acme.ping', null, null, [], null, $at))->site());
    }

    public function test_the_command_lists_the_catalogue(): void
    {
        Hook::register('atrium.events', 'acme.ping', Event::make('acme.ping')->label('Ping'));
        Hook::register('atrium.events', 'acme.seo.checked', Event::make('acme.seo.checked')->label('SEO checked')->subject(SeoMeta::class));

        $this->assertSame(0, Artisan::call('atrium:events'));
        $output = Artisan::output();

        $this->assertMatchesRegularExpression('/\| posts\.created +\| Posts: a record was created +\| atrium\.record\.saved +\| posts +\|/', $output);
        $this->assertMatchesRegularExpression('/\| posts\.deleted +\| Posts: a record was deleted +\| atrium\.record\.deleted +\| posts +\|/', $output);
        $this->assertMatchesRegularExpression('/\| acme\.post\.published +\| Post published +\| acme\.post\.published +\| posts +\|/', $output);
        $this->assertMatchesRegularExpression('/\| acme\.ping +\| Ping +\| acme\.ping +\| +\|/', $output);
        $this->assertStringContainsString('| '.SeoMeta::class.' |', $output);
        $this->assertMatchesRegularExpression('/\| Key +\| Label +\| Hook +\| Subject +\|/', $output);
    }
}
