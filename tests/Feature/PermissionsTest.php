<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Auth\Permissions;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Tests\Concerns\PinsExtensionSurface;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;

final class PermissionsTest extends TestCase
{
    use PinsExtensionSurface;

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Sanctum::class))->getFileName(), 2).'/database/migrations');
    }

    /**
     * @return array<string,array{0:string,1:string,2:string}>
     */
    public static function panelActions(): array
    {
        return [
            'list' => ['get', '/admin/posts', 'posts.view'],
            'open a record' => ['get', '/admin/posts/{id}/edit', 'posts.view'],
            'create form' => ['get', '/admin/posts/create', 'posts.create'],
            'store' => ['post', '/admin/posts', 'posts.create'],
            'update' => ['put', '/admin/posts/{id}', 'posts.update'],
            'destroy' => ['delete', '/admin/posts/{id}', 'posts.delete'],
        ];
    }

    #[DataProvider('panelActions')]
    public function test_a_panel_action_is_allowed_with_its_permission(string $method, string $url, string $permission): void
    {
        $response = $this->panel($this->editor([$permission]), $method, $url);

        $this->assertNotSame(403, $response->getStatusCode());
        $this->assertLessThan(400, $response->getStatusCode());
    }

    #[DataProvider('panelActions')]
    public function test_a_panel_action_is_refused_without_its_permission(string $method, string $url, string $permission): void
    {
        $everythingElse = array_values(array_diff(app(Permissions::class)->all(), [$permission]));

        $this->panel($this->editor($everythingElse), $method, $url)->assertForbidden();
        $this->assertSame(1, Post::query()->count());
        $this->assertSame('Hello', Post::query()->sole()->name);
    }

    public function test_the_menu_shows_only_what_the_roles_allow(): void
    {
        $routes = $this->menuRoutes($this->actingAs($this->editor(['posts.view']), 'admin')->get('/admin'));

        $this->assertContains('admin.dashboard', $routes);
        $this->assertContains('admin.posts.index', $routes);
        $this->assertNotContains('admin.users.index', $routes);
        $this->assertNotContains('admin.roles.index', $routes);
        $this->assertNotContains('admin.sites.index', $routes);
        $this->assertNotContains('admin.api-tokens.index', $routes);
    }

    public function test_a_superadmin_sees_the_whole_menu(): void
    {
        $routes = $this->menuRoutes($this->actingAs($this->admin(), 'admin')->get('/admin'));

        foreach (['admin.posts.index', 'admin.users.index', 'admin.roles.index', 'admin.sites.index'] as $route) {
            $this->assertContains($route, $routes);
        }
    }

    public function test_roles_add_up(): void
    {
        $user = $this->editor(['posts.view']);
        $user->roles()->attach(AdminRole::query()->create(['name' => 'Writer', 'permissions' => ['posts.update']]));

        $this->panel($user->fresh(), 'put', '/admin/posts/{id}')->assertRedirect();
        $this->assertSame('Renamed', Post::query()->sole()->name);
    }

    public function test_pages_get_only_the_buttons_the_roles_allow(): void
    {
        $user = $this->editor(['posts.view']);
        $post = Post::query()->create(['name' => 'Hello']);

        $this->actingAs($user, 'admin')
            ->get('/admin/posts')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('can', ['view' => true, 'create' => false, 'update' => false, 'delete' => false]));

        // Read-only card: the record opens, the form knows it cannot save.
        $this->get("/admin/posts/{$post->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Resource/Edit')
                ->where('item.name', 'Hello')
                ->where('can.update', false));
    }

    public function test_roles_are_managed_by_superadmins_only(): void
    {
        $role = AdminRole::query()->create(['name' => 'Editor', 'permissions' => []]);
        $everything = $this->editor(app(Permissions::class)->all());

        $this->actingAs($everything, 'admin')->get('/admin/roles')->assertForbidden();
        $this->post('/admin/roles', ['name' => 'Mine', 'permissions' => ['users.update']])->assertForbidden();
        $this->put("/admin/roles/{$role->id}", ['name' => 'Editor', 'permissions' => app(Permissions::class)->all()])->assertForbidden();
        $this->delete("/admin/roles/{$role->id}")->assertForbidden();

        $this->assertSame([], AdminRole::query()->findOrFail($role->id)->permissions);
        $this->assertFalse(AdminRole::query()->where('name', 'Mine')->exists());
    }

    public function test_a_role_holding_the_routers_own_markers_gets_nothing_from_them(): void
    {
        $permissions = app(Permissions::class);
        $user = $this->editor(['*superadmin', '*write', '*settings']);

        $this->assertFalse($permissions->allowsRoute($user, 'admin.roles.index'));
        $this->assertFalse($permissions->allowsRoute($user, 'admin.upload'));
        $this->assertFalse($permissions->canWriteAnything($user));
    }

    /**
     * @return array<string,array{0:string,1:bool}>
     */
    public static function writeEndings(): array
    {
        return [
            'create' => ['posts.create', true],
            'update' => ['posts.update', true],
            'settings' => ['labels.settings', true],
            'view' => ['posts.view', false],
            'delete' => ['posts.delete', false],
            'create without a dot' => ['postscreate', false],
            'update without a dot' => ['postsupdate', false],
            'settings without a dot' => ['labelssettings', false],
        ];
    }

    #[DataProvider('writeEndings')]
    public function test_writing_anything_needs_a_permission_to_create_update_or_set_up_something(string $permission, bool $can): void
    {
        $this->assertSame($can, app(Permissions::class)->canWriteAnything($this->editor([$permission])));
    }

    public function test_a_module_keeps_its_screens_for_superadmins(): void
    {
        // Empty and non-string prefixes are skipped: '' would close every screen.
        config(['atrium.superadmin_routes' => ['', 42, 'admin.backups.']]);
        $permissions = app(Permissions::class);
        $everything = $this->editor($permissions->all());

        $this->assertFalse($permissions->allowsRoute($everything, 'admin.backups.index'));
        $this->assertTrue($permissions->allowsRoute($this->admin(), 'admin.backups.index'));
        $this->assertTrue($permissions->allowsRoute($everything, 'admin.backupsx'));
        $this->assertTrue($permissions->allowsRoute($everything, 'admin.dashboard'));
        $this->assertTrue($permissions->allowsRoute($everything, 'admin.posts.index'));

        // One prefix written as a string, not a list.
        config(['atrium.superadmin_routes' => 'admin.backups.']);
        $this->assertFalse($permissions->allowsRoute($everything, 'admin.backups.index'));
    }

    public function test_a_superadmin_creates_edits_and_deletes_a_role(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get('/admin/roles/create')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Roles/Edit')
                ->where('sections', fn ($sections) => collect($sections)->pluck('key')->contains('posts')
                    && collect($sections)->pluck('key')->contains('users')
                    && collect($sections)->pluck('key')->last() === 'api-tokens'));

        $this->post('/admin/roles', ['name' => 'Editor', 'permissions' => ['posts.view', 'posts.update']])
            ->assertRedirect(route('admin.roles.index'));

        $role = AdminRole::query()->sole();
        $this->assertSame(['posts.view', 'posts.update'], $role->permissions);

        $this->get('/admin/roles')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Roles/Index')
            ->where('roles.0.name', 'Editor')
            ->where('roles.0.permissions_count', 2));

        $this->from("/admin/roles/{$role->id}/edit")
            ->put("/admin/roles/{$role->id}", ['name' => 'Editor', 'permissions' => ['posts.fly']])
            ->assertSessionHasErrors('permissions.0');

        $this->put("/admin/roles/{$role->id}", ['name' => 'Editor', 'permissions' => []])->assertRedirect();
        $this->assertSame([], $role->fresh()->permissions);

        $this->delete("/admin/roles/{$role->id}")->assertRedirect();
        $this->assertSame(0, AdminRole::query()->count());
    }

    public function test_a_superadmin_is_out_of_reach_for_other_administrators(): void
    {
        $boss = $this->admin();
        $manager = $this->editor(['users.view', 'users.update', 'users.delete']);

        $this->actingAs($manager, 'admin')->get("/admin/users/{$boss->id}/edit")->assertForbidden();
        $this->put("/admin/users/{$boss->id}", ['name' => 'Hacked', 'email' => $boss->email, 'password' => 'new-password', 'password_confirmation' => 'new-password'])
            ->assertForbidden();
        $this->delete("/admin/users/{$boss->id}")->assertForbidden();

        $this->assertSame('Admin', $boss->fresh()->name);
    }

    public function test_only_a_superadmin_hands_out_roles_and_the_superadmin_flag(): void
    {
        $this->admin();
        $role = AdminRole::query()->create(['name' => 'Everything', 'permissions' => app(Permissions::class)->all()]);
        $manager = $this->editor(['users.view', 'users.update']);
        $other = $this->editor([], 'other@example.com');

        $payload = ['name' => 'Other', 'email' => 'other@example.com', 'is_active' => true, 'is_superadmin' => true, 'roles' => [$role->id]];

        $this->actingAs($manager, 'admin')->put("/admin/users/{$other->id}", $payload)->assertRedirect();
        $this->assertFalse($other->fresh()->isSuperadmin());
        $this->assertFalse($other->fresh()->roles->contains($role));

        // Not even for themselves.
        $this->put("/admin/users/{$manager->id}", ['email' => $manager->email] + $payload)->assertRedirect();
        $this->assertFalse($manager->fresh()->isSuperadmin());
        $this->assertFalse($manager->fresh()->roles->contains($role));
    }

    public function test_a_superadmin_hands_out_roles_and_the_superadmin_flag(): void
    {
        $role = AdminRole::query()->create(['name' => 'Editor', 'permissions' => ['posts.view']]);
        $other = $this->editor([], 'other@example.com');

        $this->actingAs($this->admin(), 'admin')
            ->get("/admin/users/{$other->id}/edit")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('fields', fn ($fields) => collect($fields)->pluck('name')->contains('roles')
                    && collect($fields)->pluck('name')->contains('is_superadmin')));

        $this->put("/admin/users/{$other->id}", ['name' => 'Other', 'email' => 'other@example.com', 'is_active' => true, 'roles' => [$role->id]])
            ->assertRedirect();
        $this->assertSame([$role->id], $other->fresh()->roles->pluck('id')->all());
        $this->assertFalse($other->fresh()->isSuperadmin());

        $this->put("/admin/users/{$other->id}", ['name' => 'Other', 'email' => 'other@example.com', 'is_active' => true, 'is_superadmin' => true, 'roles' => [$role->id]])
            ->assertRedirect();
        $this->assertTrue($other->fresh()->isSuperadmin());
    }

    public function test_a_superadmin_creates_an_administrator_without_touching_the_superadmin_box(): void
    {
        // What the create form sends when nobody touched the box or the roles:
        // every field starts as its default or an empty string.
        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/users', [
                'name' => 'New',
                'email' => 'new@example.com',
                'password' => 'secret-password',
                'password_confirmation' => 'secret-password',
                'is_active' => true,
                'is_superadmin' => '',
                'roles' => '',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertFalse(AdminUser::query()->where('email', 'new@example.com')->sole()->isSuperadmin());
    }

    public function test_the_last_superadmin_cannot_be_demoted_switched_off_or_deleted(): void
    {
        $boss = $this->admin();
        $this->actingAs($boss, 'admin');

        $form = ['name' => 'Admin', 'email' => $boss->email];

        $this->from("/admin/users/{$boss->id}/edit")
            ->put("/admin/users/{$boss->id}", $form + ['is_active' => true, 'is_superadmin' => false])
            ->assertSessionHasErrors('is_superadmin');
        $this->from("/admin/users/{$boss->id}/edit")
            ->put("/admin/users/{$boss->id}", $form + ['is_active' => false, 'is_superadmin' => true])
            ->assertSessionHasErrors('is_superadmin');
        $this->delete("/admin/users/{$boss->id}")->assertSessionHas('error');

        $boss->refresh();
        $this->assertTrue($boss->isSuperadmin());
        $this->assertTrue($boss->is_active);

        // With a second one around, it can.
        $this->editor([], 'second@example.com')->forceFill(['is_superadmin' => true])->save();

        $this->put("/admin/users/{$boss->id}", $form + ['is_active' => true, 'is_superadmin' => false])->assertSessionHasNoErrors();
        $this->assertFalse($boss->fresh()->isSuperadmin());
    }

    /**
     * @return array<string,array{0:string,1:string,2:string}>
     */
    public static function apiActions(): array
    {
        return [
            'list' => ['getJson', '/api/v1/posts', 'posts.view'],
            'show' => ['getJson', '/api/v1/posts/{id}', 'posts.view'],
            'store' => ['postJson', '/api/v1/posts', 'posts.create'],
            'update' => ['putJson', '/api/v1/posts/{id}', 'posts.update'],
            'destroy' => ['deleteJson', '/api/v1/posts/{id}', 'posts.delete'],
        ];
    }

    #[DataProvider('apiActions')]
    public function test_an_api_action_is_allowed_when_the_owner_has_the_permission(string $method, string $url, string $permission): void
    {
        $response = $this->api($this->editor([$permission]), $method, $url);

        $this->assertLessThan(400, $response->getStatusCode(), (string) $response->getContent());
    }

    #[DataProvider('apiActions')]
    public function test_an_api_action_is_refused_when_the_owner_lacks_the_permission(string $method, string $url, string $permission): void
    {
        $everythingElse = array_values(array_diff(app(Permissions::class)->all(), [$permission]));

        $this->api($this->editor($everythingElse), $method, $url)
            ->assertForbidden()
            ->assertJsonPath('message', 'The token owner lacks the '.$permission.' permission.');
        $this->assertSame('Hello', Post::query()->sole()->name);
    }

    public function test_a_token_loses_what_its_owner_loses(): void
    {
        $owner = $this->editor(['posts.view', 'posts.update']);
        $token = $owner->createToken('agent', ['posts:read', 'posts:write'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/posts')->assertOk();

        $owner->roles()->sole()->update(['permissions' => []]);
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/posts')->assertForbidden();
    }

    public function test_uploads_need_an_owner_who_may_create_or_edit_something(): void
    {
        $token = $this->editor(['posts.view'])->createToken('agent', ['posts:read', 'posts:write'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/files', [])
            ->assertForbidden()
            ->assertJsonPath('message', 'The token owner may not create or edit anything.');
    }

    public function test_a_token_is_issued_with_no_more_than_the_owner_may_do(): void
    {
        $this->actingAs($this->editor(['posts.view']), 'admin')
            ->get('/admin/api-tokens')
            ->assertForbidden();

        $user = $this->editor(['posts.view', Permissions::API_TOKENS], 'reader@example.com');

        $this->actingAs($user, 'admin')
            ->get('/admin/api-tokens')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('modules', [['key' => 'posts', 'label' => 'Posts', 'writable' => false]]));

        $this->from('/admin/api-tokens')
            ->post('/admin/api-tokens', ['name' => 'agent', 'abilities' => ['posts:write']])
            ->assertSessionHasErrors('abilities.0');
        $this->assertSame(0, PersonalAccessToken::query()->count());

        $this->post('/admin/api-tokens', ['name' => 'agent', 'abilities' => ['posts:read']])->assertSessionHasNoErrors();
        $this->assertSame(['posts:read'], PersonalAccessToken::query()->sole()->abilities);
    }

    public function test_an_administrator_sees_and_revokes_only_their_own_tokens(): void
    {
        $boss = $this->admin();
        $bossToken = $boss->createToken('boss', ['posts:read'])->accessToken;
        $user = $this->editor([Permissions::API_TOKENS], 'reader@example.com');
        $user->createToken('mine', ['posts:read']);

        $this->actingAs($user, 'admin')
            ->get('/admin/api-tokens')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('tokens', 1)
                ->where('tokens.0.name', 'mine'));

        $this->delete("/admin/api-tokens/{$bossToken->id}")->assertRedirect();
        $this->assertTrue(PersonalAccessToken::query()->whereKey($bossToken->id)->exists());

        $this->actingAs($boss, 'admin')
            ->get('/admin/api-tokens')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('tokens', 2));
    }

    public function test_the_migration_makes_existing_administrators_superadmins(): void
    {
        $migration = require dirname(__DIR__, 2).'/database/migrations/2026_11_17_000001_create_admin_roles_tables.php';
        $migration->down();

        DB::table('admin_users')->insert([
            'name' => 'Old', 'email' => 'old@example.com', 'password' => 'x', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration->up();

        $this->assertTrue(AdminUser::query()->where('email', 'old@example.com')->sole()->isSuperadmin());
    }

    public function test_a_role_opens_for_editing_with_only_the_permissions_that_still_exist(): void
    {
        $role = AdminRole::query()->create(['name' => 'Editor', 'permissions' => ['posts.view', 'gone.view', 'posts.delete']]);

        $this->actingAs($this->admin(), 'admin')
            ->get("/admin/roles/{$role->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Roles/Edit')
                ->where('role', ['id' => $role->id, 'name' => 'Editor', 'permissions' => ['posts.view', 'posts.delete']])
                ->where('title', __('atrium::permissions.edit_title', ['name' => 'Editor'])));

        $this->get('/admin/roles/'.($role->id + 1).'/edit')->assertNotFound();
    }

    public function test_an_administrator_is_deleted_and_so_is_a_superadmin_while_another_active_one_stays(): void
    {
        $boss = $this->admin();
        $editor = $this->editor(['posts.view']);
        $deputy = $this->editor([], 'deputy@example.com');
        $deputy->forceFill(['is_superadmin' => true])->save();

        $this->actingAs($boss, 'admin');
        $this->delete("/admin/users/{$editor->id}")->assertRedirect()->assertSessionMissing('error');
        $this->delete("/admin/users/{$deputy->id}")->assertRedirect()->assertSessionMissing('error');

        $this->assertSame([$boss->id], AdminUser::query()->pluck('id')->all());
    }

    public function test_a_switched_off_superadmin_does_not_count_as_another_one(): void
    {
        $boss = $this->admin();
        $sleeper = $this->editor([], 'sleeper@example.com');
        $sleeper->forceFill(['is_superadmin' => true, 'is_active' => false])->save();

        $this->actingAs($boss, 'admin')
            ->delete("/admin/users/{$boss->id}")
            ->assertSessionHas('error', __('atrium::permissions.last_superadmin'));

        $this->assertSame(2, AdminUser::query()->count());
    }

    public function test_routes_outside_the_panel_need_no_permission(): void
    {
        $nobody = $this->editor([]);
        $permissions = app(Permissions::class);

        $this->assertTrue($permissions->allowsRoute($nobody, null));
        $this->assertTrue($permissions->allowsRoute($nobody, 'home'));
        $this->assertTrue($permissions->allowsRoute($nobody, 'administrator.posts.index'));
        $this->assertFalse($permissions->allowsRoute($nobody, 'admin.posts.index'));
    }

    /**
     * An administrator with one role holding these permissions.
     *
     * @param  array<int,string>  $permissions
     */
    private function editor(array $permissions, string $email = 'editor@example.com'): AdminUser
    {
        $user = AdminUser::query()->create([
            'name' => 'Editor',
            'email' => $email,
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        $user->roles()->attach(AdminRole::query()->create(['name' => 'Role of '.$email, 'permissions' => $permissions]));

        return $user->fresh();
    }

    private function panel(AdminUser $user, string $method, string $url): TestResponse
    {
        $post = Post::query()->create(['name' => 'Hello']);
        $url = str_replace('{id}', (string) $post->id, $url);
        $data = in_array($method, ['post', 'put'], true) ? ['name' => 'Renamed', 'active' => true, 'sort' => 1] : [];

        return $this->actingAs($user, 'admin')->{$method}($url, $data);
    }

    private function api(AdminUser $user, string $method, string $url): TestResponse
    {
        $post = Post::query()->create(['name' => 'Hello']);
        $token = $user->createToken('agent', ['posts:read', 'posts:write'])->plainTextToken;
        $url = str_replace('{id}', (string) $post->id, $url);
        $data = in_array($method, ['postJson', 'putJson'], true) ? ['name' => 'Renamed', 'active' => true, 'sort' => 1] : [];

        return $this->withToken($token)->{$method}($url, $data);
    }

    /**
     * @return array<int,string>
     */
    private function menuRoutes(TestResponse $response): array
    {
        $menu = $response->viewData('page')['props']['menu'];

        return array_values(array_filter(Arr::flatten(array_map(
            fn (array $item): array => [$item['route'] ?? null, ...array_column($item['children'] ?? [], 'route')],
            $menu,
        ))));
    }

    public function test_only_the_exact_admin_route_names_of_a_module_need_its_permission(): void
    {
        $nobody = $this->editor([]);
        $permissions = app(Permissions::class);

        // Another route that merely ends in the name of a module route, or starts with it.
        $this->assertTrue($permissions->allowsRoute($nobody, 'admin.nested.admin.posts.index'));
        $this->assertTrue($permissions->allowsRoute($nobody, 'admin.posts.index.extra'));
        $this->assertTrue($permissions->allowsRoute($nobody, 'admin.posts.index1'));
        $this->assertFalse($permissions->allowsRoute($nobody, 'admin.posts.index'));
    }

    public function test_the_settings_of_any_module_open_the_modules_screen_to_a_role_with_exactly_that_permission(): void
    {
        $permissions = app(Permissions::class);

        $this->assertTrue($permissions->canSetUpAnything($this->editor(['labels.settings'])));
        $this->assertTrue($permissions->canSetUpAnything($this->admin()));
        $this->assertFalse($permissions->canSetUpAnything($this->editor(['labelsettings', 'posts.view'], 'other@example.com')));
        $this->assertFalse($permissions->canSetUpAnything($this->editor([], 'none@example.com')));
        $this->assertTrue($permissions->canSetUp($this->editor(['labels.settings'], 'third@example.com'), 'labels'));
    }

    public function test_a_token_may_read_what_the_owner_may_view_and_write_what_the_owner_may_change(): void
    {
        $permissions = app(Permissions::class);

        $this->assertSame(
            ['posts' => ['read' => true, 'write' => false]],
            Arr::only($permissions->tokenModes($this->editor(['posts.view'])), ['posts']),
        );
        $this->assertSame(
            ['posts' => ['read' => true, 'write' => true]],
            Arr::only($permissions->tokenModes($this->editor(['posts.view', 'posts.delete'], 'b@example.com')), ['posts']),
        );
        $this->assertArrayNotHasKey('posts', $permissions->tokenModes($this->editor(['posts.update'], 'c@example.com')));
    }

    public function test_the_sections_of_the_role_form_are_in_alphabetical_order_and_end_with_the_api_tokens(): void
    {
        $sections = app(Permissions::class)->sections();
        $labels = array_column($sections, 'label');
        $own = array_slice($labels, 0, -1);
        $sorted = $own;
        usort($sorted, strnatcasecmp(...));

        $this->assertSame($sorted, $own);
        $this->assertNotSame('Posts', $own[0]);
        $this->assertSame(
            ['key' => 'api-tokens', 'label' => __('atrium::menu.api_tokens'), 'actions' => ['use']],
            end($sections),
        );
        $this->assertSame(array_keys($sections), range(0, count($sections) - 1));
    }
}
