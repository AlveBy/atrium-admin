<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Api\ApiDocumentation;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Tests\Fixtures\Page;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;
use ReflectionClass;

final class ContentLanguagesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Post::class, Page::class]);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        // API tokens live in Sanctum's table.
        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Sanctum::class))->getFileName(), 2).'/database/migrations');

        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->json('code')->nullable();
            $table->integer('sort')->default(500);
            $table->timestamps();
        });
    }

    protected function defineRoutes($router): void
    {
        Route::localized(function (): void {
            Route::get('pages/{code}', fn (string $code) => app()->getLocale().':'.$code)->name('pages.show');
        });
    }

    private function addGerman(): Language
    {
        return Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
    }

    public function test_a_fresh_install_has_the_app_locale_as_its_only_default_language(): void
    {
        $language = Language::query()->sole();

        $this->assertSame('en', $language->code);
        $this->assertTrue($language->is_default);
        $this->assertSame('en', app(ContentLanguages::class)->default());
        $this->assertFalse(app(ContentLanguages::class)->isMultilingual());
    }

    public function test_languages_are_managed_in_the_admin(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/languages', ['name' => 'Deutsch', 'code' => 'de', 'active' => true, 'is_default' => false, 'sort' => 200])
            ->assertRedirect(route('admin.languages.index'));

        $this->assertSame(['en', 'de'], app(ContentLanguages::class)->codes());

        $this->get('/admin/posts')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('contentLocales.1', ['code' => 'de', 'name' => 'Deutsch', 'default' => false]));
    }

    public function test_a_code_must_look_like_a_language_code_and_be_new(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->post('/admin/languages', ['name' => 'Bad', 'code' => 'DE/x'])->assertSessionHasErrors('code');
        $this->post('/admin/languages', ['name' => 'English again', 'code' => 'en'])->assertSessionHasErrors('code');
        $this->post('/admin/languages', ['name' => 'Português', 'code' => 'pt-br'])->assertSessionDoesntHaveErrors();
    }

    public function test_there_is_always_exactly_one_default_language_and_it_cannot_be_deleted(): void
    {
        $german = $this->addGerman();
        $german->update(['is_default' => true]);

        $this->assertSame('de', Language::query()->where('is_default', true)->sole()->code);

        // Unticking the only default is not possible: it stays the default.
        $german->update(['is_default' => false]);
        $this->assertTrue($german->fresh()->is_default);

        $this->actingAs($this->admin(), 'admin')
            ->delete("/admin/languages/{$german->id}")
            ->assertSessionHas('error');

        $this->assertNotNull($german->fresh());
    }

    public function test_the_code_of_an_existing_language_does_not_change(): void
    {
        $german = $this->addGerman();
        $german->update(['code' => 'at']);

        $this->assertSame('de', $german->fresh()->code);
    }

    public function test_a_translatable_field_is_saved_per_language(): void
    {
        $this->addGerman();

        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/pages', [
                'name' => ['en' => 'Hello', 'de' => 'Hallo', 'fr' => 'Bonjour'],
                'code' => ['en' => 'hello', 'de' => 'hallo'],
            ])
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect(route('admin.pages.index'));

        $page = Page::query()->sole();

        // French is not a content language: not stored.
        $this->assertSame(['en' => 'Hello', 'de' => 'Hallo'], $page->getTranslations('name'));
        $this->assertSame('hallo', $page->getTranslation('code', 'de'));

        // Saving only English keeps the German text.
        $this->put("/admin/pages/{$page->id}", ['name' => ['en' => 'Hello there']])->assertSessionDoesntHaveErrors();
        $this->assertSame(['en' => 'Hello there', 'de' => 'Hallo'], $page->fresh()->getTranslations('name'));
    }

    public function test_only_the_default_language_is_required(): void
    {
        $this->addGerman();
        $this->actingAs($this->admin(), 'admin');

        $this->post('/admin/pages', ['name' => ['en' => '', 'de' => 'Hallo']])
            ->assertSessionHasErrors(['name.en' => 'The title (English) field is required.'])
            ->assertSessionDoesntHaveErrors('name.de');

        $this->post('/admin/pages', ['name' => ['en' => 'Hello', 'de' => '']])->assertSessionDoesntHaveErrors();

        // An untranslated field shows the default language's text.
        app()->setLocale('de');
        $this->assertSame('Hello', Page::query()->sole()->name);
    }

    public function test_a_plain_value_from_an_api_client_is_the_default_language(): void
    {
        $this->addGerman();

        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/pages', ['name' => 'Hello'])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(['en' => 'Hello'], Page::query()->sole()->getTranslations('name'));
    }

    public function test_a_unique_code_is_unique_within_its_language(): void
    {
        $this->addGerman();
        $this->actingAs($this->admin(), 'admin');

        $this->post('/admin/pages', ['name' => ['en' => 'Gift'], 'code' => ['en' => 'gift']])->assertSessionDoesntHaveErrors();

        // "gift" is a word in German too, and that is fine.
        $this->post('/admin/pages', ['name' => ['en' => 'Poison', 'de' => 'Gift'], 'code' => ['en' => 'poison', 'de' => 'gift']])
            ->assertSessionDoesntHaveErrors();

        $this->post('/admin/pages', ['name' => ['en' => 'Present'], 'code' => ['en' => 'gift']])
            ->assertSessionHasErrors('code.en');

        // Saving a page with its own code again is not a clash.
        $poison = Page::query()->latest('id')->firstOrFail();
        $this->put("/admin/pages/{$poison->id}", ['name' => ['en' => 'Poison'], 'code' => ['en' => 'poison', 'de' => 'gift']])
            ->assertSessionDoesntHaveErrors();
    }

    public function test_the_list_shows_the_default_language_and_searches_every_language(): void
    {
        $this->addGerman();
        Page::query()->create(['name' => ['en' => 'Hello', 'de' => 'Hallo']]);
        Page::query()->create(['name' => ['en' => 'Bye', 'de' => 'Tschüss']]);

        $this->actingAs($this->admin(), 'admin')
            ->get('/admin/pages?search=tsch&sort=name&direction=asc')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('items.total', 1)
                ->where('items.data.0.name', 'Bye'));
    }

    public function test_the_form_marks_translatable_fields_and_gets_every_translation(): void
    {
        $this->addGerman();
        $page = Page::query()->create(['name' => ['en' => 'Hello', 'de' => 'Hallo']]);

        $this->actingAs($this->admin(), 'admin')
            ->get("/admin/pages/{$page->id}/edit")
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia
                ->where('fields.0.name', 'name')
                ->where('fields.0.translatable', true)
                ->missing('fields.2.translatable')
                ->where('item.name', ['en' => 'Hello', 'de' => 'Hallo'])
                ->where('itemTitle', 'Hello'));
    }

    public function test_the_api_takes_and_finds_translations(): void
    {
        $this->addGerman();
        $token = $this->admin()->createToken('agent', ['pages:read', 'pages:write'])->plainTextToken;
        $api = fn () => $this->withToken($token)->withHeader('Accept', 'application/json');

        $api()->postJson('/api/v1/pages', ['name' => ['en' => 'Hello', 'de' => 'Hallo'], 'code' => ['en' => 'hello', 'de' => 'hallo']])
            ->assertCreated();

        // A partial update of one language leaves the other alone.
        $id = Page::query()->sole()->id;
        $api()->patchJson("/api/v1/pages/{$id}", ['name' => ['de' => 'Guten Tag']])->assertOk();
        $this->assertSame(['en' => 'Hello', 'de' => 'Guten Tag'], Page::query()->sole()->getTranslations('name'));

        $api()->getJson('/api/v1/pages?filter[code]=hallo')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', ['en' => 'Hello', 'de' => 'Guten Tag']);

        $field = collect(app(ApiDocumentation::class)->modules(['pages'])['pages']['fields'])->firstWhere('name', 'name');
        $this->assertSame(['en', 'de'], array_keys($field['schema']['oneOf'][1]['properties']));
        $this->assertStringContainsString('string per language', app(ApiDocumentation::class)->markdown(['pages']));
    }

    public function test_public_routes_answer_in_every_active_language(): void
    {
        $this->addGerman();
        Language::query()->create(['code' => 'fr', 'name' => 'Français', 'active' => false]);

        $this->get('/pages/hello')->assertOk()->assertSee('en:hello');
        $this->get('/de/pages/hallo')->assertOk()->assertSee('de:hallo');

        // Inactive languages and the default one under a prefix do not exist.
        $this->get('/fr/pages/bonjour')->assertNotFound();
        $this->get('/en/pages/hello')->assertNotFound();

        $languages = app(ContentLanguages::class);
        $this->assertSame(url('/pages/hello'), $languages->route('pages.show', ['code' => 'hello'], 'en'));
        $this->assertSame(url('/de/pages/hallo'), $languages->route('pages.show', ['code' => 'hallo'], 'de'));
    }

    public function test_hreflang_links_name_each_language_and_the_default(): void
    {
        $this->addGerman();
        Language::query()->create(['code' => 'pt-br', 'name' => 'Português']);

        $html = Blade::render('<x-atrium::hreflang :urls="$urls" />', ['urls' => [
            'en' => 'https://example.com/pages/hello',
            'pt-br' => 'https://example.com/pt-br/pages/ola',
        ]]);

        $this->assertStringContainsString('<link rel="alternate" hreflang="en" href="https://example.com/pages/hello">', $html);
        $this->assertStringContainsString('<link rel="alternate" hreflang="pt-BR" href="https://example.com/pt-br/pages/ola">', $html);
        $this->assertStringContainsString('<link rel="alternate" hreflang="x-default" href="https://example.com/pages/hello">', $html);

        // A page in one language only has no alternates.
        $this->assertSame('', trim(Blade::render('<x-atrium::hreflang :urls="$urls" />', ['urls' => ['en' => 'https://example.com/']])));
    }
}
