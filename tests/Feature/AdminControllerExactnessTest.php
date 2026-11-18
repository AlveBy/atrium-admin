<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Fields\ImageField;
use Atrium\Admin\Fields\NumberField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Http\Controllers\AdminController;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Tests\Fixtures\Article;
use Atrium\Admin\Tests\Fixtures\Brochure;
use Atrium\Admin\Tests\Fixtures\Bulletin;
use Atrium\Admin\Tests\Fixtures\Bundle;
use Atrium\Admin\Tests\Fixtures\Chapter;
use Atrium\Admin\Tests\Fixtures\Dossier;
use Atrium\Admin\Tests\Fixtures\Memo;
use Atrium\Admin\Tests\Fixtures\Note;
use Atrium\Admin\Tests\Fixtures\Page;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Topic;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Testing\AssertableInertia;
use LogicException;

/**
 * The generic module controller, one condition at a time: what the list
 * loads and searches, how the create form is preselected, who may create and
 * edit, and what a save keeps.
 */
final class AdminControllerExactnessTest extends TestCase
{
    private ?AdminUser $admin = null;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.locale', 'en');
        $app['config']->set('atrium.resources', [
            Post::class, Bulletin::class, Bundle::class, Chapter::class, Dossier::class, Memo::class, Page::class, Topic::class,
        ]);
    }

    private function panel(): static
    {
        return $this->actingAs($this->admin ??= $this->admin(), 'admin');
    }

    /**
     * The controller of a fixture with its protected methods open.
     *
     * @param  class-string  $model
     */
    private function controller(string $model): object
    {
        return new class($model) extends AdminController
        {
            public function __construct(string $model)
            {
                $this->model = $model;
            }

            public function call(string $method, mixed ...$arguments): mixed
            {
                return $this->{$method}(...$arguments);
            }

            /**
             * @param  array<string,mixed>  $data
             */
            public function pull(string $method, array &$data): mixed
            {
                return $this->{$method}($data);
            }
        };
    }

    /**
     * @return list<string> the tables the SQL of $callback read, one entry per query
     */
    private function queriesTo(string $table, callable $callback): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        DB::disableQueryLog();

        return array_values(array_filter(
            array_column(DB::getQueryLog(), 'query'),
            static fn (string $sql): bool => preg_match('/from ["`]'.$table.'["`]/i', $sql) === 1,
        ));
    }

    public function test_a_column_of_a_related_record_is_loaded_for_the_whole_page_at_once(): void
    {
        $first = Page::query()->create(['name' => ['en' => 'First']]);
        $second = Page::query()->create(['name' => ['en' => 'Second']]);
        Topic::query()->create(['name' => 'A', 'page_id' => $first->id]);
        Topic::query()->create(['name' => 'B', 'page_id' => $second->id]);

        $reads = $this->queriesTo('pages', fn () => $this->panel()->get('/admin/topics')->assertOk());

        $this->assertCount(1, $reads);
    }

    public function test_a_list_without_related_columns_reads_no_other_table(): void
    {
        Post::query()->create(['name' => 'Apple']);

        $reads = $this->queriesTo('pages', fn () => $this->panel()->get('/admin/posts')->assertOk());

        $this->assertSame([], $reads);
    }

    public function test_search_looks_only_in_the_columns_that_say_they_are_searchable(): void
    {
        Post::query()->create(['name' => 'Apple', 'sort' => 7]);
        Post::query()->create(['name' => 'Pear', 'sort' => 8]);
        $asked = [];
        Hook::filter('atrium.module.columns', static fn (array $columns, string $model): array => $model === Post::class
            ? [
                ...$columns,
                self::column(['name' => 'sort']),
                self::column(['name' => null, 'searchable' => true]),
                self::column(['name' => '', 'searchable' => true]),
                self::column(['name' => 'sort', 'searchable' => false]),
            ]
            : $columns);
        Hook::filter('atrium.query.search', static function (bool $handled, $query, string $column) use (&$asked): bool {
            $asked[] = $column;

            return false;
        });

        $names = array_column(
            $this->panel()->get('/admin/posts?search=Apple')->assertOk()->viewData('page')['props']['items']['data'],
            'name',
        );

        $this->assertSame(['Apple'], $names);
        $this->assertSame(['name'], $asked);
    }

    /**
     * @param  array<string,mixed>  $array
     */
    private static function column(array $array): object
    {
        return new class($array)
        {
            /** @param array<string,mixed> $array */
            public function __construct(private readonly array $array) {}

            /** @return array<string,mixed> */
            public function toArray(): array
            {
                return $this->array;
            }
        };
    }

    public function test_a_search_that_matches_a_number_column_only_finds_nothing_when_it_is_not_searchable(): void
    {
        Post::query()->create(['name' => 'Apple', 'sort' => 777]);

        $names = array_column(
            $this->panel()->get('/admin/posts?search=777')->assertOk()->viewData('page')['props']['items']['data'],
            'name',
        );

        $this->assertSame([], $names);
    }

    public function test_the_create_form_preselects_the_section_it_was_opened_from(): void
    {
        $section = Post::query()->create(['name' => 'Section']);

        $this->panel()->get("/admin/chapters/create?section_id={$section->id}")->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('fields.2.name', 'section_id')
                ->where('fields.2.default', $section->id)
                ->where('fieldsByTab.params.2.default', $section->id));
    }

    public function test_the_create_form_ignores_a_section_that_is_not_there(): void
    {
        Post::query()->create(['name' => 'Section']);

        foreach (['', '?section_id=0', '?section_id=-1', '?section_id=999', '?section_id=abc'] as $query) {
            $reads = $this->queriesTo('posts', function () use ($query): void {
                $this->panel()->get('/admin/chapters/create'.$query)->assertOk()
                    ->assertInertia(fn (AssertableInertia $page) => $page->where('fields.2.default', null));
            });

            // Nothing can be asked about section 0 or a negative one.
            if (in_array($query, ['', '?section_id=0', '?section_id=-1', '?section_id=abc'], true)) {
                $this->assertSame([], $reads, $query);
            }
        }
    }

    public function test_a_module_without_sections_never_preselects_one(): void
    {
        Hook::filter('atrium.module.fields', static fn (array $fields, string $model): array => $model === Post::class
            ? [...$fields, NumberField::make('section_id')->label('Section')]
            : $fields);

        $this->panel()->get('/admin/posts/create?section_id=1')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('fields.3.name', 'section_id')->where('fields.3.default', null));
    }

    public function test_a_controller_whose_form_has_no_plain_field_list_still_gets_its_defaults(): void
    {
        $controller = new class(Bulletin::class) extends AdminController
        {
            public function __construct(string $model)
            {
                $this->model = $model;
            }

            protected function prepareFormData(string $context, $item = null): array
            {
                return ['fieldsByTab' => ['params' => [['name' => 'name'], ['name' => 'sort']]]];
            }
        };

        $request = Request::create('/admin/bulletins/create?name=Apple', 'GET');
        $request->headers->set('X-Inertia', 'true');
        $props = $controller->create($request)->toResponse($request)->getData(true)['props'];

        $this->assertSame([['name' => 'name', 'default' => 'Apple'], ['name' => 'sort']], $props['fieldsByTab']['params']);
        $this->assertArrayNotHasKey('fields', $props);
    }

    public function test_a_module_that_cannot_be_created_here_still_saves_edits_and_the_reverse(): void
    {
        $dossier = Dossier::query()->create(['name' => 'Old']);
        $memo = Memo::query()->create(['name' => 'Old']);

        $this->panel()->post('/admin/dossiers', ['name' => 'New'])->assertForbidden();
        $this->panel()->put("/admin/dossiers/{$dossier->id}", ['name' => 'Changed'])->assertRedirect('/admin/dossiers');
        $this->panel()->post('/admin/memos', ['name' => 'New'])->assertRedirect('/admin/memos');
        $this->panel()->put("/admin/memos/{$memo->id}", ['name' => 'Changed'])->assertForbidden();

        $this->assertSame('Changed', $dossier->fresh()->name);
        $this->assertSame('Old', $memo->fresh()->name);
        $this->assertSame(['Changed', 'Old', 'New'], Post::query()->orderBy('id')->pluck('name')->all());
    }

    public function test_an_empty_password_on_edit_keeps_the_stored_one(): void
    {
        $other = AdminUser::query()->create(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'secret-password', 'is_active' => true]);
        $hash = $other->password;

        $this->panel()->put("/admin/users/{$other->id}", ['name' => 'Renamed', 'email' => 'other@example.com', 'password' => ''])
            ->assertSessionHasNoErrors();

        $other->refresh();
        $this->assertSame('Renamed', $other->name);
        $this->assertSame($hash, $other->password);
    }

    public function test_a_save_reads_the_fields_of_the_module_once(): void
    {
        $calls = 0;
        Hook::filter('atrium.module.fields', static function (array $fields) use (&$calls): array {
            $calls++;

            return $fields;
        });

        $this->panel()->post('/admin/posts', ['name' => 'Apple'])->assertSessionHasNoErrors();

        $this->assertSame(1, $calls);
    }

    public function test_the_fields_of_a_form_are_a_list(): void
    {
        $controller = $this->controller(Chapter::class);

        $create = $controller->call('contextFields', 'create');

        $this->assertTrue(array_is_list($create));
        $this->assertCount(4, $create);
    }

    public function test_the_fields_shown_only_on_one_side_are_kept_for_that_side(): void
    {
        Hook::filter('atrium.module.fields', static fn (array $fields, string $model): array => $model === Post::class
            ? [NumberField::make('only_create')->onlyOnCreate(), ...$fields, NumberField::make('only_edit')->onlyOnEdit()]
            : $fields);
        $controller = $this->controller(Post::class);

        $this->assertSame(['only_create', 'name', 'active', 'sort'], array_map(static fn ($field) => $field->getName(), $controller->call('contextFields', 'create')));
        $this->assertSame(['name', 'active', 'sort', 'only_edit'], array_map(static fn ($field) => $field->getName(), $controller->call('contextFields', 'edit')));
    }

    public function test_unique_rules_are_rewritten_to_skip_the_record_being_edited(): void
    {
        $topic = Topic::query()->create(['name' => 'Mine']);
        $controller = $this->controller(Topic::class);
        $unique = static fn (string $column): string => (string) Rule::unique('topics', $column)->ignore($topic->getKey(), 'id');

        $rules = $controller->call('ignoreCurrentRecord', [
            'name' => 'required|unique:topics',
            'code' => ['nullable', 'unique:topics,NULL'],
            'alias' => 'unique:topics,null',
            'slug' => 'unique:topics,note',
            'scoped' => ['unique:topics,slug,0', 'unique:topics'],
            'plain' => 'required|max:5',
            'listed' => ['required', 'max:5'],
        ], $topic);

        $stringify = static fn (array $list): array => array_map(static fn ($rule): string => (string) $rule, $list);

        $this->assertSame(['required', $unique('name')], $stringify($rules['name']));
        $this->assertSame(['nullable', $unique('code')], $stringify($rules['code']));
        $this->assertSame([$unique('alias')], $stringify($rules['alias']));
        $this->assertSame([$unique('note')], $stringify($rules['slug']));
        $this->assertSame(['unique:topics,slug,0', $unique('scoped')], $stringify($rules['scoped']));
        $this->assertSame('required|max:5', $rules['plain']);
        $this->assertSame(['required', 'max:5'], $rules['listed']);
    }

    public function test_the_rules_of_a_record_that_is_not_there_yet_are_left_as_written(): void
    {
        $controller = $this->controller(Topic::class);
        $rules = ['name' => 'required|unique:topics', 'code' => ['unique:topics,slug']];

        $this->assertSame($rules, $controller->call('ignoreCurrentRecord', $rules, null));
        $this->assertSame($rules, $controller->call('ignoreCurrentRecord', $rules, new Topic));
    }

    public function test_a_unique_rule_looks_only_at_the_current_site_for_the_module_s_own_table(): void
    {
        $controller = $this->controller(Note::class);

        $own = (string) $controller->call('uniqueRule', 'notes', 'code', null);
        $foreign = (string) $controller->call('uniqueRule', 'pages', 'code', null);

        $this->assertStringContainsString('site_id', $own);
        $this->assertStringNotContainsString('site_id', $foreign);
        $this->assertStringNotContainsString('site_id', (string) $this->controller(Topic::class)->call('uniqueRule', 'topics', 'code', null));
    }

    public function test_every_language_of_a_translated_field_gets_its_rules(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        $controller = $this->controller(Page::class);
        $field = TextField::make('name')->rules(['unique:pages', 'unique:pages,slug', 'unique:pages,NULL', 'unique:pages,null', 'regex:/^a,b$/']);
        $uniques = static fn (array $list): array => array_values(array_map(
            static fn ($rule): string => (string) $rule,
            array_filter($list, static fn ($rule): bool => is_object($rule) || str_starts_with($rule, 'unique:')),
        ));

        $rules = $controller->call('translatableRules', $field, Request::create('/'), 'create', null);

        $this->assertSame(['name', 'name.en', 'name.de'], array_keys($rules));
        $this->assertSame(['nullable', 'array'], $rules['name']);
        foreach (['en', 'de'] as $code) {
            $this->assertSame(
                ["unique:pages,name->{$code},NULL,id", "unique:pages,slug->{$code},NULL,id", "unique:pages,name->{$code},NULL,id", "unique:pages,name->{$code},NULL,id"],
                $uniques($rules["name.{$code}"]),
            );
            $this->assertContains('regex:/^a,b$/', $rules["name.{$code}"]);
        }
    }

    public function test_a_unique_language_rule_ignores_the_record_being_edited(): void
    {
        $page = Page::query()->create(['name' => ['en' => 'Home']]);
        $field = TextField::make('name')->rules(['unique:pages']);

        $rules = $this->controller(Page::class)->call('translatableRules', $field, Request::create('/'), 'edit', $page);

        $this->assertSame(
            (string) Rule::unique('pages', 'name->en')->ignore($page->getKey(), 'id'),
            (string) array_values(array_filter($rules['name.en'], 'is_object'))[0],
        );
    }

    public function test_a_language_other_than_the_default_may_stay_empty_whatever_the_rules_say(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        $controller = $this->controller(Page::class);
        $ask = static fn (TextField $field): array => $controller->call('translatableRules', $field, Request::create('/'), 'create', null);

        $required = $ask(TextField::make('name')->required()->rules(['max:5']));
        $declared = $ask(TextField::make('name')->required()->rules(['nullable', 'max:5']));
        $optional = $ask(TextField::make('name')->rules(['max:5']));

        $this->assertContains('required', $required['name.en']);
        $this->assertNotContains('required', $required['name.de']);
        $this->assertSame('nullable', $required['name.de'][0]);
        $this->assertSame(1, array_count_values($required['name.de'])['nullable']);

        $this->assertContains('required', $declared['name.en']);
        $this->assertNotContains('required', $declared['name.de']);
        $this->assertSame(1, array_count_values($declared['name.de'])['nullable']);
        $this->assertTrue(array_is_list($declared['name.de']));

        $this->assertNotContains('required', $optional['name.en']);
        $this->assertSame(1, array_count_values($optional['name.de'])['nullable']);
        $this->assertTrue(array_is_list($optional['name.de']));
    }

    public function test_a_many_to_many_field_is_pulled_out_of_the_data_whatever_comes_before_it(): void
    {
        $controller = $this->controller(Bundle::class);
        $data = ['name' => 'Box', 'extras' => [9], 'topics' => ['5', '', null, 0, 6], 'other' => 1];

        $relations = $controller->pull('extractRelationValues', $data);

        $this->assertSame(['topics' => ['5', 0, 6]], $relations);
        $this->assertSame(['name' => 'Box', 'other' => 1], $data);
    }

    public function test_every_many_to_many_field_with_a_relation_is_pulled_out(): void
    {
        $controller = $this->controller(Bundle::class);
        $data = ['name' => 'Box', 'posts' => 3, 'topics' => [4, 5]];

        $relations = $controller->pull('extractRelationValues', $data);

        $this->assertSame(['posts' => [3], 'topics' => [4, 5]], $relations);
        $this->assertSame(['name' => 'Box'], $data);
    }

    public function test_the_relations_of_a_module_are_the_many_to_many_fields_that_have_a_method(): void
    {
        $this->assertSame(['posts', 'topics'], $this->controller(Bundle::class)->call('getBelongsToManyRelations'));
        $this->assertSame([], $this->controller(Post::class)->call('getBelongsToManyRelations'));
    }

    public function test_the_edit_form_holds_the_ids_of_a_many_to_many_field(): void
    {
        $first = Post::query()->create(['name' => 'One']);
        $second = Post::query()->create(['name' => 'Two']);
        $bundle = Bundle::query()->create(['name' => 'Box']);
        $bundle->posts()->sync([$first->id, $second->id]);

        $this->panel()->get("/admin/bundles/{$bundle->id}/edit")->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('item.posts', [$first->id, $second->id])
                ->where('item.topics', [])
                ->missing('item.extras'));
    }

    public function test_the_ids_of_a_many_to_many_field_are_saved_with_the_record(): void
    {
        $post = Post::query()->create(['name' => 'One']);

        $this->panel()->post('/admin/bundles', ['name' => 'Box', 'posts' => [$post->id], 'extras' => [$post->id]])->assertSessionHasNoErrors();

        $bundle = Bundle::query()->sole();
        $this->assertSame([$post->id], $bundle->posts()->pluck('posts.id')->all());
        $this->assertSame([], $bundle->topics()->pluck('topics.id')->all());
    }

    public function test_the_form_is_titled_for_the_record_or_for_a_new_one(): void
    {
        $post = Post::query()->create(['name' => 'Apple']);
        $chapter = Chapter::query()->create(['name' => 'Intro']);
        $posts = $this->controller(Post::class);
        $chapters = $this->controller(Chapter::class);

        $edit = $posts->call('prepareFormData', 'edit', $post);
        $create = $posts->call('prepareFormData', 'create');

        $this->assertSame('Edit: Apple', $edit['title']);
        $this->assertSame('Apple', $edit['itemTitle']);
        $this->assertSame('Create Posts', $create['title']);
        $this->assertNull($create['itemTitle']);
        $this->assertNull($create['item']);
        $this->assertFalse($edit['useTabs']);
        $this->assertFalse($create['useTabs']);
        $this->assertArrayNotHasKey('tabs', $edit);
        $this->assertSame('Posts', $edit['adminLabel']);

        $tabbed = $chapters->call('prepareFormData', 'edit', $chapter);
        $this->assertTrue($tabbed['useTabs']);
        $this->assertSame('Edit: Intro', $tabbed['title']);
        $this->assertSame('Intro', $tabbed['itemTitle']);
        $this->assertSame('Create Chapters', $chapters->call('prepareFormData', 'create')['title']);
        $this->assertNull($chapters->call('prepareFormData', 'create')['itemTitle']);
        $this->assertArrayHasKey('tabs', $tabbed);
        $this->assertSame(['params'], array_keys($tabbed['fieldsByTab']));
        $this->assertCount(4, $tabbed['fields']);
    }

    public function test_the_fields_of_a_module_that_chooses_them_itself_are_the_ones_it_chooses(): void
    {
        $this->assertSame(
            ['name', 'cover'],
            array_map(static fn ($field): string => $field->getName(), AdminController::fieldsOf(Brochure::class)),
        );
        $this->assertSame(
            ['name', 'active', 'sort'],
            array_map(static fn ($field): string => $field->getName(), AdminController::fieldsOf(Post::class)),
        );
    }

    public function test_a_file_sent_with_a_value_of_the_same_name_is_checked_as_a_file(): void
    {
        Hook::filter('atrium.module.fields', static fn (array $fields, string $model): array => $model === Post::class
            ? [...$fields, ImageField::make('photo')->label('Photo')]
            : $fields);
        $controller = $this->controller(Post::class);
        $request = Request::create('/', 'POST', ['photo' => 'stored/path.jpg'], [], ['photo' => UploadedFile::fake()->image('a.jpg')]);

        $rules = $controller->call('resolveRules', $request, 'create', null)['photo'];

        $this->assertContains('image', $rules);
        $this->assertNotContains('string', $rules);

        $stored = $controller->call('resolveRules', Request::create('/', 'POST', ['photo' => 'stored/path.jpg']), 'create', null)['photo'];
        $this->assertContains('string', $stored);
        $this->assertNotContains('image', $stored);
    }

    public function test_the_rules_of_the_fields_after_a_translated_one_are_derived_too(): void
    {
        $rules = $this->controller(Page::class)->call('resolveRules', Request::create('/', 'POST'), 'create', null);

        $this->assertSame(['name', 'name.en', 'code', 'code.en', 'sort'], array_keys($rules));
    }

    public function test_a_label_in_english_is_lowercased_with_its_accents_and_in_another_language_kept(): void
    {
        Hook::filter('atrium.module.fields', static fn (array $fields, string $model): array => $model === Post::class
            ? [...$fields, TextField::make('title')->label('ÜBER Äpfel')]
            : $fields);
        $controller = $this->controller(Post::class);

        $this->assertSame('über äpfel', $controller->call('validationAttributes', 'create')['title']);

        $this->app->setLocale('de');
        $this->assertSame('ÜBER Äpfel', $controller->call('validationAttributes', 'create')['title']);
    }

    public function test_a_field_without_a_label_gets_no_name_and_does_not_hide_the_fields_after_it(): void
    {
        Hook::filter('atrium.module.fields', static fn (array $fields, string $model): array => $model === Post::class
            ? [...$fields, TextField::make('blank'), TextField::make('title')->label('Heading')]
            : $fields);

        $attributes = $this->controller(Post::class)->call('validationAttributes', 'create');

        $this->assertArrayNotHasKey('blank', $attributes);
        $this->assertSame('heading', $attributes['title']);
    }

    public function test_the_form_uses_tabs_only_for_a_model_that_has_both_the_tab_grouping_and_the_switch(): void
    {
        $switchOnly = new class extends Model
        {
            public static function getAdminLabel(): string
            {
                return 'Switch';
            }

            public static function usesTabs(): bool
            {
                return true;
            }

            public static function getAdministrationFields(): array
            {
                return [TextField::make('name')];
            }
        };
        $groupingOnly = new class extends Model
        {
            public static function getAdminLabel(): string
            {
                return 'Grouping';
            }

            public static function getFieldsGroupedByTabs(string $context): array
            {
                throw new LogicException('Grouping alone does not turn tabs on.');
            }

            public static function getAdministrationFields(): array
            {
                return [TextField::make('name')];
            }
        };

        foreach ([$switchOnly, $groupingOnly] as $model) {
            $form = $this->controller($model::class)->call('prepareFormData', 'create');

            $this->assertFalse($form['useTabs']);
            $this->assertArrayNotHasKey('fieldsByTab', $form);
        }

        $this->assertTrue($this->controller(Chapter::class)->call('prepareFormData', 'create')['useTabs'] ?? false);
    }

    public function test_translations_in_a_language_that_is_not_active_are_dropped_from_every_translated_field(): void
    {
        $controller = $this->controller(Page::class);
        $request = Request::create('/', 'POST', ['code' => ['en' => 'home', 'fr' => 'accueil'], 'sort' => 3]);

        $data = $controller->call('submittedData', $request, 'create', new Page);

        $this->assertSame(['code' => ['en' => 'home'], 'sort' => 3], $data);

        $none = $controller->call('submittedData', Request::create('/', 'POST', ['name' => ['fr' => 'x'], 'code' => ['en' => 'home']]), 'create', new Page);
        $this->assertSame(['code' => ['en' => 'home']], $none);
    }

    public function test_the_fields_of_every_tab_are_serialized(): void
    {
        $result = $this->controller(Post::class)->call('serializeFieldsByTab', [
            'params' => [TextField::make('a')->label('A')],
            'seo' => [TextField::make('b')->label('B'), TextField::make('c')->label('C')],
        ], 'create');

        $this->assertSame(['params', 'seo'], array_keys($result));
        $this->assertSame(['a'], array_column($result['params'], 'name'));
        $this->assertSame(['b', 'c'], array_column($result['seo'], 'name'));
    }

    public function test_the_seo_values_of_a_form_are_pulled_out_whichever_of_them_come_first(): void
    {
        $controller = $this->controller(Article::class);
        $data = ['name' => 'Hello', 'seo_description' => 'About', 'seo_title' => 'Title'];

        $seo = $controller->pull('extractSeoValues', $data);

        $this->assertSame(['title' => 'Title', 'description' => 'About'], $seo);
        $this->assertSame(['name' => 'Hello'], $data);

        $partial = ['name' => 'Hello', 'seo_description' => 'About'];
        $this->assertSame(['description' => 'About'], $controller->pull('extractSeoValues', $partial));
        $this->assertSame(['name' => 'Hello'], $partial);

        $none = ['name' => 'Hello'];
        $this->assertSame([], $controller->pull('extractSeoValues', $none));
        $this->assertSame([], $this->controller(Post::class)->pull('extractSeoValues', $data));
    }

    public function test_seo_is_saved_only_by_a_record_that_can_read_and_write_it(): void
    {
        $controller = $this->controller(Article::class);
        $writes = [];
        $both = new class($writes) extends Model
        {
            public function __construct(private array &$writes = [])
            {
                parent::__construct();
            }

            public function seoMeta(): object
            {
                return new class
                {
                    public function exists(): bool
                    {
                        return true;
                    }
                };
            }

            public function updateSeoMeta(array $data): void
            {
                $this->writes[] = $data;
            }
        };
        $readOnly = new class extends Model
        {
            public function seoMeta(): object
            {
                throw new LogicException('Nothing to write with.');
            }
        };
        $writeOnly = new class extends Model
        {
            public function updateSeoMeta(array $data): void
            {
                throw new LogicException('Nothing to read from.');
            }
        };

        $controller->call('persistSeoValues', $both, ['title' => 'T']);
        $controller->call('persistSeoValues', $both, []);
        $controller->call('persistSeoValues', $readOnly, ['title' => 'T']);
        $controller->call('persistSeoValues', $writeOnly, ['title' => 'T']);

        $this->assertSame([['title' => 'T']], $writes);
    }

    public function test_a_related_column_shows_the_default_language_only_of_a_record_that_translates_it(): void
    {
        $both = new class extends Model
        {
            public function isTranslatableAttribute(string $key): bool
            {
                return $key === 'name';
            }

            public function getTranslation(string $key, string $language): string
            {
                return "T-{$key}-{$language}";
            }
        };
        $other = new class extends Model
        {
            public function isTranslatableAttribute(string $key): bool
            {
                return false;
            }

            public function getTranslation(string $key, string $language): string
            {
                return "T-{$key}-{$language}";
            }
        };
        $asks = new class extends Model
        {
            public function isTranslatableAttribute(string $key): bool
            {
                return true;
            }
        };
        $reads = new class extends Model
        {
            public function getTranslation(string $key, string $language): string
            {
                return "T-{$key}-{$language}";
            }
        };
        $plain = new class extends Model {};
        $controller = $this->controller(Topic::class);

        $shown = static function (Model $related) use ($controller): mixed {
            $related->setRawAttributes(['name' => 'Raw']);
            $topic = new Topic;
            $topic->setRelation('page', $related);

            return $controller->call('listRow', $topic, ['page.name'])['page.name'];
        };

        $this->assertSame('T-name-en', $shown($both));
        $this->assertSame('Raw', $shown($other));
        $this->assertSame('Raw', $shown($asks));
        $this->assertSame('Raw', $shown($reads));
        $this->assertSame('Raw', $shown($plain));
    }

    public function test_the_actions_of_a_record_reach_the_page_as_plain_arrays(): void
    {
        $actions = $this->controller(Chapter::class)->call('resourceProps')['actions'];

        $this->assertCount(2, $actions);
        $this->assertContainsOnlyArray($actions);
        $this->assertSame('Subchapter', $actions[0]['label']);
    }
}
