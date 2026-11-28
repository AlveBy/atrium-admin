<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Auth\Permissions;
use Atrium\Admin\Builder\BlockTypes;
use Atrium\Admin\Builder\Layout;
use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Fields\LayoutField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\History\Revisions;
use Atrium\Admin\Http\Controllers\BuilderController;
use Atrium\Admin\Models\Activity;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\Revision;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Tests\Fixtures\Billboard;
use Atrium\Admin\Tests\Fixtures\Builder\NoteBlock;
use Atrium\Admin\Tests\Fixtures\Landing;
use Atrium\Admin\Tests\Fixtures\PanelAccount;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * The page editor of a layout field: the screen, the preview, the draft,
 * publishing and the earlier versions.
 */
final class PageEditorTest extends TestCase
{
    private const FRAME = '<meta http-equiv="Content-Security-Policy" content="script-src \'none\'"><base href="http://localhost/">';

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Billboard::class, Landing::class, Post::class]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Billboard::reset();
        Hook::register(BlockTypes::REGISTRY, 'note', new NoteBlock);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Model::preventLazyLoading(false);
        Billboard::reset();

        parent::tearDown();
    }

    /**
     * A page of one note saying $title.
     *
     * @return array<string,mixed>
     */
    private static function tree(string $title = 'Hi'): array
    {
        return ['version' => 1, 'sections' => [['id' => 's1', 'columns' => [['id' => 'c1', 'blocks' => [
            ['id' => 'b1', 'type' => 'note', 'settings' => ['title' => ['en' => $title]]],
        ]]]]]];
    }

    /**
     * A sound tree whose note still lacks its required title.
     *
     * @return array<string,mixed>
     */
    private static function unfinished(): array
    {
        return ['version' => 1, 'sections' => [['id' => 's1', 'columns' => [['id' => 'c1', 'blocks' => [
            ['id' => 'b1', 'type' => 'note', 'settings' => []],
        ]]]]]];
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    private function billboard(array $attributes = []): Billboard
    {
        return Billboard::query()->create(['name' => 'Summer', 'active' => true, 'layout' => self::tree('One'), ...$attributes]);
    }

    private function url(Model $record, string $path = '', string $field = 'layout', string $module = 'billboards'): string
    {
        return "/admin/{$module}/{$record->getKey()}/builder/{$field}{$path}";
    }

    private function signedIn(): self
    {
        return $this->actingAs($this->admin(), 'admin');
    }

    private function editor(array $permissions): AdminUser
    {
        $editor = AdminUser::query()->create(['name' => 'Editor', 'email' => 'editor@example.com', 'password' => 'secret-password', 'is_active' => true]);
        $editor->roles()->attach(AdminRole::query()->create(['name' => 'Editors', 'permissions' => $permissions]));

        return $editor->fresh();
    }

    private function draftOf(Model $record, string $field = 'layout'): ?Revision
    {
        return app(Revisions::class)->draft($record, $field);
    }

    /**
     * Saves $tree as the draft in place of the one the record has.
     *
     * @param  array<string,mixed>|null  $tree
     * @param  array<string,mixed>  $more
     */
    private function saveDraft(Billboard $billboard, ?array $tree, array $more = []): TestResponse
    {
        return $this->putJson($this->url($billboard, '/draft'), [
            'draft' => $this->draftOf($billboard)?->id,
            'tree' => $tree,
            'base' => Layout::fingerprint($billboard->fresh()->layout),
            ...$more,
        ]);
    }

    /**
     * @param  array<string,mixed>|null  $tree
     * @param  array<string,mixed>  $more
     */
    private function publish(Billboard $billboard, ?array $tree, array $more = []): TestResponse
    {
        return $this->postJson($this->url($billboard, '/publish'), [
            'draft' => $this->draftOf($billboard)?->id,
            'tree' => $tree,
            'base' => Layout::fingerprint($billboard->fresh()->layout),
            ...$more,
        ]);
    }

    /**
     * Every request of the editor, with what it needs to be taken.
     *
     * @return array<string,array{0:string,1:string,2:array<string,mixed>}>
     */
    public static function steps(): array
    {
        $base = str_repeat('a', 64);

        return [
            'the screen' => ['get', '', []],
            'the preview' => ['postJson', '/preview', ['tree' => null, 'language' => 'en']],
            'saving a draft' => ['putJson', '/draft', ['draft' => null, 'tree' => null, 'base' => $base]],
            'discarding' => ['deleteJson', '/draft', ['draft' => null]],
            'publishing' => ['postJson', '/publish', ['draft' => null, 'tree' => null, 'base' => $base, 'force' => true]],
            'a version' => ['getJson', '/versions/1', []],
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function step(string $method, string $url, array $data): TestResponse
    {
        return str_starts_with($method, 'get') ? $this->{$method}($url) : $this->{$method}($url, $data);
    }

    /**
     * Keeps a version of the record and leads the step's path to it: the
     * database picks its number.
     */
    private function aimed(Billboard $billboard, string $path): string
    {
        $kept = Revision::query()->create(['module' => 'billboards', 'record_id' => $billboard->id, 'values' => ['layout' => null]]);

        return str_replace('/versions/1', "/versions/{$kept->id}", $path);
    }

    // ---- who may open it ----

    /**
     * @param  array<string,mixed>  $data
     */
    #[DataProvider('steps')]
    public function test_the_editor_opens_only_on_a_layout_field_the_form_lets_change(string $method, string $path, array $data): void
    {
        $this->signedIn();
        $billboard = $this->billboard();
        $path = $this->aimed($billboard, $path);

        foreach (['unheard', 'name', 'aside', 'frozen', 'tucked'] as $field) {
            $this->step($method, $this->url($billboard, $path, $field), $data)->assertNotFound();
        }

        $this->assertSame(self::tree('One'), $billboard->fresh()->layout);
        $this->assertSame(1, Revision::query()->count());
    }

    /**
     * @param  array<string,mixed>  $data
     */
    #[DataProvider('steps')]
    public function test_a_record_that_is_not_there_has_no_editor(string $method, string $path, array $data): void
    {
        $this->signedIn();
        $this->billboard();

        $this->step($method, "/admin/billboards/999/builder/layout{$path}", $data)->assertNotFound();
        $this->assertSame(0, Revision::query()->count());
    }

    /**
     * @param  array<string,mixed>  $data
     */
    #[DataProvider('steps')]
    public function test_a_module_that_is_only_read_has_no_editor(string $method, string $path, array $data): void
    {
        $this->signedIn();
        $billboard = $this->billboard();
        $path = $this->aimed($billboard, $path);
        Billboard::$editable = false;

        $this->step($method, $this->url($billboard, $path), $data)->assertForbidden();
        $this->assertSame(1, Revision::query()->count());
    }

    /**
     * @param  array<string,mixed>  $data
     */
    #[DataProvider('steps')]
    public function test_every_step_needs_the_right_to_change_the_record(string $method, string $path, array $data): void
    {
        $billboard = $this->billboard();
        $path = $this->aimed($billboard, $path);
        $editor = $this->editor(['billboards.view', 'billboards.create', 'billboards.delete']);

        $this->actingAs($editor, 'admin');
        $this->step($method, $this->url($billboard, $path), $data)->assertForbidden();
        $this->assertSame(self::tree('One'), $billboard->fresh()->layout);
        $this->assertSame(1, Revision::query()->count());

        AdminRole::query()->sole()->update(['permissions' => ['billboards.view', 'billboards.update']]);
        $this->actingAs($editor->fresh(), 'admin');
        $this->step($method, $this->url($billboard, $path), $data)->assertOk();
    }

    public function test_only_the_editor_s_own_routes_need_the_right_to_change(): void
    {
        $permissions = app(Permissions::class);
        $viewer = $this->editor(['billboards.view']);

        foreach (['builder', 'builder.preview', 'builder.draft', 'builder.discard', 'builder.publish', 'builder.version'] as $route) {
            $this->assertFalse($permissions->allowsRoute($viewer, "admin.billboards.{$route}"), $route);
        }

        foreach (['admin.nothing.builder', 'admin.zzz.admin.billboards.builder', 'admin.billboards.builder.draft.more'] as $route) {
            $this->assertTrue($permissions->allowsRoute($viewer, $route), $route);
        }
    }

    public function test_the_edit_form_links_to_the_editor_of_each_layout_field_it_lets_change(): void
    {
        $billboard = $this->billboard();
        $edit = "/admin/billboards/{$billboard->id}/edit";
        $links = ['layout' => route('admin.billboards.builder', ['id' => $billboard->id, 'field' => 'layout'])];
        $offers = fn (AdminUser $user, array $expected) => $this->actingAs($user, 'admin')->get($edit)->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('builders', $expected));

        $offers($this->admin(), $links);

        $editor = $this->editor(['billboards.view']);
        $offers($editor, []);

        AdminRole::query()->sole()->update(['permissions' => ['billboards.view', 'billboards.update']]);
        $offers($editor->fresh(), $links);

        Billboard::$editable = false;
        $offers($editor->fresh(), []);
    }

    public function test_a_module_without_the_editor_s_routes_gets_no_links(): void
    {
        $fields = [TextField::make('name'), LayoutField::make('layout'), LayoutField::make('more')];

        $this->assertSame([], BuilderController::urlsFor('admin.nothing', 5, $fields));
        $this->assertSame([
            'layout' => route('admin.billboards.builder', ['id' => 5, 'field' => 'layout']),
            'more' => route('admin.billboards.builder', ['id' => 5, 'field' => 'more']),
        ], BuilderController::urlsFor('admin.billboards', 5, $fields));
    }

    // ---- the screen ----

    public function test_the_editor_opens_a_record_with_what_is_published(): void
    {
        $billboard = $this->billboard();
        $url = fn (string $path): string => url($this->url($billboard, $path));

        $this->signedIn()->get($this->url($billboard))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Builder/Editor', false)
            ->where('title', 'Summer')
            ->where('adminLabel', 'Billboards')
            ->where('routeName', 'admin.billboards')
            ->where('recordId', $billboard->id)
            ->where('field.name', 'layout')
            ->where('field.label', 'Blocks')
            ->where('field.type', 'layout')
            ->where('field.types.0.type', 'note')
            ->where('tree', self::tree('One'))
            ->where('published', self::tree('One'))
            ->where('base', Layout::fingerprint(self::tree('One')))
            ->where('draft', null)
            ->where('versions', [])
            ->where('hidden', false)
            ->where('urls', [
                'edit' => route('admin.billboards.edit', $billboard->id),
                'preview' => $url('/preview'),
                'draft' => $url('/draft'),
                'discard' => $url('/draft'),
                'publish' => $url('/publish'),
            ]));
    }

    public function test_the_editor_says_when_the_record_is_switched_off(): void
    {
        $billboard = $this->billboard(['active' => false]);

        $this->signedIn()->get($this->url($billboard))->assertInertia(fn (AssertableInertia $page) => $page->where('hidden', true));
    }

    public function test_a_module_that_cannot_draw_a_page_has_an_editor_without_a_preview(): void
    {
        $landing = Landing::query()->create(['name' => 'Camp', 'layout' => self::tree()]);

        $this->signedIn()->get($this->url($landing, module: 'landings'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('urls.preview', null)
            // Nothing says a landing can be switched off.
            ->where('hidden', false));

        $this->postJson($this->url($landing, '/preview', module: 'landings'), ['tree' => null, 'language' => 'en'])->assertNotFound();
    }

    public function test_the_editor_opens_the_draft_when_there_is_one(): void
    {
        Carbon::setTestNow('2026-11-28 09:30:00');
        $billboard = $this->billboard();
        $this->signedIn()->saveDraft($billboard, self::tree('Two'))->assertOk();
        $draft = $this->draftOf($billboard);

        $this->get($this->url($billboard))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tree', self::tree('Two'))
            ->where('published', self::tree('One'))
            ->where('base', Layout::fingerprint(self::tree('One')))
            ->where('draft', ['id' => $draft->id, 'at' => '2026-11-28T09:30:00+00:00', 'author' => 'Admin', 'stale' => false]));

        // Published elsewhere since: the draft keeps what it started from.
        $billboard->update(['layout' => self::tree('Other')]);

        $this->get($this->url($billboard))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tree', self::tree('Two'))
            ->where('published', self::tree('Other'))
            ->where('base', Layout::fingerprint(self::tree('One')))
            ->where('draft.stale', true));
    }

    public function test_a_draft_of_an_emptied_page_opens_empty(): void
    {
        $billboard = $this->billboard();
        $this->signedIn()->saveDraft($billboard, null)->assertOk();
        DB::table('admin_revisions')->update(['created_at' => null]);

        $this->get($this->url($billboard))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tree', null)
            ->where('published', self::tree('One'))
            ->where('draft.at', null));
    }

    // ---- the preview ----

    public function test_the_preview_draws_the_record_with_the_tree_sent_and_saves_nothing(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        app(ContentLanguages::class)->forgetActive();
        $billboard = $this->billboard();
        $seen = [];
        Billboard::$draw = function (Billboard $drawn, string $language) use (&$seen): string {
            $seen = [$language, app()->getLocale(), $drawn->name, $drawn->layout];

            return '<html><head></head><body>drawn</body></html>';
        };

        $response = $this->signedIn()->postJson($this->url($billboard, '/preview'), ['tree' => self::tree('Unsaved'), 'language' => 'de'])->assertOk();

        $this->assertSame('<html><head>'.self::FRAME.'</head><body>drawn</body></html>', $response->getContent());
        $this->assertStringStartsWith('text/html', (string) $response->headers->get('Content-Type'));
        $this->assertSame(['de', 'de', 'Summer', self::tree('Unsaved')], $seen);
        $this->assertSame('en', app()->getLocale());
        $this->assertSame(self::tree('One'), $billboard->fresh()->layout);
        $this->assertSame(0, Revision::query()->count());
        $this->assertSame(0, Activity::query()->count());
    }

    public function test_the_preview_of_an_emptied_page_is_drawn_without_a_tree(): void
    {
        $billboard = $this->billboard();

        $this->signedIn()->postJson($this->url($billboard, '/preview'), ['tree' => null, 'language' => 'en'])
            ->assertOk()
            ->assertSee('<body>null</body>', false);
    }

    public function test_the_language_is_put_back_when_the_page_cannot_be_drawn(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        app(ContentLanguages::class)->forgetActive();
        $billboard = $this->billboard();
        Billboard::$draw = static fn (): string => throw new RuntimeException('The theme broke.');

        $this->signedIn()->postJson($this->url($billboard, '/preview'), ['tree' => null, 'language' => 'de'])->assertServerError();

        $this->assertSame('en', app()->getLocale());
    }

    /**
     * @return array<string,array{0:string,1:string}>
     */
    public static function drawnPages(): array
    {
        return [
            'right after the opening tag of the head' => [
                '<html><HEAD data-x="1"><title>T</title></HEAD><body><head></body></html>',
                '<html><HEAD data-x="1">'.self::FRAME.'<title>T</title></HEAD><body><head></body></html>',
            ],
            'in front of a piece without a head' => [
                '<header>Top</header>',
                self::FRAME.'<header>Top</header>',
            ],
        ];
    }

    #[DataProvider('drawnPages')]
    public function test_the_page_is_framed_so_that_no_script_of_it_runs(string $drawn, string $framed): void
    {
        $billboard = $this->billboard();
        Billboard::$draw = static fn (): string => $drawn;

        $response = $this->signedIn()->postJson($this->url($billboard, '/preview'), ['tree' => null, 'language' => 'en'])->assertOk();

        $this->assertSame($framed, $response->getContent());
    }

    public function test_the_address_of_the_site_is_escaped_in_the_frame(): void
    {
        $billboard = $this->billboard();
        Billboard::$draw = static function (): string {
            URL::forceRootUrl('http://example.test/a&b');

            return '';
        };

        $this->signedIn()->postJson($this->url($billboard, '/preview'), ['tree' => null, 'language' => 'en'])
            ->assertOk()
            ->assertSee('<base href="http://example.test/a&amp;b/">', false);
    }

    /**
     * @return array<string,array{0:array<string,mixed>,1:string}>
     */
    public static function refusedPreviews(): array
    {
        return [
            'no tree' => [['language' => 'en'], 'tree'],
            'a tree that is text' => [['tree' => 'x', 'language' => 'en'], 'tree'],
            'no language' => [['tree' => null], 'language'],
            'a language the site does not have' => [['tree' => null, 'language' => 'fr'], 'language'],
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     */
    #[DataProvider('refusedPreviews')]
    public function test_a_preview_needs_a_tree_and_a_language_of_the_site(array $data, string $error): void
    {
        $billboard = $this->billboard();

        $this->signedIn()->postJson($this->url($billboard, '/preview'), $data)->assertUnprocessable()->assertJsonValidationErrors([$error]);
    }

    // ---- drafts ----

    public function test_a_draft_waits_beside_the_record(): void
    {
        Carbon::setTestNow('2026-11-28 09:30:00');
        $billboard = $this->billboard();

        $response = $this->signedIn()->saveDraft($billboard, self::tree('Two'))->assertOk();
        $draft = Revision::query()->sole();

        $response->assertExactJson(['draft' => ['id' => $draft->id, 'at' => '2026-11-28T09:30:00+00:00', 'author' => 'Admin', 'stale' => false]]);
        $this->assertSame(['billboards', $billboard->id, 'layout', 'Admin', Layout::fingerprint(self::tree('One'))], [$draft->module, $draft->record_id, $draft->draft, $draft->author, $draft->base]);
        $this->assertSame(['layout'], array_keys($draft->values));
        $this->assertSame(Layout::fingerprint(self::tree('Two')), Layout::fingerprint($draft->values['layout']));
        $this->assertNull($draft->activity_id);
        $this->assertSame(self::tree('One'), $billboard->fresh()->layout);
        $this->assertSame(0, Activity::query()->count());
    }

    public function test_the_next_draft_takes_the_place_of_the_one_before(): void
    {
        $billboard = $this->billboard();
        $this->signedIn()->saveDraft($billboard, self::tree('Two'))->assertOk();
        $first = $this->draftOf($billboard)->id;

        // A form posts numbers as text.
        $this->saveDraft($billboard, self::tree('Three'), ['draft' => (string) $first])->assertOk();

        $draft = Revision::query()->sole();
        $this->assertNotSame($first, $draft->id);
        $this->assertSame('Three', $draft->values['layout']['sections'][0]['columns'][0]['blocks'][0]['settings']['title']['en']);
    }

    public function test_a_draft_may_hold_blocks_that_are_not_filled_in_yet(): void
    {
        $billboard = $this->billboard();
        $unknown = self::unfinished();
        $unknown['sections'][0]['columns'][0]['blocks'][] = ['id' => 'b2', 'type' => 'later', 'settings' => ['anything' => 1]];

        $this->signedIn()->saveDraft($billboard, $unknown)->assertOk();

        $this->assertCount(2, $this->draftOf($billboard)->values['layout']['sections'][0]['columns'][0]['blocks']);
    }

    public function test_a_draft_of_a_broken_tree_is_refused_with_the_node_it_is_broken_in(): void
    {
        $billboard = $this->billboard();
        $broken = self::tree('Two');
        $broken['sections'][0]['settings'] = ['height' => 1];

        $this->signedIn()->saveDraft($billboard, $broken)
            ->assertStatus(422)
            ->assertExactJson(['message' => 'Section 1: There is no setting “height”.', 'node' => 's1']);

        $this->assertSame(0, Revision::query()->count());
    }

    public function test_a_draft_made_of_an_old_one_is_refused(): void
    {
        $billboard = $this->billboard();
        $this->signedIn()->saveDraft($billboard, self::tree('Theirs'))->assertOk();
        $theirs = $this->draftOf($billboard);

        foreach ([null, $theirs->id + 1] as $seen) {
            $this->saveDraft($billboard, self::tree('Mine'), ['draft' => $seen])
                ->assertStatus(409)
                ->assertExactJson([
                    'conflict' => 'draft',
                    'message' => 'Someone saved or discarded the draft of this page after you opened it.',
                    'draft' => ['id' => $theirs->id, 'at' => $theirs->created_at->toIso8601String(), 'author' => 'Admin', 'stale' => false],
                ]);
        }

        $this->assertSame($theirs->id, Revision::query()->sole()->id);

        $this->saveDraft($billboard, self::tree('Mine'), ['draft' => null, 'force' => true])->assertOk();
        $this->assertSame('Mine', Revision::query()->sole()->values['layout']['sections'][0]['columns'][0]['blocks'][0]['settings']['title']['en']);
    }

    public function test_a_draft_that_was_discarded_is_not_saved_over_unasked(): void
    {
        $billboard = $this->billboard();

        $this->signedIn()->saveDraft($billboard, self::tree('Mine'), ['draft' => 41])
            ->assertStatus(409)
            ->assertJson(['conflict' => 'draft', 'draft' => null]);
        $this->saveDraft($billboard, self::tree('Mine'), ['draft' => 41, 'force' => false])->assertStatus(409);

        $this->assertSame(0, Revision::query()->count());
    }

    /**
     * @return array<string,array{0:array<string,mixed>,1:array<int,string>,2:string}>
     */
    public static function refusedSteps(): array
    {
        $whole = ['draft' => null, 'tree' => null, 'base' => str_repeat('a', 64)];
        $without = static fn (string $key): array => array_diff_key($whole, [$key => 0]);

        return [
            'no draft said' => [$without('draft'), [], 'draft'],
            'a draft that is a word' => [['draft' => 'last'] + $whole, [], 'draft'],
            'force that is a word' => [['force' => 'please'] + $whole, [], 'force'],
            'no tree' => [$without('tree'), ['tree'], 'tree'],
            'a tree that is text' => [['tree' => 'x'] + $whole, ['tree'], 'tree'],
            'no base' => [$without('base'), ['base'], 'base'],
            'a base that is a list' => [['base' => array_fill(0, 64, 'a')] + $whole, ['base'], 'base'],
            'a base too short' => [['base' => str_repeat('a', 63)] + $whole, ['base'], 'base'],
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     * @param  array<int,string>  $needsTree  empty when discarding refuses it too
     */
    #[DataProvider('refusedSteps')]
    public function test_a_step_says_what_it_has_seen(array $data, array $needsTree, string $error): void
    {
        $billboard = $this->billboard();
        $this->signedIn();

        $this->putJson($this->url($billboard, '/draft'), $data)->assertUnprocessable()->assertJsonValidationErrors([$error]);
        $this->postJson($this->url($billboard, '/publish'), $data)->assertUnprocessable()->assertJsonValidationErrors([$error]);

        // Discarding sends no tree.
        $discarded = $this->deleteJson($this->url($billboard, '/draft'), $data);
        $needsTree === [] ? $discarded->assertUnprocessable()->assertJsonValidationErrors([$error]) : $discarded->assertOk();

        $this->assertSame(self::tree('One'), $billboard->fresh()->layout);
        $this->assertSame(0, Revision::query()->count());
    }

    // ---- discarding ----

    public function test_discarding_goes_back_to_what_is_published(): void
    {
        $billboard = $this->billboard();
        $this->signedIn()->saveDraft($billboard, self::tree('Two'))->assertOk();

        $response = $this->deleteJson($this->url($billboard, '/draft'), ['draft' => $this->draftOf($billboard)->id])->assertOk();

        $this->assertSame(['tree', 'published', 'base', 'draft', 'versions'], array_keys($response->json()));
        $this->assertSame(Layout::fingerprint(self::tree('One')), Layout::fingerprint($response->json('tree')));
        $this->assertSame($response->json('tree'), $response->json('published'));
        $this->assertSame(Layout::fingerprint(self::tree('One')), $response->json('base'));
        $this->assertNull($response->json('draft'));
        $this->assertSame([], $response->json('versions'));
        $this->assertSame(0, Revision::query()->count());
        $this->assertSame(self::tree('One'), $billboard->fresh()->layout);
    }

    public function test_a_draft_saved_by_someone_else_is_not_discarded_unasked(): void
    {
        $billboard = $this->billboard();
        $this->signedIn()->saveDraft($billboard, self::tree('Theirs'))->assertOk();
        $theirs = $this->draftOf($billboard)->id;

        $this->deleteJson($this->url($billboard, '/draft'), ['draft' => null])
            ->assertStatus(409)
            ->assertJson(['conflict' => 'draft', 'draft' => ['id' => $theirs]]);
        $this->assertSame(1, Revision::query()->count());

        $this->deleteJson($this->url($billboard, '/draft'), ['draft' => null, 'force' => true])->assertOk();
        $this->assertSame(0, Revision::query()->count());
    }

    // ---- publishing ----

    public function test_publishing_saves_the_tree_as_the_form_does(): void
    {
        $billboard = $this->billboard();
        $this->signedIn()->saveDraft($billboard, self::tree('Two'))->assertOk();

        $response = $this->publish($billboard, self::tree('Three'))->assertOk();

        $stored = $billboard->fresh();
        $this->assertSame(Layout::fingerprint(self::tree('Three')), Layout::fingerprint($stored->layout));
        $this->assertSame(['Summer', true], [$stored->name, $stored->active]);
        $this->assertNull($this->draftOf($billboard));
        $this->assertSame(Layout::fingerprint($stored->layout), $response->json('base'));
        $this->assertSame(Layout::fingerprint($stored->layout), Layout::fingerprint($response->json('tree')));
        $this->assertSame($response->json('tree'), $response->json('published'));
        $this->assertNull($response->json('draft'));

        $entry = Activity::query()->sole();
        $this->assertSame(['billboards', 'updated', 'Admin', ['layout']], [$entry->module, $entry->event, $entry->admin_name, array_keys($entry->changes)]);

        $versions = app(Revisions::class)->of($stored)->pluck('id')->all();
        $this->assertCount(2, $versions);
        $this->assertSame($versions, array_column($response->json('versions'), 'id'));
    }

    public function test_a_page_can_be_published_empty_when_the_form_allows_it(): void
    {
        $billboard = $this->billboard();

        $this->signedIn()->publish($billboard, null)->assertOk()->assertJson(['tree' => null, 'published' => null]);

        $this->assertNull($billboard->fresh()->layout);
    }

    public function test_publishing_asks_what_the_form_asks(): void
    {
        Billboard::$layoutRequired = true;
        $billboard = $this->billboard();

        $this->signedIn()->publish($billboard, null)->assertUnprocessable()->assertJsonValidationErrors(['layout']);

        $this->assertSame(self::tree('One'), $billboard->fresh()->layout);
    }

    public function test_an_unfinished_block_is_not_published_and_the_draft_stays(): void
    {
        $billboard = $this->billboard();
        $this->signedIn()->saveDraft($billboard, self::unfinished())->assertOk();

        $this->publish($billboard, self::unfinished())
            ->assertStatus(422)
            ->assertExactJson(['message' => 'Section 1, column 1, block 1 (Note): The Title field is required.', 'node' => 'b1']);

        $this->assertSame(self::tree('One'), $billboard->fresh()->layout);
        $this->assertNotNull($this->draftOf($billboard));
        $this->assertSame(0, Activity::query()->count());
    }

    public function test_a_block_the_site_already_shows_does_not_keep_the_page_from_being_published(): void
    {
        // Its module is gone, but it is as it was.
        $old = self::tree('One');
        $old['sections'][0]['columns'][0]['blocks'][] = ['id' => 'b9', 'type' => 'gone', 'settings' => ['sum' => 5]];
        $billboard = $this->billboard(['layout' => $old]);
        $changed = $old;
        $changed['sections'][0]['columns'][0]['blocks'][0]['settings']['title']['en'] = 'Two';

        $this->signedIn()->publish($billboard, $changed)->assertOk();

        $this->assertSame('Two', $billboard->fresh()->layout['sections'][0]['columns'][0]['blocks'][0]['settings']['title']['en']);
    }

    public function test_publishing_over_someone_else_s_draft_is_asked_about(): void
    {
        $billboard = $this->billboard();
        $this->signedIn()->saveDraft($billboard, self::tree('Theirs'))->assertOk();

        $this->publish($billboard, self::tree('Mine'), ['draft' => null])
            ->assertStatus(409)
            ->assertJson(['conflict' => 'draft', 'draft' => ['id' => $this->draftOf($billboard)->id]]);
        $this->assertSame(self::tree('One'), $billboard->fresh()->layout);

        $this->publish($billboard, self::tree('Mine'), ['draft' => null, 'force' => true])->assertOk();
        $this->assertSame('Mine', $billboard->fresh()->layout['sections'][0]['columns'][0]['blocks'][0]['settings']['title']['en']);
        $this->assertNull($this->draftOf($billboard));
    }

    public function test_publishing_over_a_change_made_elsewhere_is_asked_about(): void
    {
        $billboard = $this->billboard();
        $base = Layout::fingerprint(self::tree('One'));
        $billboard->update(['layout' => self::tree('Elsewhere')]);

        $this->signedIn()->publish($billboard, self::tree('Mine'), ['base' => $base])
            ->assertStatus(409)
            ->assertExactJson([
                'conflict' => 'published',
                'message' => 'The published page changed after this editing started.',
                'draft' => null,
            ]);
        $this->assertSame(self::tree('Elsewhere'), $billboard->fresh()->layout);

        $this->publish($billboard, self::tree('Mine'), ['base' => $base, 'force' => true])->assertOk();
        $this->assertSame('Mine', $billboard->fresh()->layout['sections'][0]['columns'][0]['blocks'][0]['settings']['title']['en']);
    }

    public function test_the_keys_of_the_stored_tree_may_come_in_any_order(): void
    {
        $billboard = $this->billboard();
        $base = Layout::fingerprint(['sections' => [['columns' => [['blocks' => [
            ['settings' => ['title' => ['en' => 'One']], 'type' => 'note', 'id' => 'b1'],
        ], 'id' => 'c1']], 'id' => 's1']], 'version' => 1]);

        $this->signedIn()->publish($billboard, self::tree('Two'), ['base' => $base])->assertOk();
    }

    public function test_the_record_is_locked_while_a_step_changes_it(): void
    {
        $billboard = $this->billboard();
        $this->signedIn();
        $first = function (callable $request): bool|string|null {
            Billboard::$locks = [];
            $request()->assertOk();

            return Billboard::$locks[0];
        };

        $this->assertTrue($first(fn (): TestResponse => $this->saveDraftUnlocked($billboard)));
        $this->assertTrue($first(fn (): TestResponse => $this->deleteJson($this->url($billboard, '/draft'), ['draft' => null, 'force' => true])));
        $this->assertTrue($first(fn (): TestResponse => $this->postJson($this->url($billboard, '/publish'), ['draft' => null, 'tree' => null, 'base' => str_repeat('a', 64), 'force' => true])));
        // Reading takes no lock.
        $this->assertNull($first(fn (): TestResponse => $this->get($this->url($billboard))));
        $this->assertNull($first(fn (): TestResponse => $this->postJson($this->url($billboard, '/preview'), ['tree' => null, 'language' => 'en'])));
    }

    /** A draft saved without reading the record first, which would count among its locks. */
    private function saveDraftUnlocked(Billboard $billboard): TestResponse
    {
        return $this->putJson($this->url($billboard, '/draft'), ['draft' => null, 'tree' => null, 'base' => str_repeat('a', 64)]);
    }

    public function test_an_account_of_another_kind_leaves_a_draft_without_a_name(): void
    {
        $billboard = $this->billboard();
        $this->admin();
        config(['auth.providers.admin_users.model' => PanelAccount::class]);
        $this->app['auth']->forgetGuards();

        $this->actingAs(PanelAccount::query()->firstOrFail(), 'admin')->saveDraft($billboard, self::tree('Two'))
            ->assertOk()
            ->assertJsonPath('draft.author', null);
    }

    // ---- earlier versions ----

    public function test_the_versions_listed_are_those_in_which_the_page_changed(): void
    {
        $billboard = $this->billboard();
        $this->signedIn();
        Carbon::setTestNow('2026-11-28 10:00:00');
        $this->publish($billboard, self::tree('Two'))->assertOk();
        // A save of the form that leaves the page alone.
        Carbon::setTestNow('2026-11-28 11:00:00');
        $this->put("/admin/billboards/{$billboard->id}", ['name' => 'Winter', 'active' => true])->assertSessionHasNoErrors();
        Carbon::setTestNow('2026-11-28 12:00:00');
        // A version of other fields only, as an import of a file could leave.
        Revision::query()->create(['module' => 'billboards', 'record_id' => $billboard->id, 'values' => ['name' => 'Only the name']]);
        Carbon::setTestNow('2026-11-28 13:00:00');
        $response = $this->publish($billboard, self::tree('One'))->assertOk();

        [$back, , , $two, $before] = app(Revisions::class)->of($billboard)->pluck('id')->all();
        $entry = static fn (int $id, ?string $at, bool $current): array => [
            'id' => $id, 'at' => $at, 'admin' => $at === null ? null : 'Admin', 'source' => $at === null ? null : 'admin',
            'url' => route('admin.billboards.builder.version', ['id' => $billboard->id, 'field' => 'layout', 'revision' => $id]),
            'current' => $current,
        ];
        $expected = [
            $entry($back, '2026-11-28T13:00:00+00:00', true),
            $entry($two, '2026-11-28T10:00:00+00:00', false),
            // The page has come back to this one, which is not marked again.
            $entry($before, null, false),
        ];

        $this->assertSame($expected, $response->json('versions'));
        Model::preventLazyLoading();
        $this->get($this->url($billboard))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('versions', $expected));
    }

    public function test_no_version_is_marked_when_the_page_was_changed_past_the_history(): void
    {
        $billboard = $this->billboard();
        $this->signedIn()->publish($billboard, self::tree('Two'))->assertOk();
        $billboard->update(['layout' => self::tree('Three')]);

        $this->get($this->url($billboard))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('versions', 2)
            ->where('versions.0.current', false)
            ->where('versions.1.current', false));
    }

    public function test_a_version_opens_with_its_tree(): void
    {
        $billboard = $this->billboard();
        $this->signedIn()->publish($billboard, self::tree('Two'))->assertOk();
        [$two, $one] = app(Revisions::class)->of($billboard)->pluck('id')->all();

        $response = $this->getJson($this->url($billboard, "/versions/{$one}"))->assertOk();

        $this->assertSame(['tree'], array_keys($response->json()));
        $this->assertSame(Layout::fingerprint(self::tree('One')), Layout::fingerprint($response->json('tree')));
        $this->assertSame(Layout::fingerprint(self::tree('Two')), Layout::fingerprint($billboard->fresh()->layout), 'Opening changes nothing.');
        $this->assertNotSame($two, $one);
    }

    public function test_a_version_of_an_empty_page_opens_empty(): void
    {
        $billboard = $this->billboard();
        $version = Revision::query()->create(['module' => 'billboards', 'record_id' => $billboard->id, 'values' => ['layout' => null]]);

        $this->signedIn()->getJson($this->url($billboard, "/versions/{$version->id}"))->assertOk()->assertExactJson(['tree' => null]);
    }

    public function test_only_a_version_of_this_record_that_holds_the_page_opens(): void
    {
        $billboard = $this->billboard();
        $other = $this->billboard(['name' => 'Other']);
        $this->signedIn()->saveDraft($billboard, self::tree('Draft'))->assertOk();
        $row = static fn (string $module, int $record, array $values): int => Revision::query()->create(['module' => $module, 'record_id' => $record, 'values' => $values])->id;

        foreach ([
            'of another record' => $row('billboards', $other->id, ['layout' => null]),
            'of another module' => $row('landings', $billboard->id, ['layout' => null]),
            'without the page' => $row('billboards', $billboard->id, ['name' => 'Only the name']),
            'a draft' => $this->draftOf($billboard)->id,
            'unheard of' => 999,
        ] as $case => $id) {
            $this->assertSame(404, $this->getJson($this->url($billboard, "/versions/{$id}"))->status(), $case);
        }
    }

    public function test_a_module_that_keeps_no_versions_has_none_in_the_editor(): void
    {
        $billboard = $this->billboard();
        $version = Revision::query()->create(['module' => 'billboards', 'record_id' => $billboard->id, 'values' => ['layout' => null]]);
        config(['atrium.history.revisions' => 0]);
        $this->signedIn();

        $this->get($this->url($billboard))->assertInertia(fn (AssertableInertia $page) => $page->where('versions', []));
        $this->getJson($this->url($billboard, "/versions/{$version->id}"))->assertNotFound();
        // A draft needs no history.
        $this->saveDraft($billboard, self::tree('Two'))->assertOk();
        $this->publish($billboard, self::tree('Two'))->assertOk()->assertJsonPath('versions', []);
    }

    // ---- the drafts among the versions ----

    public function test_a_draft_is_one_per_field_of_a_record_and_no_version(): void
    {
        $revisions = app(Revisions::class);
        $billboard = $this->billboard();
        $other = $this->billboard(['name' => 'Other']);
        $landing = Landing::query()->create(['name' => 'Camp']);
        $this->assertSame($billboard->id, $landing->id);

        $revisions->keepDraft($billboard, 'layout', ['a' => 1], 'base-1', 'Ann');
        $revisions->keepDraft($billboard, 'aside', ['a' => 2], 'base-2', null);
        $revisions->keepDraft($other, 'layout', ['a' => 3], 'base-3', 'Bob');
        $revisions->keepDraft($landing, 'layout', ['a' => 4], 'base-4', 'Cleo');

        $this->assertSame(['layout' => ['a' => 1]], $revisions->draft($billboard, 'layout')->values);
        $this->assertSame(['aside' => ['a' => 2]], $revisions->draft($billboard, 'aside')->values);
        $this->assertSame(['layout' => ['a' => 3]], $revisions->draft($other, 'layout')->values);
        $this->assertSame(['layout' => ['a' => 4]], $revisions->draft($landing, 'layout')->values);
        $this->assertNull($revisions->draft($billboard, 'unheard'));
        $this->assertSame(0, $revisions->of($billboard)->count());

        $revisions->keepDraft($billboard, 'layout', ['a' => 5], 'base-5', 'Dan');
        $this->assertSame(4, Revision::query()->count());
        $this->assertSame(['base-5', 'Dan'], [$revisions->draft($billboard, 'layout')->base, $revisions->draft($billboard, 'layout')->author]);

        $revisions->dropDraft($billboard, 'layout');
        $this->assertNull($revisions->draft($billboard, 'layout'));
        $this->assertSame(3, Revision::query()->count());
    }

    public function test_a_draft_is_not_counted_with_the_versions_and_is_not_in_the_history(): void
    {
        config(['atrium.history.revisions' => 2]);
        $billboard = $this->billboard();
        $this->signedIn();
        app(Revisions::class)->keepDraft($billboard, 'aside', ['a' => 1], 'base', 'Ann');

        foreach (['Two', 'Three', 'Four'] as $title) {
            $this->publish($billboard, self::tree($title))->assertOk();
        }

        $this->assertSame(2, app(Revisions::class)->of($billboard)->count());
        $this->assertNotNull($this->draftOf($billboard, 'aside'));
        $this->get("/admin/billboards/{$billboard->id}/history")->assertInertia(fn (AssertableInertia $page) => $page->has('versions', 2));
    }

    public function test_a_record_deleted_for_good_takes_its_drafts_along(): void
    {
        $billboard = $this->billboard();
        $other = $this->billboard(['name' => 'Other']);
        $this->signedIn()->saveDraft($billboard, self::tree('Two'))->assertOk();
        $this->saveDraft($other, self::tree('Two'))->assertOk();

        $this->delete("/admin/billboards/{$billboard->id}")->assertSessionHasNoErrors();

        $this->assertSame([$other->id], Revision::query()->pluck('record_id')->all());
    }

    public function test_undoing_the_migration_removes_the_drafts_and_keeps_the_versions(): void
    {
        $migration = require dirname(__DIR__, 2).'/database/migrations/2026_11_28_000002_add_drafts_to_admin_revisions_table.php';
        $billboard = $this->billboard();
        $version = Revision::query()->create(['module' => 'billboards', 'record_id' => $billboard->id, 'values' => ['layout' => null]]);
        app(Revisions::class)->keepDraft($billboard, 'layout', null, 'base', 'Ann');

        $migration->down();

        $this->assertSame([$version->id], DB::table('admin_revisions')->pluck('id')->all());
        $this->assertFalse(Schema::hasColumn('admin_revisions', 'draft'));
        $this->assertFalse(Schema::hasColumn('admin_revisions', 'author'));
        $this->assertFalse(Schema::hasColumn('admin_revisions', 'base'));

        $migration->up();

        $this->assertTrue(Schema::hasColumns('admin_revisions', ['draft', 'author', 'base']));
        $this->assertSame([$version->id], app(Revisions::class)->of($billboard)->pluck('id')->all());
    }

    public function test_the_router_calls_the_editor_from_outside(): void
    {
        $actions = $this->assertRouteActionsArePublic();

        foreach (['show', 'preview', 'draft', 'discard', 'publish', 'version'] as $action) {
            $this->assertArrayHasKey(BuilderController::class.'::'.$action, $actions);
        }
    }
}
