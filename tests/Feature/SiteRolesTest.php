<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Http\Middleware\ResolveSite;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\Sites;
use Atrium\Admin\Tests\Fixtures\Note;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;
use ReflectionClass;

/**
 * Roles on one site: an administrator edits one site and only looks at
 * another.
 */
final class SiteRolesTest extends TestCase
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

    public function test_a_role_on_one_site_counts_only_while_that_site_is_picked(): void
    {
        $second = $this->addSecondSite();
        $editor = $this->editor();
        $editor->roles()->attach($this->role('Reader', ['notes.view']));
        $editor->syncSiteRoles([$second->id => [$this->role('Editor', ['notes.view', 'notes.update'])->id]]);
        $main = Note::query()->create(['name' => 'Main note', 'code' => 'a', 'site_id' => $this->main()->id]);
        $german = Note::query()->create(['name' => 'German note', 'code' => 'b', 'site_id' => $second->id]);

        // On the main site only the role on every site counts: reading.
        $this->actingAs($editor, 'admin')->get('/admin/notes')->assertOk();
        $this->put("/admin/notes/{$main->id}", ['name' => 'Renamed', 'code' => 'a'])->assertForbidden();
        $this->assertSame('Main note', $main->fresh()->name);

        // On the second site its own role counts as well: editing.
        $this->withSession([ResolveSite::SESSION_KEY => $second->id])
            ->put("/admin/notes/{$german->id}", ['name' => 'Umbenannt', 'code' => 'b'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Umbenannt', Note::query()->withoutGlobalScopes()->findOrFail($german->id)->name);
    }

    public function test_the_menu_follows_the_picked_site(): void
    {
        $second = $this->addSecondSite();
        $editor = $this->editor();
        $editor->syncSiteRoles([$second->id => [$this->role('Posts', ['posts.view'])->id]]);

        $this->actingAs($editor, 'admin')->get('/admin')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('menu', fn ($menu) => ! str_contains(json_encode($menu), 'admin.posts.index')));

        $this->withSession([ResolveSite::SESSION_KEY => $second->id])->get('/admin')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('menu', fn ($menu) => str_contains(json_encode($menu), 'admin.posts.index')));
    }

    public function test_an_api_call_gets_a_site_s_roles_only_when_it_names_the_site(): void
    {
        $second = $this->addSecondSite();
        $editor = $this->editor();
        $editor->syncSiteRoles([$second->id => [$this->role('Reader', ['notes.view'])->id]]);
        Note::query()->create(['name' => 'German note', 'code' => 'b', 'site_id' => $second->id]);
        $token = $editor->createToken('agent', ['notes:read'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/notes')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/notes?site=main')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/notes?site=de')->assertOk()->assertJsonPath('data.0.name', 'German note');
    }

    public function test_a_role_on_every_site_counts_without_a_site_too(): void
    {
        $editor = $this->editor();
        $editor->roles()->attach($this->role('Reader', ['notes.view']));

        $this->assertSame(['notes.view'], $editor->adminPermissions());
        $this->assertFalse(app(Sites::class)->isResolved());
    }

    public function test_a_superadmin_sets_the_roles_of_each_site_on_the_admin_form(): void
    {
        $second = $this->addSecondSite();
        $writer = $this->role('Writer', ['notes.update']);
        $reader = $this->role('Reader', ['notes.view']);
        $editor = $this->editor();
        $editor->syncSiteRoles([$second->id => [$reader->id]]);

        $this->actingAs($this->admin(), 'admin')
            ->get("/admin/users/{$editor->id}/edit")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('item.roles_by_site', [(string) $second->id => [$reader->id]])
                ->where('fields', function ($fields) use ($second, $writer, $reader): bool {
                    $field = collect($fields)->firstWhere('name', 'roles_by_site');

                    $this->assertSame('siteRoles', $field['type']);
                    $this->assertSame('Roles on one site', $field['label']);
                    $this->assertStringStartsWith('They count only while that site is chosen', $field['hint']);
                    $this->assertSame([['value' => $this->main()->id, 'label' => $this->main()->name], ['value' => $second->id, 'label' => 'Deutschland']], $field['sites']);
                    // By name.
                    $this->assertSame([['value' => $reader->id, 'label' => 'Reader'], ['value' => $writer->id, 'label' => 'Writer']], $field['roles']);

                    return true;
                }));

        $this->put("/admin/users/{$editor->id}", [
            'name' => 'Editor', 'email' => 'editor@example.com', 'is_active' => true,
            'roles_by_site' => [$this->main()->id => [$reader->id], $second->id => [$writer->id, $reader->id]],
        ])->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing([$this->main()->id => [$reader->id], $second->id => [$writer->id, $reader->id]], $editor->fresh()->roles_by_site);

        // A site left empty loses its roles.
        $this->put("/admin/users/{$editor->id}", [
            'name' => 'Editor', 'email' => 'editor@example.com', 'is_active' => true,
            'roles_by_site' => [$this->main()->id => null, $second->id => [$writer->id]],
        ])->assertSessionHasNoErrors();

        $this->assertSame([$second->id => [$writer->id]], $editor->fresh()->roles_by_site);
    }

    public function test_a_form_without_the_field_keeps_the_roles_of_each_site(): void
    {
        $second = $this->addSecondSite();
        $editor = $this->editor();
        $editor->syncSiteRoles([$second->id => [$this->role('Reader', ['notes.view'])->id]]);

        $this->actingAs($this->admin(), 'admin')->put("/admin/users/{$editor->id}", [
            'name' => 'Renamed', 'email' => 'editor@example.com', 'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $editor->fresh()->name);
        $this->assertCount(1, $editor->fresh()->roles_by_site[$second->id]);
    }

    public function test_a_new_administrator_gets_roles_on_a_site(): void
    {
        $second = $this->addSecondSite();
        $reader = $this->role('Reader', ['notes.view']);

        $this->actingAs($this->admin(), 'admin')
            ->get('/admin/users/create')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('fields', fn ($fields) => collect($fields)->contains('name', 'roles_by_site')));

        $this->post('/admin/users', [
            'name' => 'New', 'email' => 'new@example.com', 'password' => 'secret-password', 'password_confirmation' => 'secret-password',
            'is_active' => true, 'roles_by_site' => [$second->id => [$reader->id]],
        ])->assertSessionHasNoErrors();

        $this->assertSame([$second->id => [$reader->id]], AdminUser::query()->where('email', 'new@example.com')->sole()->roles_by_site);
    }

    public function test_the_form_takes_only_sites_and_roles_that_are_there(): void
    {
        $second = $this->addSecondSite();
        $reader = $this->role('Reader', ['notes.view']);
        $editor = $this->editor();
        $this->actingAs($this->admin(), 'admin');
        $data = ['name' => 'Editor', 'email' => 'editor@example.com', 'is_active' => true];

        $this->put("/admin/users/{$editor->id}", [...$data, 'roles_by_site' => [$second->id + 100 => [$reader->id]]])
            ->assertSessionHasErrors('roles_by_site');
        $this->put("/admin/users/{$editor->id}", [...$data, 'roles_by_site' => [$second->id => [$reader->id + 100]]])
            ->assertSessionHasErrors("roles_by_site.{$second->id}.0");
        $this->put("/admin/users/{$editor->id}", [...$data, 'roles_by_site' => [$second->id => 'all']])
            ->assertSessionHasErrors("roles_by_site.{$second->id}");
        $this->put("/admin/users/{$editor->id}", [...$data, 'roles_by_site' => 'all'])
            ->assertSessionHasErrors('roles_by_site');

        $this->assertSame([], $editor->fresh()->roles_by_site);
    }

    public function test_only_a_superadmin_hands_out_the_roles_of_a_site(): void
    {
        $second = $this->addSecondSite();
        $reader = $this->role('Reader', ['notes.view']);
        $manager = $this->editor('manager@example.com');
        $manager->roles()->attach($this->role('Managers', ['users.view', 'users.update']));
        $editor = $this->editor();

        $this->actingAs($manager->fresh(), 'admin')
            ->get("/admin/users/{$editor->id}/edit")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->missing('item.roles_by_site')
                ->where('fields', fn ($fields) => ! collect($fields)->contains('name', 'roles_by_site')));

        $this->put("/admin/users/{$editor->id}", [
            'name' => 'Editor', 'email' => 'editor@example.com', 'is_active' => true,
            'roles_by_site' => [$second->id => [$reader->id]],
        ])->assertSessionHasNoErrors();

        $this->assertSame([], $editor->fresh()->roles_by_site);
    }

    public function test_with_one_site_there_are_no_roles_of_a_site(): void
    {
        $reader = $this->role('Reader', ['notes.view']);
        $editor = $this->editor();

        $this->actingAs($this->admin(), 'admin')
            ->get("/admin/users/{$editor->id}/edit")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->missing('item.roles_by_site')
                ->where('fields', fn ($fields) => ! collect($fields)->contains('name', 'roles_by_site')));

        $this->put("/admin/users/{$editor->id}", [
            'name' => 'Editor', 'email' => 'editor@example.com', 'is_active' => true,
            'roles_by_site' => [$this->main()->id => [$reader->id]],
        ])->assertSessionHasNoErrors();

        $this->assertSame([], $editor->fresh()->roles_by_site);
    }

    public function test_syncing_replaces_the_roles_and_skips_what_is_not_there(): void
    {
        $second = $this->addSecondSite();
        $reader = $this->role('Reader', ['notes.view']);
        $writer = $this->role('Writer', ['notes.update']);
        $editor = $this->editor();
        $other = $this->editor('other@example.com');
        $other->syncSiteRoles([$second->id => [$reader->id]]);

        app(Sites::class)->setCurrent($this->main());
        $this->assertSame([], $editor->adminPermissions());

        $editor->syncSiteRoles([$this->main()->id => [$writer->id]]);
        $this->assertSame([$this->main()->id => [$writer->id]], $editor->roles_by_site);
        // The roles read before are read again.
        $this->assertSame(['notes.update'], $editor->adminPermissions());

        // A site that is gone comes first and is skipped, the rest is kept;
        // the same role twice is one row; an empty site has none.
        $editor->syncSiteRoles([$second->id + 100 => [$reader->id], $second->id => [$reader->id, $reader->id], $this->main()->id => null]);

        $this->assertSame([$second->id => [$reader->id]], $editor->roles_by_site);
        $this->assertSame([$second->id => [$reader->id]], $editor->fresh()->roles_by_site);
        $this->assertSame(1, DB::table('admin_role_site_user')->where('admin_user_id', $editor->id)->count());

        // Another administrator's roles are not touched.
        $this->assertSame([$second->id => [$reader->id]], $other->fresh()->roles_by_site);
    }

    private function main(): Site
    {
        return Site::query()->where('code', 'main')->sole();
    }

    private function addSecondSite(): Site
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        app(Sites::class)->flush();

        return Site::query()->create(['code' => 'de', 'name' => 'Deutschland', 'domains' => ['example.de'], 'languages' => ['de']]);
    }

    /**
     * @param  array<int,string>  $permissions
     */
    private function role(string $name, array $permissions): AdminRole
    {
        return AdminRole::query()->create(['name' => $name, 'permissions' => $permissions]);
    }

    private function editor(string $email = 'editor@example.com'): AdminUser
    {
        return AdminUser::query()->create(['name' => 'Editor', 'email' => $email, 'password' => 'secret-password', 'is_active' => true]);
    }
}
