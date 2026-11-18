<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Http\Middleware\ResolveSite;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Support\Sites;
use Atrium\Admin\Tests\Fixtures\Note;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Closure;
use Illuminate\Support\Facades\Route;
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

    public function test_nobody_signed_in_may_not_switch_the_site(): void
    {
        $this->withoutMiddleware();

        $this->post('/admin/site', ['site_id' => $this->main()->id])->assertForbidden();
    }

    public function test_switching_the_site_names_the_site(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->from('/admin/notes')
            ->post('/admin/site', [])
            ->assertSessionHasErrors(['site_id' => 'The site id field is required.']);
    }

    public function test_switching_the_site_goes_back_to_a_page_that_is_not_a_form_of_a_module(): void
    {
        $second = $this->addSecondSite();
        Route::get('/elsewhere/{note}', static fn (): string => 'x')->name('other.admin.notes.edit');
        Route::get('/elsewhere/copy/{note}', static fn (): string => 'x')->name('admin.notes.edit.copy');

        $this->actingAs($this->admin(), 'admin');

        foreach (['/nowhere-at-all', '/elsewhere/1', '/elsewhere/copy/1'] as $page) {
            $this->from($page)->post('/admin/site', ['site_id' => $second->id])->assertRedirect(url($page));
        }
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

    public function test_a_token_of_an_admin_limited_to_one_site_sees_only_that_site(): void
    {
        $second = $this->addSecondSite();
        $main = Note::query()->create(['name' => 'Main note', 'code' => 'a', 'site_id' => $this->main()->id]);
        Note::query()->create(['name' => 'German note', 'code' => 'b', 'site_id' => $second->id]);
        $api = $this->limitedApi([$second]);

        $api()->getJson('/api/v1/notes')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'German note');
        $api()->getJson('/api/v1/notes?site=de')->assertJsonPath('meta.total', 1);
        $api()->getJson('/api/v1/notes?site=')->assertJsonPath('meta.total', 1);
        $api()->getJson('/api/v1/notes?site=main')->assertNotFound()->assertJsonPath('message', 'No site [main].');

        $api()->getJson("/api/v1/notes/{$main->id}")->assertNotFound();
        $api()->putJson("/api/v1/notes/{$main->id}", ['name' => 'Taken', 'code' => 'a'])->assertNotFound();
        $api()->deleteJson("/api/v1/notes/{$main->id}")->assertNotFound();
        $this->assertSame('Main note', $main->fresh()->name, 'Neither changed nor deleted.');
    }

    public function test_a_limited_token_creates_records_on_its_own_site(): void
    {
        $second = $this->addSecondSite();
        $api = $this->limitedApi([$second]);

        $api()->postJson('/api/v1/notes', ['name' => 'Neu', 'code' => 'c'])->assertCreated();
        $api()->postJson('/api/v1/notes?site=main', ['name' => 'Nope', 'code' => 'd'])->assertNotFound();

        $this->assertSame($second->id, Note::query()->withoutGlobalScopes()->where('code', 'c')->sole()->site_id);
        $this->assertFalse(Note::query()->withoutGlobalScopes()->where('code', 'd')->exists());
    }

    public function test_a_limited_token_cannot_move_a_record_to_another_site(): void
    {
        $second = $this->addSecondSite();
        $note = Note::query()->create(['name' => 'German note', 'code' => 'b', 'site_id' => $second->id]);

        $this->limitedApi([$second])()
            ->putJson("/api/v1/notes/{$note->id}", ['name' => 'Moved', 'code' => 'b', 'site_id' => $this->main()->id])
            ->assertOk();

        $this->assertSame($second->id, $note->fresh()->site_id);
    }

    public function test_a_token_limited_to_several_sites_sees_all_of_them_and_no_other(): void
    {
        $second = $this->addSecondSite();
        $third = Site::query()->create(['code' => 'fr', 'name' => 'France', 'domains' => ['example.fr']]);
        Note::query()->create(['name' => 'Main note', 'code' => 'a', 'site_id' => $this->main()->id]);
        Note::query()->create(['name' => 'German note', 'code' => 'b', 'site_id' => $second->id]);
        Note::query()->create(['name' => 'French note', 'code' => 'c', 'site_id' => $third->id]);

        $names = $this->limitedApi([$second, $third])()->getJson('/api/v1/notes')->assertOk()->json('data.*.name');

        sort($names);
        $this->assertSame(['French note', 'German note'], $names);
    }

    public function test_the_limit_of_one_token_does_not_carry_over_to_the_next_request(): void
    {
        $second = $this->addSecondSite();
        Note::query()->create(['name' => 'Main note', 'code' => 'a', 'site_id' => $this->main()->id]);
        Note::query()->create(['name' => 'German note', 'code' => 'b', 'site_id' => $second->id]);
        $limited = $this->limitedApi([$second]);
        $everything = AdminUser::query()->create(['name' => 'All', 'email' => 'all@example.com', 'password' => 'secret-password', 'is_active' => true]);
        $everything->forceFill(['is_superadmin' => true])->save();
        $token = $everything->createToken('agent', ['notes:read'])->plainTextToken;

        $limited()->getJson('/api/v1/notes')->assertJsonPath('meta.total', 1);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/notes')->assertJsonPath('meta.total', 2);
    }

    public function test_an_assistant_of_a_limited_admin_sees_only_its_sites(): void
    {
        $second = $this->addSecondSite();
        $main = Note::query()->create(['name' => 'Main note', 'code' => 'a', 'site_id' => $this->main()->id]);
        Note::query()->create(['name' => 'German note', 'code' => 'b', 'site_id' => $second->id]);
        $token = $this->limitedToken([$second]);
        $call = fn (string $tool, array $arguments) => $this->withToken($token)
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => $arguments]])
            ->assertOk()
            ->json('result');

        $list = $call('list_records', ['module' => 'notes']);
        $this->assertFalse($list['isError']);
        $this->assertSame(1, json_decode($list['content'][0]['text'], true)['meta']['total']);

        $this->assertTrue($call('get_record', ['module' => 'notes', 'id' => $main->id])['isError']);
        $this->assertTrue($call('list_records', ['module' => 'notes', 'site' => 'main'])['isError']);
    }

    /**
     * A token of an administrator who may work only with the given sites.
     *
     * @param  array<int,Site>  $sites
     */
    private function limitedToken(array $sites): string
    {
        $admin = $this->admin();
        $admin->update(['site_ids' => array_map(fn (Site $site): string => (string) $site->id, $sites)]);

        return $admin->createToken('agent', ['notes:read', 'notes:write'])->plainTextToken;
    }

    /**
     * @param  array<int,Site>  $sites
     */
    private function limitedApi(array $sites): Closure
    {
        $token = $this->limitedToken($sites);

        return fn () => $this->withToken($token)->withHeader('Accept', 'application/json');
    }
}
