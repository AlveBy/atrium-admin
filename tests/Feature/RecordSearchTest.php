<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Columns\Column;
use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Support\RecordSearch;
use Atrium\Admin\Tests\Fixtures\Note;
use Atrium\Admin\Tests\Fixtures\Page;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Records of a module found by a few words, the way the module's own list
 * finds them: for the record picker and the editors' search box.
 */
final class RecordSearchTest extends TestCase
{
    /** @var array<int,Column> */
    public static array $columns = [];

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Post::class, Page::class]);
    }

    private function search(): RecordSearch
    {
        return $this->app->make(RecordSearch::class);
    }

    private function makePost(string $name, ?string $code = null, bool $active = true, ?int $id = null): Post
    {
        $post = new Post(['name' => $name, 'active' => $active]);
        $post->forceFill(['code' => $code, ...($id === null ? [] : ['id' => $id])])->save();

        return $post;
    }

    /**
     * A list column that says whatever it is told, even what no real column does.
     *
     * @param  array<string,mixed>  $data
     */
    private function column(array $data): Column
    {
        return new class($data) extends TextColumn
        {
            /**
             * @param  array<string,mixed>  $data
             */
            public function __construct(private readonly array $data) {}

            public function toArray(): array
            {
                return $this->data;
            }
        };
    }

    /**
     * A module over the posts table with the given list columns, whose list hides inactive records.
     *
     * @param  array<int,Column>  $columns
     * @return class-string<Model>
     */
    private function module(array $columns): string
    {
        self::$columns = $columns;

        $model = new class extends Model
        {
            protected $table = 'posts';

            /** @return array<int,Column> */
            public static function getAdministrationListColumns(): array
            {
                return RecordSearchTest::$columns;
            }

            /**
             * @param  Builder<Model>  $query
             * @return Builder<Model>
             */
            public function scopeAdminList(Builder $query): Builder
            {
                return $query->where('active', true);
            }
        };

        return $model::class;
    }

    /**
     * @param  array<int,string>  $permissions
     */
    private function editor(array $permissions): AdminUser
    {
        $user = AdminUser::query()->create(['name' => 'Editor', 'email' => 'editor@example.com', 'password' => 'secret-password', 'is_active' => true]);
        $user->roles()->attach(AdminRole::query()->create(['name' => 'Role', 'permissions' => $permissions]));

        return $user->fresh();
    }

    /**
     * @return array<int,string>
     */
    private function names(Builder $query): array
    {
        return $query->orderBy('id')->get()->map(fn (Model $record): string => (string) $record->getAttribute('name'))->all();
    }

    // ---- searchableColumns ----

    /**
     * @return array<string,array{0:array<int,array<string,mixed>>,1:array<int,string>}>
     */
    public static function columnSets(): array
    {
        return [
            'a searchable column' => [[['name' => 'name', 'searchable' => true]], ['name']],
            'a column that is not searchable' => [[['name' => 'name', 'searchable' => false]], []],
            'a column that does not say' => [[['name' => 'name']], []],
            'the order of the list is kept' => [[['name' => 'b', 'searchable' => true], ['name' => 'a', 'searchable' => true]], ['b', 'a']],
            'only the searchable ones' => [[['name' => 'id', 'searchable' => false], ['name' => 'name', 'searchable' => true], ['name' => 'code']], ['name']],
            'a related column is left out' => [[['name' => 'owner.name', 'searchable' => true], ['name' => 'name', 'searchable' => true]], ['name']],
            'a column of a column of a related record' => [[['name' => 'a.b.c', 'searchable' => true]], []],
            'a column with a dot first' => [[['name' => '.name', 'searchable' => true]], []],
            'a column without a name' => [[['searchable' => true]], []],
            'a column with an empty name' => [[['name' => '', 'searchable' => true]], []],
            'a column with a null name' => [[['name' => null, 'searchable' => true]], []],
            'a column whose name is a number' => [[['name' => 5, 'searchable' => true]], []],
            'no columns' => [[], []],
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $data
     * @param  array<int,string>  $expected
     */
    #[DataProvider('columnSets')]
    public function test_the_searchable_columns_are_the_lists_own_without_related_ones(array $data, array $expected): void
    {
        $model = $this->module(array_map($this->column(...), $data));

        $this->assertSame($expected, $this->search()->searchableColumns($model));
    }

    public function test_a_model_with_no_list_has_no_searchable_columns(): void
    {
        $this->assertSame([], $this->search()->searchableColumns(Model::class));
        $this->assertSame(['name'], $this->search()->searchableColumns(Post::class));
    }

    // ---- query ----

    public function test_the_query_is_the_modules_own_list(): void
    {
        $model = $this->module([$this->column(['name' => 'name', 'searchable' => true])]);
        $this->makePost('Shown');
        $this->makePost('Hidden', active: false);

        $this->assertSame(['Shown'], $this->names($this->search()->query($model, '')));
        $this->assertSame(['Shown'], $this->names($this->search()->query($model, 'shown')));
        $this->assertSame([], $this->names($this->search()->query($model, 'hidden')), 'what the list hides is not found');
    }

    public function test_a_model_without_a_list_scope_is_searched_whole(): void
    {
        $this->makePost('One');
        $two = $this->makePost('Two', active: false);

        // A plain model: no scopeAdminList, no list columns, so only the id can be searched.
        $model = new class extends Model
        {
            protected $table = 'posts';
        };

        $this->assertSame(['One', 'Two'], $this->names($this->search()->query($model::class, '')));
        $this->assertSame(['Two'], $this->names($this->search()->query($model::class, (string) $two->id)));
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function nothingToSearchFor(): array
    {
        return [
            'empty' => [''],
            'a space' => [' '],
            'spaces' => ['     '],
            'a tab and a line break' => ["\t\n"],
        ];
    }

    #[DataProvider('nothingToSearchFor')]
    public function test_no_words_means_every_record(string $text): void
    {
        $this->makePost('One');
        $this->makePost('Two');

        $this->assertSame(['One', 'Two'], $this->names($this->search()->query(Post::class, $text)));
    }

    /**
     * @return array<string,array{0:string,1:array<int,string>}>
     */
    public static function words(): array
    {
        return [
            'a whole name' => ['Garden chair', ['Garden chair']],
            'part of a name' => ['arden', ['Garden chair']],
            'the start of a name' => ['gar', ['Garden chair']],
            'the end of a name' => ['den chair', ['Garden chair']],
            'in capitals' => ['GARDEN', ['Garden chair']],
            'in lower case' => ['garden chair', ['Garden chair']],
            'with spaces around' => ['  garden  ', ['Garden chair']],
            'in more than one record' => ['chair', ['Chair 🪑', 'Garden chair', 'Office chair']],
            'in none' => ['table', []],
            'Cyrillic' => ['стул', ['Садовый стул']],
            'Chinese' => ['花园', ['花园椅']],
            'Japanese' => ['ガーデン', ['ガーデンチェア']],
            'Arabic' => ['حديقة', ['كرسي حديقة']],
            'Hebrew' => ['גן', ['כיסא גן']],
            'emoji' => ['🪑', ['Chair 🪑']],
            'a quote' => ["o'clock", ["Five o'clock"]],
            'a quote that tries to break out' => ["' OR '1'='1", []],
            'a statement' => ['1; DROP TABLE posts; --', []],
            'a tag' => ['<script>alert(1)</script>', ['<script>alert(1)</script>']],
            'a backslash' => ['\\', []],
        ];
    }

    /**
     * @param  array<int,string>  $expected
     */
    #[DataProvider('words')]
    public function test_a_few_words_find_records_by_their_name(string $text, array $expected): void
    {
        foreach (['Garden chair', 'Office chair', 'Садовый стул', '花园椅', 'ガーデンチェア', 'كرسي حديقة', 'כיסא גן', 'Chair 🪑', "Five o'clock", '<script>alert(1)</script>'] as $name) {
            $this->makePost($name);
        }

        $found = $this->names($this->search()->query(Post::class, $text));
        sort($found);
        sort($expected);

        $this->assertSame($expected, $found);
        $this->assertSame(10, Post::query()->count(), 'whatever was searched for, nothing was dropped');
    }

    public function test_every_searchable_column_is_searched(): void
    {
        $model = $this->module([
            $this->column(['name' => 'name', 'searchable' => true]),
            $this->column(['name' => 'code', 'searchable' => true]),
            $this->column(['name' => 'sort']),
        ]);
        $this->makePost('Alpha', 'first');
        $this->makePost('Beta', 'alpine');
        $this->makePost('Gamma', 'third');

        $this->assertSame(['Alpha', 'Beta'], $this->names($this->search()->query($model, 'alp')));
        $this->assertSame(['Beta'], $this->names($this->search()->query($model, 'pine')));
        $this->assertSame(['Alpha'], $this->names($this->search()->query($model, 'first')));
        $this->assertSame([], $this->names($this->search()->query($model, '500')), 'a column that is not searchable is not searched');
    }

    public function test_a_number_finds_the_record_with_that_id_and_records_that_have_it_in_their_name(): void
    {
        $this->makePost('Seven', id: 7);
        $this->makePost('7 wonders', id: 8);
        $this->makePost('Seventy', id: 70);
        $this->makePost('Other', id: 9);

        $this->assertSame(['Seven', '7 wonders'], $this->names($this->search()->query(Post::class, '7')));
        $this->assertSame(['Seven', '7 wonders'], $this->names($this->search()->query(Post::class, ' 7 ')));
        $this->assertSame(['Seven'], $this->names($this->search()->query(Post::class, '007')), 'leading zeros do not matter');
        $this->assertSame(['Other'], $this->names($this->search()->query(Post::class, '9')));
        $this->assertSame([], $this->names($this->search()->query(Post::class, '11')));
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function notNumbers(): array
    {
        return [
            'a minus' => ['-7'],
            'a plus' => ['+7'],
            'a decimal' => ['7.0'],
            'a letter after' => ['7a'],
            'a hex number' => ['0x7'],
            'an exponent' => ['7e0'],
            'a full-width digit' => ['７'],
        ];
    }

    #[DataProvider('notNumbers')]
    public function test_only_digits_are_taken_for_an_id(string $text): void
    {
        $this->makePost('Seven', id: 7);

        $this->assertSame([], $this->names($this->search()->query(Post::class, $text)));
    }

    public function test_a_module_with_nothing_searchable_finds_no_records_by_words(): void
    {
        $model = $this->module([]);
        $this->makePost('Seven', id: 7);
        $this->makePost('Other', id: 8);

        $this->assertSame([], $this->names($this->search()->query($model, 'Seven')));
        $this->assertSame([], $this->names($this->search()->query($model, '7a')));
    }

    public function test_a_module_with_nothing_searchable_still_finds_a_record_by_its_id(): void
    {
        $model = $this->module([]);
        $this->makePost('Seven', id: 7);
        $this->makePost('Other', id: 8);

        $this->assertSame(['Seven'], $this->names($this->search()->query($model, '7')));
        $this->assertSame([], $this->names($this->search()->query($model, '9')));
    }

    public function test_a_number_too_large_for_an_id_finds_nothing_and_does_not_fail(): void
    {
        $this->makePost('Seven', id: 7);

        $this->assertSame([], $this->names($this->search()->query(Post::class, str_repeat('9', 40))));
    }

    public function test_a_translated_column_is_searched_in_every_language_of_the_site(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        Page::query()->create(['name' => ['en' => 'Hello', 'de' => 'Hallo'], 'code' => ['en' => 'hello', 'de' => 'hallo']]);
        Page::query()->create(['name' => ['en' => 'Goodbye'], 'code' => ['en' => 'goodbye']]);
        Page::query()->create(['name' => ['fr' => 'Bonjour'], 'code' => ['fr' => 'bonjour']]);

        $names = fn (string $text): array => $this->search()->query(Page::class, $text)->orderBy('id')->get()
            ->map(fn (Page $page): string => (string) $page->getTranslation('name', 'en', false) ?: (string) $page->getTranslation('name', 'fr', false))
            ->all();

        $this->assertSame(['Hello'], $names('hello'), 'in the first language');
        $this->assertSame(['Hello'], $names('HALLO'), 'in another, whatever the case');
        $this->assertSame(['Goodbye'], $names('goodbye'));
        $this->assertSame([], $names('bonjour'), 'a language the site does not have is not searched');
        $this->assertSame([], $names('nothing'));
        $this->assertCount(3, $this->search()->query(Page::class, '')->get());
    }

    public function test_a_translated_name_is_not_found_by_its_json(): void
    {
        Page::query()->create(['name' => ['en' => 'Hello'], 'code' => ['en' => 'hello']]);

        $this->assertCount(0, $this->search()->query(Page::class, '"en"')->get());
        $this->assertCount(0, $this->search()->query(Page::class, '{')->get());
    }

    // ---- label ----

    /**
     * @return array<string,array{0:string,1:string}>
     */
    public static function labels(): array
    {
        return [
            'a name' => ['Garden chair', 'Garden chair'],
            'spaces around it' => ['  Garden chair  ', 'Garden chair'],
            'tags are taken out' => ['<b>Garden</b> chair', 'Garden chair'],
            'a script is taken out with its tags' => ['<script>alert(1)</script>Chair', 'alert(1)Chair'],
            'an attribute that runs code' => ['"><img src=x onerror=alert(1)>Chair', '">Chair'],
            'tags and spaces' => ['  <p> Garden </p>  ', 'Garden'],
            'Cyrillic' => ['Садовый стул', 'Садовый стул'],
            'exactly as long as allowed' => [str_repeat('a', 120), str_repeat('a', 120)],
            'one character too long' => [str_repeat('a', 121), str_repeat('a', 120).'...'],
            'much too long' => [str_repeat('b', 255), str_repeat('b', 120).'...'],
            'too long once the tags are out' => ['<b>'.str_repeat('c', 121).'</b>', str_repeat('c', 120).'...'],
            'long only with its tags' => ['<b>'.str_repeat('d', 120).'</b>', str_repeat('d', 120)],
        ];
    }

    #[DataProvider('labels')]
    public function test_a_record_is_known_by_its_name_without_markup_and_not_too_long(string $name, string $label): void
    {
        $this->assertSame($label, $this->search()->label($this->makePost($name)));
    }

    public function test_a_record_without_a_name_of_its_own_is_known_by_its_number(): void
    {
        $model = new class extends Model
        {
            protected $table = 'posts';
        };
        $model->setAttribute('id', 42);

        $this->assertSame('42', $this->search()->label($model));
    }

    // ---- mayView ----

    public function test_a_superadmin_may_see_every_module(): void
    {
        $admin = $this->admin();

        $this->assertTrue($this->search()->mayView($admin, Post::class));
        $this->assertTrue($this->search()->mayView($admin, Page::class));
    }

    public function test_an_administrator_may_see_the_modules_their_role_lets_them_view(): void
    {
        $editor = $this->editor(['posts.view']);

        $this->assertTrue($this->search()->mayView($editor, Post::class));
        $this->assertFalse($this->search()->mayView($editor, Page::class), 'viewing one module is not viewing another');
    }

    /**
     * @return array<string,array{0:array<int,string>}>
     */
    public static function rolesWithoutView(): array
    {
        return [
            'no rights at all' => [[]],
            'create only' => [['posts.create']],
            'edit only' => [['posts.update']],
            'delete only' => [['posts.delete']],
            'another module' => [['pages.view']],
        ];
    }

    /**
     * @param  array<int,string>  $permissions
     */
    #[DataProvider('rolesWithoutView')]
    public function test_an_administrator_without_the_right_may_not_see_the_module(array $permissions): void
    {
        $this->assertFalse($this->search()->mayView($this->editor($permissions), Post::class));
    }

    public function test_nobody_may_see_what_is_not_a_module(): void
    {
        $admin = $this->admin();

        $this->assertFalse($this->search()->mayView($admin, Note::class));
        $this->assertFalse($this->search()->mayView(null, Note::class));
        $this->assertFalse($this->search()->mayView($admin, Model::class));
    }

    public function test_without_an_administrator_every_module_may_be_seen(): void
    {
        $this->assertTrue($this->search()->mayView(null, Post::class));
    }
}
