<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/**
 * The roles section in exact form: what the list shows and what the form
 * asks for and keeps.
 */
final class RoleControllerExactnessTest extends TestCase
{
    public function test_the_list_gives_each_role_its_numbers_counting_only_permissions_that_exist(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $role = AdminRole::query()->create(['name' => 'Writer', 'permissions' => ['posts.view', 'posts.fly', 'posts.update']]);
        $role->users()->attach([
            AdminUser::query()->create(['name' => 'Ann', 'email' => 'ann@example.com', 'password' => 'long-enough-1'])->id,
            AdminUser::query()->create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'long-enough-1'])->id,
        ]);

        $this->get('/admin/roles')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('roles', fn ($roles) => json_decode((string) json_encode($roles), true) === [
                ['id' => $role->id, 'name' => 'Writer', 'users_count' => 2, 'permissions_count' => 2],
            ])
            ->where('title', 'Roles'));
    }

    public function test_the_list_is_sorted_by_name(): void
    {
        $this->actingAs($this->admin(), 'admin');
        AdminRole::query()->create(['name' => 'Beta', 'permissions' => []]);
        AdminRole::query()->create(['name' => 'Alpha', 'permissions' => []]);

        $this->get('/admin/roles')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('roles.0.name', 'Alpha')
            ->where('roles.1.name', 'Beta'));
    }

    public function test_a_role_needs_a_name_and_a_list_of_permissions_named_for_the_admin(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->from('/admin/roles/create')->post('/admin/roles', ['permissions' => []])
            ->assertSessionHasErrors(['name' => 'The Name field is required.']);
        $this->from('/admin/roles/create')->post('/admin/roles', ['name' => 'Editor'])
            ->assertSessionHasErrors(['permissions' => 'The permissions field must be present.']);
        $this->assertSame(0, AdminRole::query()->count());
    }

    public function test_a_permission_is_a_string_that_exists(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->from('/admin/roles/create')->post('/admin/roles', ['name' => 'Editor', 'permissions' => [5]])
            ->assertSessionHasErrors(['permissions.0' => 'The permissions.0 field must be a string.']);
        $this->assertSame(0, AdminRole::query()->count());
    }

    public function test_a_permission_named_twice_is_kept_once_and_the_list_stays_a_list(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->post('/admin/roles', ['name' => 'Editor', 'permissions' => ['posts.view', 'posts.view', 'posts.update']])->assertRedirect();

        $this->assertSame('["posts.view","posts.update"]', DB::table('admin_roles')->value('permissions'));
    }
}
