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
use Atrium\Admin\Tests\Fixtures\Crate;
use Atrium\Admin\Tests\Fixtures\Kit;
use Atrium\Admin\Tests\Fixtures\Note;
use Atrium\Admin\Tests\Fixtures\Page;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Topic;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The search of a relation field: a page of the linked module's records by a
 * few words, for a form whose field has too many records to list them all.
 */
final class RelationOptionsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Kit::class, Post::class, Note::class, Page::class, Crate::class]);
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
    private function search(string $field, array $query = []): TestResponse
    {
        return $this->getJson("/admin/kits/fields/{$field}/options".($query === [] ? '' : '?'.http_build_query($query)));
    }

    private function makePost(string $name, bool $active = true): Post
    {
        return Post::query()->create(['name' => $name, 'active' => $active]);
    }

    /**
     * @return array<int,Post>
     */
    private function posts(int $count): array
    {
        return array_map(fn (int $n): Post => $this->makePost(sprintf('Post %02d', $n)), range(1, $count));
    }

    // ---- what it finds ----

    public function test_a_visitor_is_sent_to_the_login(): void
    {
        $this->get('/admin/kits/fields/posts/options')->assertRedirect();
        $this->search('posts')->assertUnauthorized();
    }

    public function test_records_are_found_by_a_part_of_their_name_in_any_case(): void
    {
        $chair = $this->makePost('Garden chair');
        $this->makePost('Kitchen table');
        $this->actingAs($this->admin(), 'admin');

        $this->search('posts', ['q' => 'CHAIR'])
            ->assertOk()
            ->assertExactJson(['data' => [['value' => $chair->id, 'label' => 'Garden chair']], 'more' => false]);
        $this->search('posts', ['q' => '  den ch  '])->assertOk()->assertJsonPath('data.0.value', $chair->id);
        $this->search('posts', ['q' => 'sofa'])->assertOk()->assertExactJson(['data' => [], 'more' => false]);
    }

    public function test_a_record_is_found_by_its_id_as_well_as_by_digits_in_names(): void
    {
        $posts = $this->posts(12);
        $named = $this->makePost('Model 3');
        $this->actingAs($this->admin(), 'admin');

        $this->search('posts', ['q' => (string) $posts[2]->id])
            ->assertOk()
            ->assertExactJson(['data' => [['value' => $posts[2]->id, 'label' => 'Post 03'], ['value' => $named->id, 'label' => 'Model 3']], 'more' => false]);
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function unicode(): array
    {
        return [
            'Cyrillic' => ['Садовый стул'],
            'Chinese' => ['花园椅'],
            'Japanese' => ['ガーデンチェア'],
            'Arabic, right to left' => ['كرسي حديقة'],
            'Hebrew, right to left' => ['כיסא גן'],
            'emoji' => ['Chair 🪑'],
        ];
    }

    #[DataProvider('unicode')]
    public function test_names_in_any_script_are_found_by_a_part(string $name): void
    {
        $post = $this->makePost($name);
        $this->makePost('Other');
        $this->actingAs($this->admin(), 'admin');

        $this->search('posts', ['q' => mb_substr($name, 1, 3)])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.value', $post->id);
    }

    public function test_wildcards_typed_in_the_box_are_plain_characters(): void
    {
        $this->makePost('100% cotton');
        $this->makePost('100 cotton');
        $this->makePost('a_b');
        $this->makePost('axb');
        $this->actingAs($this->admin(), 'admin');

        $this->search('posts', ['q' => '0%'])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.label', '100% cotton');
        $this->search('posts', ['q' => 'a_b'])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.label', 'a_b');
    }

    /**
     * @return array<string,array{0:array<string,mixed>}>
     */
    public static function noWords(): array
    {
        return [
            'no words at all' => [[]],
            'empty' => [['q' => '']],
            'only spaces' => [['q' => '   ']],
            'a list instead of words' => [['q' => ['Post 01']]],
        ];
    }

    /**
     * @param  array<string,mixed>  $query
     */
    #[DataProvider('noWords')]
    public function test_without_words_the_first_records_are_listed_by_id(array $query): void
    {
        $posts = $this->posts(3);
        $this->actingAs($this->admin(), 'admin');

        $this->search('posts', $query)
            ->assertOk()
            ->assertExactJson(['data' => array_map(fn (Post $post): array => ['value' => $post->id, 'label' => $post->name], $posts), 'more' => false]);
    }

    /**
     * @return array<string,array{0:int,1:int|string,2:int,3:bool,4:int}>
     */
    public static function pages(): array
    {
        return [
            'one record' => [1, 1, 1, false, 1],
            'one page exactly' => [20, 1, 20, false, 1],
            'one more than a page' => [21, 1, 20, true, 1],
            'the rest on the second page' => [21, 2, 1, false, 21],
            'a full second page of three' => [45, 2, 20, true, 21],
            'the third page' => [45, 3, 5, false, 41],
            'past the end' => [5, 2, 0, false, 0],
            'page zero is the first' => [21, 0, 20, true, 1],
            'a negative page is the first' => [21, -3, 20, true, 1],
            'a word is the first' => [21, 'next', 20, true, 1],
        ];
    }

    #[DataProvider('pages')]
    public function test_records_come_twenty_at_a_time_with_a_sign_of_more(int $records, int|string $page, int $listed, bool $more, int $first): void
    {
        $posts = $this->posts($records);
        $this->actingAs($this->admin(), 'admin');

        $response = $this->search('posts', ['page' => $page])->assertOk()->assertJsonCount($listed, 'data')->assertJsonPath('more', $more);

        if ($listed > 0) {
            $response->assertJsonPath('data.0.value', $posts[$first - 1]->id);
        }

        $this->search('posts', ['q' => 'post', 'page' => $page])->assertOk()->assertJsonCount($listed, 'data')->assertJsonPath('more', $more);
    }

    public function test_the_field_query_narrows_the_records_even_by_id(): void
    {
        $shown = $this->makePost('Shown chair');
        $hidden = $this->makePost('Hidden chair', active: false);
        $this->actingAs($this->admin(), 'admin');

        $this->search('posts', ['q' => 'chair'])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.value', $shown->id);
        $this->search('posts', ['q' => (string) $hidden->id])->assertOk()->assertJsonCount(0, 'data');
        // A field without a query() offers every record.
        $this->search('post_id', ['q' => 'chair'])->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_a_translated_name_is_searched_in_every_language_and_shown_in_the_current_one(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        $page = Page::query()->create(['name' => ['en' => 'Garden', 'de' => 'Garten']]);
        Page::query()->create(['name' => ['en' => 'Kitchen', 'de' => 'Küche']]);
        $this->actingAs($this->admin(), 'admin');

        $this->search('pages', ['q' => 'garten'])
            ->assertOk()
            ->assertExactJson(['data' => [['value' => $page->id, 'label' => 'Garden']], 'more' => false]);
    }

    public function test_the_records_of_another_site_are_not_offered(): void
    {
        $main = Site::query()->where('code', 'main')->sole();
        $german = Site::query()->create(['code' => 'de', 'name' => 'Deutschland', 'domains' => ['example.de']]);
        $ours = Note::query()->create(['site_id' => $main->id, 'name' => 'Our note', 'code' => 'ours']);
        $theirs = Note::query()->create(['site_id' => $german->id, 'name' => 'Their note', 'code' => 'theirs']);
        $this->actingAs($this->admin(), 'admin');

        $this->search('notes', ['q' => 'note'])->assertOk()->assertExactJson(['data' => [['value' => $ours->id, 'label' => 'Our note']], 'more' => false]);
        $this->search('notes', ['q' => (string) $theirs->id])->assertOk()->assertJsonCount(0, 'data');

        $this->post('/admin/site', ['site_id' => $german->id])->assertRedirect();

        $this->search('notes')->assertOk()->assertExactJson(['data' => [['value' => $theirs->id, 'label' => 'Their note']], 'more' => false]);
    }

    public function test_a_search_that_is_an_attack_finds_nothing_and_breaks_nothing(): void
    {
        $this->makePost('Hello');
        $this->actingAs($this->admin(), 'admin');

        foreach (["' OR '1'='1", '1; DROP TABLE posts; --', '"><script>alert(1)</script>', '!', '\\'] as $text) {
            $this->search('posts', ['q' => $text])->assertOk()->assertJsonCount(0, 'data');
        }

        $this->assertSame(1, Post::query()->count());
    }

    // ---- refusals ----

    /**
     * @return array<string,array{0:string}>
     */
    public static function notRelations(): array
    {
        return [
            'a field that is not there' => ['nothing'],
            'a text field' => ['name'],
            'a relation field without a model' => ['loose'],
            'a field name in another case' => ['Posts'],
        ];
    }

    #[DataProvider('notRelations')]
    public function test_what_is_not_a_relation_field_of_the_module_is_not_found(string $field): void
    {
        $this->makePost('Hello');
        $this->actingAs($this->admin(), 'admin');

        $this->search($field)->assertNotFound();
    }

    public function test_a_field_name_with_other_characters_matches_no_route(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->getJson('/admin/kits/fields/po-sts/options')->assertNotFound();
        $this->getJson('/admin/kits/fields/../options')->assertNotFound();
    }

    /**
     * @return array<string,array{0:array<int,string>}>
     */
    public static function rolesWithoutTheForm(): array
    {
        return [
            'no rights at all' => [['posts.view']],
            'create only' => [['kits.create', 'posts.view']],
            'edit only' => [['kits.update', 'posts.view']],
            'another module only' => [['pages.view', 'posts.view']],
        ];
    }

    /**
     * @param  array<int,string>  $permissions
     */
    #[DataProvider('rolesWithoutTheForm')]
    public function test_an_administrator_who_may_not_see_the_module_gets_nothing(array $permissions): void
    {
        $this->makePost('Hello');
        $this->actingAs($this->editor($permissions), 'admin');

        $this->search('posts')->assertForbidden()->assertJsonMissingPath('data');
    }

    public function test_an_administrator_who_may_not_see_the_linked_module_gets_nothing(): void
    {
        $this->makePost('Hello');
        $this->actingAs($this->editor(['kits.view', 'kits.update', 'pages.view']), 'admin');

        $this->search('posts')->assertForbidden()->assertJsonMissingPath('data');
        $this->search('pages')->assertOk();
    }

    public function test_an_administrator_with_both_rights_gets_the_records(): void
    {
        $post = $this->makePost('Hello');
        $this->actingAs($this->editor(['kits.view', 'posts.view']), 'admin');

        $this->search('posts')->assertOk()->assertExactJson(['data' => [['value' => $post->id, 'label' => 'Hello']], 'more' => false]);
    }

    public function test_records_of_a_model_that_is_no_module_need_only_the_right_to_the_form(): void
    {
        $topic = Topic::query()->create(['name' => 'Gardening']);
        $this->actingAs($this->editor(['kits.view']), 'admin');

        $this->search('topics')->assertOk()->assertExactJson(['data' => [['value' => $topic->id, 'label' => 'Gardening']], 'more' => false]);
    }

    public function test_a_deactivated_administrator_gets_nothing(): void
    {
        $editor = $this->editor(['kits.view', 'posts.view']);
        $editor->forceFill(['is_active' => false])->save();
        $this->actingAs($editor, 'admin');

        $this->search('posts')->assertForbidden();
    }

    // ---- the form ----

    /**
     * @return array<string,mixed> the form's field by name
     */
    private function formField(TestResponse $response, string $name): array
    {
        $field = null;

        $response->assertInertia(function (AssertableInertia $page) use ($name, &$field): void {
            $field = collect($page->toArray()['props']['fields'])->firstWhere('name', $name);
        });

        $this->assertIsArray($field, "the form has no field {$name}");

        return $field;
    }

    public function test_up_to_the_threshold_the_form_lists_every_record(): void
    {
        $posts = $this->posts(3);
        $this->actingAs($this->admin(), 'admin');

        $field = $this->formField($this->get('/admin/kits/create')->assertOk(), 'posts');

        $this->assertNull($field['searchUrl']);
        $this->assertSame([], $field['selected']);
        $this->assertSame(array_map(fn (Post $post): int => $post->id, $posts), array_column($field['options'], 'value'));
    }

    public function test_past_the_threshold_the_form_gets_a_search_and_no_list(): void
    {
        $this->posts(4);
        $this->actingAs($this->admin(), 'admin');

        $response = $this->get('/admin/kits/create')->assertOk();

        foreach (['posts', 'post_id'] as $name) {
            $field = $this->formField($response, $name);

            $this->assertSame("/admin/kits/fields/{$name}/options", $field['searchUrl']);
            $this->assertSame([], $field['options']);
            $this->assertSame([], $field['selected']);
        }

        $this->assertStringNotContainsString('Post 04', $response->getContent());
    }

    public function test_the_threshold_counts_only_what_the_field_query_offers(): void
    {
        $this->posts(3);
        $this->makePost('Hidden', active: false);
        $this->actingAs($this->admin(), 'admin');

        $response = $this->get('/admin/kits/create')->assertOk();

        $this->assertNull($this->formField($response, 'posts')['searchUrl']);
        $this->assertNotNull($this->formField($response, 'post_id')['searchUrl']);
    }

    public function test_the_edit_form_names_the_chosen_records_in_their_order(): void
    {
        $posts = $this->posts(30);
        $hidden = $this->makePost('Hidden one', active: false);
        $kit = Kit::query()->create(['name' => 'Kit', 'post_id' => $posts[7]->id]);
        $kit->posts()->attach([$posts[25]->id => ['sort' => 1], $hidden->id => ['sort' => 2], $posts[4]->id => ['sort' => 3]]);
        $this->actingAs($this->admin(), 'admin');

        $response = $this->get("/admin/kits/{$kit->id}/edit")->assertOk();

        $this->assertSame([
            ['value' => $posts[25]->id, 'label' => 'Post 26'],
            ['value' => $hidden->id, 'label' => 'Hidden one'],
            ['value' => $posts[4]->id, 'label' => 'Post 05'],
        ], $this->formField($response, 'posts')['selected']);
        $this->assertSame([['value' => $posts[7]->id, 'label' => 'Post 08']], $this->formField($response, 'post_id')['selected']);
        $this->assertStringNotContainsString('Post 20', $response->getContent());
    }

    public function test_a_form_with_tabs_searches_on_its_tab_too(): void
    {
        $posts = $this->posts(2);
        $crate = Crate::query()->create(['name' => 'Crate', 'post_id' => $posts[1]->id]);
        $this->actingAs($this->admin(), 'admin');

        $this->get("/admin/crates/{$crate->id}/edit")->assertOk()->assertInertia(function (AssertableInertia $page) use ($posts): void {
            $props = $page->toArray()['props'];
            $onTab = collect($props['fieldsByTab']['params'])->firstWhere('name', 'post_id');
            $flat = collect($props['fields'])->firstWhere('name', 'post_id');

            foreach ([$onTab, $flat] as $field) {
                $this->assertSame('/admin/crates/fields/post_id/options', $field['searchUrl']);
                $this->assertSame([], $field['options']);
                $this->assertSame([['value' => $posts[1]->id, 'label' => 'Post 02']], $field['selected']);
            }
        });

        $this->getJson('/admin/crates/fields/post_id/options?q=st%2001')->assertOk()
            ->assertExactJson(['data' => [['value' => $posts[0]->id, 'label' => 'Post 01']], 'more' => false]);
    }

    public function test_a_chosen_record_that_is_gone_is_left_out(): void
    {
        $posts = $this->posts(5);
        $kit = Kit::query()->create(['name' => 'Kit', 'post_id' => 9999]);
        $kit->posts()->attach([$posts[0]->id, 9998]);
        $this->actingAs($this->admin(), 'admin');

        $response = $this->get("/admin/kits/{$kit->id}/edit")->assertOk();

        $this->assertSame([['value' => $posts[0]->id, 'label' => 'Post 01']], $this->formField($response, 'posts')['selected']);
        $this->assertSame([], $this->formField($response, 'post_id')['selected']);
    }

    public function test_the_chosen_records_are_saved_as_before(): void
    {
        $posts = $this->posts(30);
        $kit = Kit::query()->create(['name' => 'Kit']);
        $this->actingAs($this->admin(), 'admin');

        $this->put("/admin/kits/{$kit->id}", ['name' => 'Kit', 'post_id' => $posts[29]->id, 'posts' => [$posts[28]->id, $posts[0]->id]])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame($posts[29]->id, $kit->fresh()?->post_id);
        $this->assertEqualsCanonicalizing([$posts[28]->id, $posts[0]->id], $kit->posts()->pluck('posts.id')->all());
    }

    public function test_the_limit_of_chosen_records_holds_as_before(): void
    {
        $posts = $this->posts(10);
        $kit = Kit::query()->create(['name' => 'Kit']);
        $this->actingAs($this->admin(), 'admin');

        $this->put("/admin/kits/{$kit->id}", ['name' => 'Kit', 'posts' => array_map(fn (Post $post): int => $post->id, array_slice($posts, 0, 6))])
            ->assertSessionHasErrors('posts');

        $this->assertSame(0, $kit->posts()->count());
    }

    public function test_a_form_on_routes_of_its_own_keeps_the_full_list(): void
    {
        foreach (range(1, 51) as $n) {
            AdminRole::query()->create(['name' => "Role {$n}", 'permissions' => []]);
        }

        $admin = $this->admin();
        $this->actingAs($admin, 'admin');

        $field = $this->formField($this->get("/admin/users/{$admin->id}/edit")->assertOk(), 'roles');

        $this->assertNull($field['searchUrl']);
        $this->assertCount(51, $field['options']);
    }
}
