<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Tests\Fixtures\Note;
use Atrium\Admin\Tests\Fixtures\Page;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Story;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The record picker behind LinkField: the records of one module by a few
 * words, with their names and addresses, for administrators who may see
 * that module.
 */
final class LinkPickerTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Post::class, Story::class, Page::class, Note::class]);
        $app['config']->set('atrium.public_urls', [Post::class => ['prefix' => 'posts'], Page::class => ['prefix' => 'pages']]);
    }

    protected function defineRoutes($router): void
    {
        Route::localized(function (): void {
            Route::get('stories/{code}', fn (string $code) => $code)->name('stories.show');
        });
    }

    private function makePost(string $name, string $code): Post
    {
        $post = new Post(['name' => $name, 'active' => true]);
        $post->forceFill(['code' => $code])->save();

        return $post;
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
     * @param  array<string,mixed>  $query
     */
    private function pick(string $model, array $query = []): TestResponse
    {
        return $this->getJson('/admin/links/records?'.http_build_query(['model' => $model, ...$query]));
    }

    public function test_a_visitor_is_sent_to_the_login(): void
    {
        $this->makePost('Hello', 'hello');

        $this->get('/admin/links/records?model='.urlencode(Post::class))->assertRedirect();
        $this->getJson('/admin/links/records?model='.urlencode(Post::class))->assertUnauthorized();
    }

    public function test_records_are_found_by_name_with_their_names_and_addresses(): void
    {
        $hello = $this->makePost('Hello world', 'hello');
        $this->makePost('Goodbye', 'bye');
        $this->actingAs($this->admin(), 'admin');

        $this->pick(Post::class, ['q' => 'hello'])
            ->assertOk()
            ->assertExactJson(['data' => [['id' => $hello->id, 'label' => 'Hello world', 'url' => url('posts/hello')]]]);

        $this->pick(Post::class, ['q' => 'nothing like it'])->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_a_record_is_found_by_its_id(): void
    {
        $hello = $this->makePost('Hello world', 'hello');
        $this->makePost('Goodbye', 'bye');
        $this->actingAs($this->admin(), 'admin');

        $this->pick(Post::class, ['q' => (string) $hello->id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $hello->id);
    }

    public function test_the_words_may_have_spaces_around_them_and_any_case(): void
    {
        $this->makePost('Hello world', 'hello');
        $this->actingAs($this->admin(), 'admin');

        $this->pick(Post::class, ['q' => '  HELLO  '])->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_without_words_the_first_records_are_listed(): void
    {
        $this->makePost('One', 'one');
        $this->makePost('Two', 'two');
        $this->actingAs($this->admin(), 'admin');

        $this->pick(Post::class)->assertOk()->assertJsonCount(2, 'data');
        $this->pick(Post::class, ['q' => ''])->assertOk()->assertJsonCount(2, 'data');
        $this->pick(Post::class, ['q' => '   '])->assertOk()->assertJsonCount(2, 'data');
    }

    /**
     * @return array<string,array{0:int,1:int}>
     */
    public static function counts(): array
    {
        return [
            'one' => [1, 1],
            'fewer than the limit' => [19, 19],
            'as many as the limit' => [20, 20],
            'one more than the limit' => [21, 20],
            'far more' => [45, 20],
        ];
    }

    #[DataProvider('counts')]
    public function test_no_more_than_twenty_records_come_back(int $records, int $listed): void
    {
        foreach (range(1, $records) as $n) {
            $this->makePost("Post {$n}", "post-{$n}");
        }

        $this->actingAs($this->admin(), 'admin');

        $this->pick(Post::class)->assertOk()->assertJsonCount($listed, 'data');
        $this->pick(Post::class, ['q' => 'post'])->assertOk()->assertJsonCount($listed, 'data');
    }

    public function test_the_list_has_nothing_but_the_id_the_name_and_the_address(): void
    {
        $this->makePost('Hello', 'hello');
        $this->actingAs($this->admin(), 'admin');

        $row = $this->pick(Post::class)->assertOk()->json('data.0');

        $this->assertSame(['id', 'label', 'url'], array_keys($row));
    }

    public function test_a_record_without_a_page_comes_with_no_address(): void
    {
        $post = new Post(['name' => 'Without a code', 'active' => true]);
        $post->save();
        $this->actingAs($this->admin(), 'admin');

        $this->pick(Post::class)->assertOk()->assertExactJson(['data' => [['id' => $post->id, 'label' => 'Without a code', 'url' => null]]]);
    }

    public function test_the_name_is_text_without_markup(): void
    {
        $this->makePost('<img src=x onerror=alert(1)>Chair', 'chair');
        $this->actingAs($this->admin(), 'admin');

        $this->pick(Post::class)->assertOk()->assertJsonPath('data.0.label', 'Chair');
    }

    public function test_a_module_that_lists_its_own_pages_answers_with_its_own_addresses(): void
    {
        $story = Story::query()->create(['name' => ['en' => 'Hello'], 'code' => ['en' => 'hello']]);
        $this->actingAs($this->admin(), 'admin');

        $this->pick(Story::class)
            ->assertOk()
            ->assertExactJson(['data' => [['id' => $story->id, 'label' => 'Hello', 'url' => url('stories/hello')]]]);
    }

    public function test_a_translated_module_is_searched_in_every_language(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        $page = Page::query()->create(['name' => ['en' => 'Hello', 'de' => 'Hallo'], 'code' => ['en' => 'hello', 'de' => 'hallo']]);
        Page::query()->create(['name' => ['en' => 'Goodbye'], 'code' => ['en' => 'goodbye']]);
        $this->actingAs($this->admin(), 'admin');

        $this->pick(Page::class, ['q' => 'hallo'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $page->id)
            ->assertJsonPath('data.0.url', url('pages/hello'));
    }

    // ---- refusals ----

    /**
     * @return array<string,array{0:array<string,mixed>}>
     */
    public static function notModules(): array
    {
        return [
            'no module' => [[]],
            'an empty module' => [['model' => '']],
            'a class that is not a module' => [['model' => Model::class]],
            'a class that does not exist' => [['model' => 'No\\Such\\Class']],
            'a module that has no pages' => [['model' => Note::class]],
            'a module in lower case' => [['model' => 'atrium\\admin\\tests\\fixtures\\post']],
            'a module with a slash in front' => [['model' => '\\'.Post::class]],
            'a built-in module' => [['model' => Site::class]],
            'a path' => [['model' => '../../etc/passwd']],
            'a list of modules' => [['model' => [Post::class]]],
        ];
    }

    /**
     * @param  array<string,mixed>  $query
     */
    #[DataProvider('notModules')]
    public function test_what_is_not_a_module_with_pages_is_not_found(array $query): void
    {
        $this->makePost('Hello', 'hello');
        $this->actingAs($this->admin(), 'admin');

        $this->getJson('/admin/links/records?'.http_build_query($query))->assertNotFound();
    }

    public function test_the_module_is_judged_before_the_right_to_see_it(): void
    {
        $this->actingAs($this->editor([]), 'admin');

        $this->pick(Note::class)->assertNotFound();
        $this->pick(Post::class)->assertForbidden();
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
            'the right to see another module' => [['pages.view']],
        ];
    }

    /**
     * @param  array<int,string>  $permissions
     */
    #[DataProvider('rolesWithoutView')]
    public function test_an_administrator_without_the_right_to_see_the_module_gets_nothing(array $permissions): void
    {
        $this->makePost('Hello', 'hello');
        $this->actingAs($this->editor($permissions), 'admin');

        $this->pick(Post::class)->assertForbidden()->assertJsonMissingPath('data');
        $this->pick(Post::class, ['q' => 'hello'])->assertForbidden();
    }

    public function test_an_administrator_with_the_right_to_see_the_module_gets_its_records(): void
    {
        $this->makePost('Hello', 'hello');
        $this->actingAs($this->editor(['posts.view']), 'admin');

        $this->pick(Post::class)->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_deactivated_administrator_gets_nothing(): void
    {
        $this->makePost('Hello', 'hello');
        $editor = $this->editor(['posts.view']);
        $editor->forceFill(['is_active' => false])->save();
        $this->actingAs($editor, 'admin');

        $this->pick(Post::class)->assertForbidden();
    }

    public function test_the_records_of_another_site_are_not_offered(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        $main = Site::query()->where('code', 'main')->sole();
        $german = Site::query()->create(['code' => 'de', 'name' => 'Deutschland', 'domains' => ['example.de'], 'languages' => ['de']]);
        $ours = Story::query()->create(['site_id' => $main->id, 'name' => ['en' => 'Ours'], 'code' => ['en' => 'ours']]);
        $theirs = Story::query()->create(['site_id' => $german->id, 'name' => ['en' => 'Theirs'], 'code' => ['en' => 'theirs']]);
        $this->actingAs($this->admin(), 'admin');

        $this->pick(Story::class)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ours->id);
        $this->pick(Story::class, ['q' => (string) $theirs->id])->assertOk()->assertJsonCount(0, 'data');

        $this->post('/admin/site', ['site_id' => $german->id])->assertRedirect();

        $this->pick(Story::class)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $theirs->id);
    }

    public function test_words_sent_as_a_list_are_taken_for_no_words(): void
    {
        $this->makePost('Hello', 'hello');
        $this->makePost('World', 'world');
        $this->actingAs($this->admin(), 'admin');

        $this->pick(Post::class, ['q' => ['Hello']])->assertOk()->assertJsonCount(2, 'data');
        $this->pick(Post::class, ['q' => 'Hello'])->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_search_that_is_an_attack_finds_nothing_and_breaks_nothing(): void
    {
        $this->makePost('Hello', 'hello');
        $this->actingAs($this->admin(), 'admin');

        foreach (["' OR '1'='1", '1; DROP TABLE posts; --', '"><script>alert(1)</script>', '%', '_'] as $text) {
            $this->pick(Post::class, ['q' => $text])->assertOk();
        }

        $this->assertSame(1, Post::query()->count());
    }
}
