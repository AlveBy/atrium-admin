<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\SeoMeta;
use Atrium\Admin\Tests\Fixtures\ApiArticle;
use Atrium\Admin\Tests\Fixtures\Chapter;
use Atrium\Admin\Tests\Fixtures\Page;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Topic;
use Atrium\Admin\Tests\TestCase;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;
use ReflectionClass;

/**
 * What a module's form checks and keeps: hand-written unique rules on edit,
 * error names, translations of inactive languages, delete hooks and empty
 * SEO.
 */
final class FormRulesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.locale', 'en');
        $app['config']->set('atrium.resources', [Post::class, Page::class, Topic::class, ApiArticle::class, Chapter::class]);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Sanctum::class))->getFileName(), 2).'/database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->admin(), 'admin');
    }

    private function topic(string $name, ?string $code = null, ?string $slug = null): Topic
    {
        return Topic::query()->create(['name' => $name, 'code' => $code, 'slug' => $slug]);
    }

    public function test_a_unique_rule_written_as_a_string_ignores_the_record_being_edited(): void
    {
        $topic = $this->topic('Mine');
        $this->topic('Theirs');

        $this->put("/admin/topics/{$topic->id}", ['name' => 'Mine'])->assertSessionHasNoErrors();
        $this->put("/admin/topics/{$topic->id}", ['name' => 'Theirs'])->assertSessionHasErrors('name');

        $this->assertSame('Mine', $topic->fresh()->name);
    }

    public function test_a_unique_rule_with_a_null_column_checks_the_field_itself(): void
    {
        $topic = $this->topic('Mine', code: 'mine');
        $this->topic('Theirs', code: 'theirs');

        $this->put("/admin/topics/{$topic->id}", ['name' => 'Mine', 'code' => 'mine'])->assertSessionHasNoErrors();
        $this->put("/admin/topics/{$topic->id}", ['name' => 'Mine', 'code' => 'theirs'])->assertSessionHasErrors('code');
        $this->post('/admin/topics', ['name' => 'New', 'code' => 'theirs'])->assertSessionHasErrors('code');

        $this->assertSame('mine', $topic->fresh()->code);
        $this->assertSame(2, Topic::query()->count());
    }

    public function test_a_unique_rule_that_names_its_own_exception_is_left_as_written(): void
    {
        $topic = $this->topic('Mine', slug: 'mine');

        $this->put("/admin/topics/{$topic->id}", ['name' => 'Mine', 'slug' => 'mine'])->assertSessionHasErrors('slug');
        $this->put("/admin/topics/{$topic->id}", ['name' => 'Mine', 'slug' => 'fresh'])->assertSessionHasNoErrors();

        $this->assertSame('fresh', $topic->fresh()->slug);
    }

    public function test_a_field_without_a_label_keeps_its_own_name_in_errors(): void
    {
        $this->post('/admin/topics', ['name' => 'New', 'note' => 'too long'])
            ->assertSessionHasErrors(['note' => 'The note field must not be greater than 5 characters.']);

        $this->post('/admin/topics', [])
            ->assertSessionHasErrors(['name' => 'The name field is required.']);
    }

    public function test_a_many_to_many_field_with_no_relation_is_dropped_from_the_save(): void
    {
        $post = Post::query()->create(['name' => 'Linked']);

        $this->post('/admin/topics', ['name' => 'New', 'related_posts' => [$post->id]])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('New', Topic::query()->sole()->name);
    }

    public function test_the_edit_form_opens_with_a_many_to_many_field_that_has_no_relation(): void
    {
        $topic = Topic::query()->create(['name' => 'Old']);

        $this->get("/admin/topics/{$topic->id}/edit")->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('item.name', 'Old')->missing('item.related_posts'));
    }

    public function test_the_api_reads_a_module_with_a_many_to_many_field_that_has_no_relation(): void
    {
        $topic = Topic::query()->create(['name' => 'Old']);
        $token = AdminUser::query()->firstOrFail()->createToken('robot', ['topics:read'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/topics')->assertOk()->assertJsonPath('data.0.name', 'Old');
        $this->withToken($token)->getJson("/api/v1/topics/{$topic->id}")->assertOk()->assertJsonPath('data.name', 'Old');
    }

    public function test_the_edit_form_gets_the_address_of_a_record_that_has_one(): void
    {
        $chapter = Chapter::query()->create(['name' => 'Intro']);
        $post = Post::query()->create(['name' => 'Plain']);

        $this->get("/admin/chapters/{$chapter->id}/edit")->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('item.fullPath', "chapters/{$chapter->id}"));
        $this->get("/admin/posts/{$post->id}/edit")->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->missing('item.fullPath'));
    }

    public function test_a_column_of_a_translated_related_record_shows_the_default_language(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        $page = Page::query()->create(['name' => ['en' => 'Home', 'de' => 'Startseite']]);
        Topic::query()->create(['name' => 'Linked', 'page_id' => $page->id]);
        Topic::query()->create(['name' => 'Alone']);

        $rows = $this->get('/admin/topics?sort=id&direction=asc')->assertOk()->viewData('page')['props']['items']['data'];

        $this->assertSame(['Home', null], array_column($rows, 'page.name'));
    }

    public function test_with_one_language_a_translated_field_is_named_without_the_language(): void
    {
        $this->post('/admin/pages', ['name' => ['en' => '']])
            ->assertSessionHasErrors(['name.en' => 'The title field is required.']);
    }

    public function test_with_several_languages_the_error_names_the_language(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);

        $this->post('/admin/pages', ['name' => ['en' => '', 'de' => 'Titel']])
            ->assertSessionHasErrors('name.en');
        $this->assertStringContainsString('(', (string) session('errors')->first('name.en'));
    }

    public function test_translations_of_inactive_languages_are_not_saved(): void
    {
        $page = Page::query()->create(['name' => ['en' => 'Home'], 'code' => ['en' => 'home']]);

        $this->put("/admin/pages/{$page->id}", ['name' => ['en' => 'Home', 'fr' => 'Accueil'], 'code' => ['fr' => 'accueil']])
            ->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertSame(['en' => 'Home'], $page->getTranslations('name'));
        $this->assertSame(['en' => 'home'], $page->getTranslations('code'), 'Only inactive languages sent: the stored ones stay.');
    }

    public function test_a_delete_hook_that_refuses_keeps_the_record(): void
    {
        $kept = ApiArticle::query()->create(['name' => 'Kept']);
        $locked = ApiArticle::query()->create(['name' => 'Locked']);
        $gone = ApiArticle::query()->create(['name' => 'Gone']);

        $this->from('/admin/api_articles')->delete("/admin/api_articles/{$kept->id}")
            ->assertRedirect('/admin/api_articles')->assertSessionHas('error', __('atrium::resource.delete_failed'));
        $this->delete("/admin/api_articles/{$locked->id}")->assertSessionHas('error', __('atrium::resource.cannot_delete'));
        $this->delete("/admin/api_articles/{$gone->id}")->assertSessionMissing('error');

        $this->assertSame(['Kept', 'Locked'], ApiArticle::query()->orderBy('id')->pluck('name')->all());
    }

    public function test_an_empty_seo_tab_does_not_create_a_seo_row(): void
    {
        $this->post('/admin/api_articles', ['name' => 'Bare', 'seo_title' => '', 'seo_description' => null])->assertSessionHasNoErrors();
        $this->assertSame(0, SeoMeta::query()->count());

        $this->post('/admin/api_articles', ['name' => 'Titled', 'seo_title' => 'Titled | Shop'])->assertSessionHasNoErrors();
        $this->assertSame(['Titled | Shop'], SeoMeta::query()->pluck('title')->all());
    }
}
