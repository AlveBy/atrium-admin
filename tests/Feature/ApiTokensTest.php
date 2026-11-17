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
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;

/**
 * The "API tokens" section: issuing a key, seeing it once, revoking it.
 */
final class ApiTokensTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Sanctum::class))->getFileName(), 2).'/database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-17 12:00:00');
    }

    public function test_a_token_is_issued_and_its_key_is_shown_once(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'admin')
            ->post('/admin/api-tokens', ['name' => 'Price robot', 'abilities' => ['posts:read', 'posts:write', 'posts:read'], 'expires_at' => '2026-12-31'])
            ->assertRedirect('/admin/api-tokens')
            ->assertSessionHas('success', __('atrium::account.api_tokens.created'));

        $token = PersonalAccessToken::query()->sole();
        $plain = $response->getSession()->get('api_token');

        $this->assertSame('Price robot', $token->name);
        $this->assertSame(['posts:read', 'posts:write'], $token->abilities, 'Each ability once.');
        $this->assertSame('2026-12-31 23:59:59', $token->expires_at->format('Y-m-d H:i:s'));
        $this->assertTrue($token->tokenable->is($admin));
        $this->assertStringStartsWith($token->id.'|', $plain);
        $this->assertSame(hash('sha256', explode('|', $plain, 2)[1]), $token->token, 'Only the hash is stored.');

        $this->withToken($plain)->getJson('/api/v1/posts')->assertOk();
    }

    public function test_a_token_without_an_expiry_never_expires(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/api-tokens', ['name' => 'Forever', 'abilities' => ['posts:read'], 'expires_at' => ''])
            ->assertSessionHasNoErrors();

        $this->assertNull(PersonalAccessToken::query()->sole()->expires_at);
    }

    /**
     * @return array<string,array{0:array<string,mixed>,1:string}>
     */
    public static function invalidTokens(): array
    {
        return [
            'no name' => [['abilities' => ['posts:read']], 'name'],
            'empty name' => [['name' => '', 'abilities' => ['posts:read']], 'name'],
            'name as an array' => [['name' => ['robot'], 'abilities' => ['posts:read']], 'name'],
            'name one too long' => [['name' => str_repeat('a', 256), 'abilities' => ['posts:read']], 'name'],
            'no abilities' => [['name' => 'robot'], 'abilities'],
            'empty abilities' => [['name' => 'robot', 'abilities' => []], 'abilities'],
            'abilities as a string' => [['name' => 'robot', 'abilities' => 'posts:read'], 'abilities'],
            'an unknown module' => [['name' => 'robot', 'abilities' => ['nope:read']], 'abilities.0'],
            'administrators are not in the API' => [['name' => 'robot', 'abilities' => ['users:read']], 'abilities.0'],
            'a wildcard' => [['name' => 'robot', 'abilities' => ['*']], 'abilities.0'],
            'an unknown mode' => [['name' => 'robot', 'abilities' => ['posts:delete']], 'abilities.0'],
            'an ability that is not a string' => [['name' => 'robot', 'abilities' => [['posts:read']]], 'abilities.0'],
            'expiring today' => [['name' => 'robot', 'abilities' => ['posts:read'], 'expires_at' => '2026-11-17'], 'expires_at'],
            'expired yesterday' => [['name' => 'robot', 'abilities' => ['posts:read'], 'expires_at' => '2026-11-16'], 'expires_at'],
            'not a date' => [['name' => 'robot', 'abilities' => ['posts:read'], 'expires_at' => 'tomorrow-ish'], 'expires_at'],
        ];
    }

    /**
     * @param  array<string,mixed>  $form
     */
    #[DataProvider('invalidTokens')]
    public function test_an_invalid_token_form_is_refused_on_its_field(array $form, string $field): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->from('/admin/api-tokens')
            ->post('/admin/api-tokens', $form)
            ->assertRedirect('/admin/api-tokens')
            ->assertSessionHasErrors($field)
            ->assertSessionMissing('api_token');

        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_a_token_for_tomorrow_is_fine(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/api-tokens', ['name' => 'robot', 'abilities' => ['posts:read'], 'expires_at' => '2026-11-18'])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-11-18 23:59:59', PersonalAccessToken::query()->sole()->expires_at->format('Y-m-d H:i:s'));
    }

    public function test_missing_abilities_are_asked_for_in_the_panel_s_words(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/api-tokens', ['name' => 'robot'])
            ->assertSessionHasErrors(['abilities' => __('atrium::account.api_tokens.abilities_required')]);
    }

    public function test_the_list_shows_each_token_with_its_owner_and_dates(): void
    {
        $admin = $this->admin();
        $old = $admin->createToken('old', ['posts:read'], now()->subDay())->accessToken;
        $old->forceFill(['last_used_at' => now()->subHours(3)])->save();
        $this->travel(1)->minutes();
        $admin->createToken('new', ['posts:read', 'posts:write']);

        $this->actingAs($admin, 'admin')
            ->withSession(['api_token' => '5|plain-key'])
            ->get('/admin/api-tokens')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/ApiTokens/Index')
                ->where('plainToken', '5|plain-key')
                ->where('docsUrl', 'http://localhost/api/v1')
                ->where('openApiUrl', 'http://localhost/api/v1/openapi.json')
                ->has('tokens', 2)
                ->where('tokens.0.name', 'new')
                ->where('tokens.0.owner', 'Admin')
                ->where('tokens.0.abilities', ['posts:read', 'posts:write'])
                ->where('tokens.0.last_used_at', null)
                ->where('tokens.0.expires_at', null)
                ->where('tokens.0.expired', false)
                ->where('tokens.0.created_at', '17.11.2026 12:01')
                ->where('tokens.1.name', 'old')
                ->where('tokens.1.id', $old->id)
                ->where('tokens.1.last_used_at', '17.11.2026 09:00')
                ->where('tokens.1.expires_at', '16.11.2026')
                ->where('tokens.1.expired', true)
                ->where('modules', [
                    ['key' => 'languages', 'label' => 'Languages', 'writable' => true],
                    ['key' => 'posts', 'label' => 'Posts', 'writable' => true],
                    ['key' => 'sites', 'label' => 'Sites', 'writable' => true],
                ]));
    }

    public function test_a_token_expiring_later_is_not_marked_expired(): void
    {
        $admin = $this->admin();
        $admin->createToken('soon', ['posts:read'], now()->addMinute());

        $this->actingAs($admin, 'admin')
            ->get('/admin/api-tokens')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('tokens.0.expired', false)->where('tokens.0.expires_at', '17.11.2026'));
    }

    public function test_a_module_is_writable_with_any_of_create_update_or_delete(): void
    {
        foreach (['posts.create', 'posts.update', 'posts.delete'] as $i => $permission) {
            $user = $this->editor(['posts.view', $permission, Permissions::API_TOKENS], "editor{$i}@example.com");

            $this->actingAs($user, 'admin')
                ->get('/admin/api-tokens')
                ->assertInertia(fn (AssertableInertia $page) => $page->where('modules', [['key' => 'posts', 'label' => 'Posts', 'writable' => true]]));

            $this->post('/admin/api-tokens', ['name' => 'robot', 'abilities' => ['posts:write']])->assertSessionHasNoErrors();
        }

        $this->assertSame(3, PersonalAccessToken::query()->count());
    }

    public function test_a_module_the_administrator_cannot_view_is_not_offered(): void
    {
        $user = $this->editor(['posts.create', Permissions::API_TOKENS]);

        $this->actingAs($user, 'admin')
            ->get('/admin/api-tokens')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('modules', []));

        $this->post('/admin/api-tokens', ['name' => 'robot', 'abilities' => ['posts:write']])->assertSessionHasErrors('abilities.0');
    }

    public function test_a_token_is_revoked_and_stops_working(): void
    {
        $admin = $this->admin();
        $token = $admin->createToken('robot', ['posts:read']);
        $keep = $admin->createToken('other', ['posts:read']);

        $this->actingAs($admin, 'admin')
            ->delete("/admin/api-tokens/{$token->accessToken->id}")
            ->assertRedirect('/admin/api-tokens')
            ->assertSessionHas('success', __('atrium::account.api_tokens.revoked'));

        $this->assertSame([$keep->accessToken->id], PersonalAccessToken::query()->pluck('id')->all());

        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)->getJson('/api/v1/posts')->assertUnauthorized();
    }

    public function test_a_superadmin_revokes_anyone_s_token(): void
    {
        $user = $this->editor([Permissions::API_TOKENS]);
        $theirs = $user->createToken('theirs', ['posts:read'])->accessToken;

        $this->actingAs($this->admin(), 'admin')->delete("/admin/api-tokens/{$theirs->id}")->assertRedirect();

        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_revoking_an_unknown_token_changes_nothing(): void
    {
        $admin = $this->admin();
        $admin->createToken('robot', ['posts:read']);

        $this->actingAs($admin, 'admin')->delete('/admin/api-tokens/999')->assertRedirect('/admin/api-tokens');

        $this->assertSame(1, PersonalAccessToken::query()->count());
    }

    public function test_tokens_of_other_kinds_of_users_are_never_listed_or_revoked(): void
    {
        $admin = $this->admin();
        $post = Post::query()->create(['name' => 'Not a person']);
        $odd = PersonalAccessToken::query()->forceCreate([
            'tokenable_type' => Post::class, 'tokenable_id' => $post->id, 'name' => 'odd', 'token' => hash('sha256', 'x'), 'abilities' => ['*'],
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/api-tokens')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('tokens', 0));

        $this->delete("/admin/api-tokens/{$odd->id}");
        $this->assertTrue(PersonalAccessToken::query()->whereKey($odd->id)->exists());
    }

    public function test_the_section_needs_its_permission_for_every_action(): void
    {
        $owner = $this->admin();
        $token = $owner->createToken('robot', ['posts:read'])->accessToken;
        $user = $this->editor(['posts.view', 'posts.create', 'posts.update', 'posts.delete']);

        $this->actingAs($user, 'admin')->get('/admin/api-tokens')->assertForbidden();
        $this->post('/admin/api-tokens', ['name' => 'robot', 'abilities' => ['posts:read']])->assertForbidden();
        $this->delete("/admin/api-tokens/{$token->id}")->assertForbidden();

        $this->assertSame([$token->id], PersonalAccessToken::query()->pluck('id')->all());
    }

    /**
     * @param  array<int,string>  $permissions
     */
    private function editor(array $permissions, string $email = 'editor@example.com'): AdminUser
    {
        $user = AdminUser::query()->create(['name' => 'Editor', 'email' => $email, 'password' => 'secret-password', 'is_active' => true]);
        $user->roles()->attach(AdminRole::query()->create(['name' => 'Role of '.$email, 'permissions' => $permissions]));

        return $user->fresh();
    }
}
