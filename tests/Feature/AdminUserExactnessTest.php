<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Fields\Field;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Tests\TestCase;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The administrator account in exact form: its fields, the rules of its
 * forms, the sites it may open and the permissions its roles add up to.
 */
final class AdminUserExactnessTest extends TestCase
{
    private function user(array $attributes = []): AdminUser
    {
        return AdminUser::query()->create(['name' => 'Ann', 'email' => 'ann@example.com', 'password' => 'long-enough-1', ...$attributes]);
    }

    /**
     * @return array<string,Field>
     */
    private function fields(): array
    {
        $fields = [];

        foreach (AdminUser::getAdministrationFields() as $field) {
            $fields[$field->getName()] = $field;
        }

        return $fields;
    }

    private function errors(array $rules, array $data): array
    {
        return array_keys(Validator::make($data, $rules)->errors()->toArray());
    }

    public function test_a_verification_date_comes_back_as_a_date(): void
    {
        $user = $this->user(['email_verified_at' => '2026-03-04 05:06:07']);
        $user->forceFill(['email_verified_at' => '2026-03-04 05:06:07'])->save();

        $this->assertInstanceOf(CarbonInterface::class, $user->fresh()->email_verified_at);
        $this->assertSame('2026-03-04 05:06:07', $user->fresh()->email_verified_at->format('Y-m-d H:i:s'));
    }

    public function test_the_fields_of_one_site_install(): void
    {
        $fields = $this->fields();

        $this->assertSame(['name', 'email', 'password', 'password_confirmation', 'is_active'], array_keys($fields));
        $this->assertTrue($fields['name']->isRequired());
        $this->assertSame(255, $fields['name']->getMaxLength());
        $this->assertContains('min:8', $fields['password']->getValidationRules());
        $this->assertNotContains('min:7', $fields['password']->getValidationRules());
        $this->assertNotContains('min:9', $fields['password']->getValidationRules());
        $this->assertTrue($fields['is_active']->getDefault());
    }

    public function test_the_site_limit_lists_the_sites_by_id(): void
    {
        $second = Site::query()->create(['code' => 'de', 'name' => 'Deutschland', 'domains' => ['example.de']]);
        $main = Site::query()->where('code', 'main')->sole();

        $fields = $this->fields();

        $this->assertSame(['name', 'email', 'password', 'password_confirmation', 'is_active', 'site_ids'], array_keys($fields));
        $options = $fields['site_ids']->toArray()['options'];
        $this->assertEquals([
            ['value' => (string) $main->id, 'label' => $main->name],
            ['value' => (string) $second->id, 'label' => 'Deutschland'],
        ], $options);
    }

    public function test_only_a_superadmin_sees_the_rights_fields(): void
    {
        $this->assertArrayNotHasKey('roles', $this->fields());

        $admin = $this->user();
        $admin->forceFill(['is_superadmin' => true])->save();
        $this->actingAs($admin, 'admin');
        $fields = $this->fields();

        $this->assertSame(['name', 'email', 'password', 'password_confirmation', 'is_active', 'is_superadmin', 'roles'], array_keys($fields));
    }

    public function test_the_create_rules_are_these(): void
    {
        $this->assertEquals([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:admin_users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
            'is_superadmin' => ['nullable', 'boolean'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', Rule::exists('admin_roles', 'id')],
            'site_ids' => ['nullable', 'array'],
            'site_ids.*' => [Rule::in([1])],
            'roles_by_site' => ['nullable', 'array:1'],
            'roles_by_site.*' => ['nullable', 'array'],
            'roles_by_site.*.*' => ['integer', Rule::exists('admin_roles', 'id')],
        ], AdminUser::getCreateValidationRules());
    }

    public function test_the_update_rules_are_these(): void
    {
        $this->assertEquals([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
            'is_superadmin' => ['nullable', 'boolean'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', Rule::exists('admin_roles', 'id')],
            'site_ids' => ['nullable', 'array'],
            'site_ids.*' => [Rule::in([1])],
            'roles_by_site' => ['nullable', 'array:1'],
            'roles_by_site.*' => ['nullable', 'array'],
            'roles_by_site.*.*' => ['integer', Rule::exists('admin_roles', 'id')],
        ], AdminUser::getUpdateValidationRules());
    }

    public function test_the_rules_accept_the_ticked_sites_as_the_form_sends_them(): void
    {
        foreach ([AdminUser::getCreateValidationRules(), AdminUser::getUpdateValidationRules()] as $rules) {
            $good = ['name' => 'Ann', 'email' => 'new@example.com', 'password' => 'long-enough-1', 'password_confirmation' => 'long-enough-1'];

            $this->assertSame([], $this->errors($rules, $good + ['site_ids' => ['1']]));
            $this->assertSame(['site_ids.0'], $this->errors($rules, $good + ['site_ids' => ['2']]));
            $this->assertSame(['roles.0'], $this->errors($rules, $good + ['roles' => ['x']]));
            $this->assertSame(['roles.0'], $this->errors($rules, $good + ['roles' => [99]]));
        }
    }

    public function test_a_blank_form_names_what_is_missing(): void
    {
        $this->assertSame(['name', 'email', 'password'], $this->errors(AdminUser::getCreateValidationRules(), []));
        $this->assertSame(['name', 'email'], $this->errors(AdminUser::getUpdateValidationRules(), []));
        $this->assertSame(['is_active', 'is_superadmin'], $this->errors(AdminUser::getUpdateValidationRules(), ['name' => 'a', 'email' => 'a@b.test', 'is_active' => 'x', 'is_superadmin' => 'x']));
    }

    public function test_the_sites_ticked_on_an_account_may_be_stored_as_numbers(): void
    {
        $second = Site::query()->create(['code' => 'de', 'name' => 'Deutschland', 'domains' => ['example.de']]);
        $main = Site::query()->where('code', 'main')->sole();

        $user = $this->user();
        $this->assertSame([$main->id, $second->id], $user->allowedSites()->pluck('id')->all(), 'No ticks: every site.');

        $user->forceFill(['site_ids' => [$second->id]])->save();
        $this->assertSame([$second->id], $user->fresh()->allowedSites()->pluck('id')->all());

        $user->forceFill(['site_ids' => [(string) $main->id]])->save();
        $this->assertSame([$main->id], $user->fresh()->allowedSites()->pluck('id')->all());
    }

    public function test_the_permissions_of_all_the_roles_add_up_and_a_role_without_any_adds_nothing(): void
    {
        $user = $this->user();
        $user->roles()->attach([
            AdminRole::query()->create(['name' => 'A', 'permissions' => ['x.read', 'x.write']])->id,
            AdminRole::query()->create(['name' => 'B', 'permissions' => ['x.read', 'y.read']])->id,
            AdminRole::query()->create(['name' => 'C', 'permissions' => []])->id,
        ]);

        $this->assertSame(['x.read', 'x.write', 'y.read'], $user->fresh()->adminPermissions());
    }
}
