<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Http\Middleware\ResolveSite;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Support\Sites;
use Atrium\Admin\Tests\Fixtures\Note;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;
use ReflectionClass;

final class SitesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.url', 'http://main.test');
        $app['config']->set('atrium.resources', [Post::class, Note::class]);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Sanctum::class))->getFileName(), 2).'/database/migrations');

        Schema::create('notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->nullable()->index();
            $table->string('name');
            $table->string('code');
            $table->timestamps();
        });
    }

    protected function defineRoutes($router): void
    {
        Route::localized(function (): void {
            Route::get('notes/{code}', fn (string $code) => app()->getLocale().':'.Note::query()->where('code', $code)->firstOrFail()->name);
        });
    }

    private function main(): Site
    {
        return Site::query()->where('code', 'main')->sole();
    }

    private function addSecondSite(array $attributes = []): Site
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);

        return Site::query()->create([
            'code' => 'de',
            'name' => 'Deutschland',
            'domains' => ['example.de', 'www.example.de'],
            'languages' => ['de'],
            ...$attributes,
        ]);
    }

    public function test_a_fresh_install_has_one_default_site_on_the_app_domain(): void
    {
        $site = Site::query()->sole();

        $this->assertSame('main', $site->code);
        $this->assertTrue($site->is_default);
        $this->assertSame(['main.test'], $site->domains);
        $this->assertFalse(app(Sites::class)->isMultisite());
    }

    public function test_the_site_is_picked_by_domain(): void
    {
        $second = $this->addSecondSite();
        Note::query()->create(['name' => 'Hallo', 'code' => 'hello', 'site_id' => $second->id]);
        Note::query()->create(['name' => 'Hello', 'code' => 'hello', 'site_id' => $this->main()->id]);

        $this->get('http://www.example.de/notes/hello')->assertOk()->assertSee('de:Hallo');
        $this->get('http://main.test/notes/hello')->assertOk()->assertSee('en:Hello');

        // A domain no site claims gets the default one.
        $this->get('http://unknown.test/notes/hello')->assertOk()->assertSee('en:Hello');
    }

    public function test_a_record_of_one_site_is_not_found_on_another(): void
    {
        $second = $this->addSecondSite();
        Note::query()->create(['name' => 'Nur hier', 'code' => 'only', 'site_id' => $second->id]);

        $this->get('http://example.de/notes/only')->assertOk();
        $this->get('http://main.test/notes/only')->assertNotFound();
    }

    public function test_an_inactive_site_is_not_served(): void
    {
        $this->addSecondSite(['active' => false]);
        Note::query()->create(['name' => 'Hello', 'code' => 'hello', 'site_id' => $this->main()->id]);

        $this->get('http://example.de/notes/hello')->assertOk()->assertSee('en:Hello');
    }

    public function test_the_site_sets_its_languages(): void
    {
        $this->addSecondSite();
        Language::query()->create(['code' => 'fr', 'name' => 'Français', 'sort' => 300]);
        Note::query()->create(['name' => 'Hello', 'code' => 'hello', 'site_id' => $this->main()->id]);

        // The main site lists no languages: all of them, the panel's default first.
        $this->assertSame(['en', 'de', 'fr'], app(ContentLanguages::class)->codes());

        // The German site has only German, so it is the default and has no prefix…
        $second = Site::query()->where('code', 'de')->sole();
        Note::query()->create(['name' => 'Hallo', 'code' => 'hallo', 'site_id' => $second->id]);
        $this->get('http://example.de/notes/hallo')->assertOk()->assertSee('de:Hallo');
        $this->get('http://example.de/fr/notes/hallo')->assertNotFound();

        // …while on the main site German is one of the prefixed languages.
        $this->get('http://main.test/de/notes/hello')->assertOk()->assertSee('de:Hello');
    }

    public function test_a_site_may_put_another_language_first(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        $this->main()->update(['languages' => ['en', 'de'], 'default_language' => 'de']);
        Note::query()->create(['name' => 'Hallo', 'code' => 'hallo', 'site_id' => $this->main()->id]);

        $this->get('http://main.test/notes/hallo')->assertSee('de:Hallo');
        $this->get('http://main.test/en/notes/hallo')->assertSee('en:Hallo');
        $this->get('http://main.test/de/notes/hallo')->assertNotFound();
    }

    public function test_domains_are_normalized_and_cannot_be_shared(): void
    {
        $site = $this->addSecondSite(['domains' => ['https://Example.DE:8443/path', 'example.de', ' ']]);
        $this->assertSame(['example.de'], $site->domains);

        try {
            $this->main()->update(['domains' => ['main.test', 'example.de']]);
            $this->fail('A domain of another site was accepted.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('example.de', $e->errors()['domains'][0]);
        }

        $this->expectException(ValidationException::class);
        $this->main()->update(['domains' => ['not a domain']]);
    }

    public function test_there_is_always_one_default_site_and_it_cannot_be_deleted(): void
    {
        $second = $this->addSecondSite(['is_default' => true]);

        $this->assertFalse($this->main()->is_default);
        $this->assertTrue($second->fresh()->is_default);
        $this->assertFalse($second->canDelete());
        $this->assertTrue($this->main()->canDelete());

        $this->actingAs($this->admin(), 'admin')
            ->delete("/admin/sites/{$second->id}")
            ->assertRedirect();
        $this->assertNotNull($second->fresh());
    }

    public function test_sites_are_managed_in_the_admin(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);

        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/sites', [
                'name' => 'Deutschland', 'code' => 'de', 'domains' => ['example.de'],
                'languages' => ['de'], 'default_language' => '', 'active' => true, 'is_default' => false, 'sort' => 200,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.sites.index'));

        $site = Site::query()->where('code', 'de')->sole();
        $this->assertSame(['example.de'], $site->domains);
        $this->assertSame('de', $site->default_language);

        $this->post('/admin/sites', ['name' => 'Bad', 'code' => 'bad', 'domains' => ['example.de']])
            ->assertSessionHasErrors('domains');
    }

    public function test_the_admin_works_with_the_site_picked_in_the_header(): void
    {
        $second = $this->addSecondSite();
        $admin = $this->admin();
        Note::query()->create(['name' => 'Main note', 'code' => 'a', 'site_id' => $this->main()->id]);
        Note::query()->create(['name' => 'German note', 'code' => 'b', 'site_id' => $second->id]);

        $this->actingAs($admin, 'admin')->get('/admin/notes')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('currentSite.id', $this->main()->id)
            ->has('sites', 2)
            ->where('items.total', 1)
            ->where('items.data.0.name', 'Main note'));

        $this->from('/admin/notes')->post('/admin/site', ['site_id' => $second->id])->assertRedirect('/admin/notes');

        $this->get('/admin/notes')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('currentSite.id', $second->id)
            // Its first domain, with the scheme and port the admin is opened on.
            ->where('currentSite.url', fn (string $url) => preg_match('#^http://example\.de(:\d+)?$#', $url) === 1)
            ->where('items.total', 1)
            ->where('items.data.0.name', 'German note'));

        // A record is created on the picked site.
        $this->post('/admin/notes', ['name' => 'Neu', 'code' => 'c'])->assertSessionHasNoErrors();
        $this->assertSame($second->id, Note::query()->withoutGlobalScopes()->where('code', 'c')->sole()->site_id);

        // The other site's record is not reachable from here.
        $other = Note::query()->withoutGlobalScopes()->where('code', 'a')->sole();
        $this->get("/admin/notes/{$other->id}/edit")->assertNotFound();
    }

    public function test_switching_the_site_from_a_form_goes_to_the_list(): void
    {
        $second = $this->addSecondSite();

        $this->actingAs($this->admin(), 'admin')
            ->from('/admin/notes/create')
            ->post('/admin/site', ['site_id' => $second->id])
            ->assertRedirect(route('admin.notes.index'));
    }

    public function test_a_code_is_unique_within_its_site(): void
    {
        $second = $this->addSecondSite();
        Note::query()->create(['name' => 'Main', 'code' => 'same', 'site_id' => $this->main()->id]);

        $this->actingAs($this->admin(), 'admin')
            ->withSession([ResolveSite::SESSION_KEY => $second->id])
            ->post('/admin/notes', ['name' => 'German', 'code' => 'same'])
            ->assertSessionHasNoErrors();

        $this->post('/admin/notes', ['name' => 'Again', 'code' => 'same'])->assertSessionHasErrors('code');
    }

    public function test_an_admin_can_be_limited_to_some_sites(): void
    {
        $second = $this->addSecondSite();
        $admin = $this->admin();
        $admin->update(['site_ids' => [(string) $second->id]]);
        Note::query()->create(['name' => 'Main note', 'code' => 'a', 'site_id' => $this->main()->id]);

        // Even with the main site in the session, only the allowed one is shown.
        $this->actingAs($admin, 'admin')
            ->withSession([ResolveSite::SESSION_KEY => $this->main()->id])
            ->get('/admin/notes')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('currentSite.id', $second->id)
                ->has('sites', 1)
                ->where('items.total', 0));

        $this->post('/admin/site', ['site_id' => $this->main()->id])->assertSessionHasErrors('site_id');
    }

    public function test_the_site_limit_is_set_on_the_admin_user_form(): void
    {
        $second = $this->addSecondSite();
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->get("/admin/users/{$admin->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where(
                'fields',
                fn ($fields) => collect($fields)->contains('name', 'site_ids'),
            ));

        $this->put("/admin/users/{$admin->id}", [
            'name' => 'Admin', 'email' => 'admin@example.com', 'is_active' => true, 'site_ids' => [(string) $second->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame([(string) $second->id], $admin->fresh()->site_ids);
    }

    public function test_the_api_works_on_every_site_or_on_one(): void
    {
        $second = $this->addSecondSite();
        Note::query()->create(['name' => 'Main note', 'code' => 'a', 'site_id' => $this->main()->id]);
        Note::query()->create(['name' => 'German note', 'code' => 'b', 'site_id' => $second->id]);

        $token = $this->admin()->createToken('agent', ['notes:read', 'notes:write'])->plainTextToken;
        $api = fn () => $this->withToken($token)->withHeader('Accept', 'application/json');

        $api()->getJson('/api/v1/notes')->assertJsonPath('meta.total', 2);
        $api()->getJson('/api/v1/notes?site=de')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'German note');
        $api()->getJson('/api/v1/notes?site=nope')->assertNotFound();

        $api()->postJson('/api/v1/notes?site=de', ['name' => 'Neu', 'code' => 'c'])->assertCreated();
        $this->assertSame($second->id, Note::query()->withoutGlobalScopes()->where('code', 'c')->sole()->site_id);
    }
}
