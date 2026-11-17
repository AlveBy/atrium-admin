<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Tests\Concerns\PinsExtensionSurface;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * An administrator's own profile: name, e-mail and password.
 */
final class ProfileTest extends TestCase
{
    use PinsExtensionSurface;

    public function test_the_profile_shows_the_administrator_without_secrets(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['two_factor_code' => Hash::make('123456'), 'remember_token' => 'remember-me'])->save();

        $response = $this->actingAs($admin, 'admin')
            ->get('/admin/profile')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Profile/Edit')
                ->where('user.id', $admin->id)
                ->where('user.name', 'Admin')
                ->where('user.email', 'admin@example.com')
                ->missing('user.password')
                ->missing('user.two_factor_code')
                ->missing('user.remember_token'));

        $this->assertStringNotContainsString('remember-me', (string) $response->getContent());
    }

    public function test_guests_have_no_profile(): void
    {
        $this->get('/admin/profile')->assertRedirect('/admin/login');
        $this->put('/admin/profile', ['name' => 'X', 'email' => 'x@example.com'])->assertRedirect('/admin/login');
        $this->put('/admin/profile/password', [])->assertRedirect('/admin/login');
    }

    public function test_the_name_and_e_mail_are_changed(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($admin, 'admin')
            ->from('/admin/profile')
            ->put('/admin/profile', ['name' => 'Юрий 🪑', 'email' => 'yuri@example.com'])
            ->assertRedirect('/admin/profile')
            ->assertSessionHas('success', __('atrium::account.profile.updated'));

        $admin->refresh();
        $this->assertSame('Юрий 🪑', $admin->name);
        $this->assertSame('yuri@example.com', $admin->email);
        $this->assertNull($admin->email_verified_at, 'A new address is not verified yet.');
    }

    public function test_keeping_the_e_mail_keeps_it_verified(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($admin, 'admin')->put('/admin/profile', ['name' => 'Renamed', 'email' => 'admin@example.com'])->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $admin->fresh()->name);
        $this->assertNotNull($admin->fresh()->email_verified_at);
    }

    public function test_the_profile_form_cannot_raise_its_own_rights(): void
    {
        $admin = AdminUser::query()->create(['name' => 'Editor', 'email' => 'editor@example.com', 'password' => 'secret-password', 'is_active' => true]);

        $this->actingAs($admin, 'admin')->put('/admin/profile', [
            'name' => 'Editor', 'email' => 'editor@example.com', 'is_superadmin' => true, 'is_active' => false, 'id' => 99, 'password' => 'x',
        ])->assertSessionHasNoErrors();

        $admin->refresh();
        $this->assertFalse($admin->isSuperadmin());
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('secret-password', $admin->password));
        $this->assertNull(AdminUser::query()->find(99));
    }

    /**
     * @return array<string,array{0:array<string,mixed>,1:string,2:string|null}>
     */
    public static function invalidProfiles(): array
    {
        return [
            'no name' => [['email' => 'admin@example.com'], 'name', 'atrium::account.profile.validation.name_required'],
            'empty name' => [['name' => '', 'email' => 'admin@example.com'], 'name', 'atrium::account.profile.validation.name_required'],
            'name as an array' => [['name' => ['A'], 'email' => 'admin@example.com'], 'name', null],
            'name one too long' => [['name' => str_repeat('a', 256), 'email' => 'admin@example.com'], 'name', null],
            'no e-mail' => [['name' => 'Admin'], 'email', 'atrium::account.profile.validation.email_required'],
            'not an e-mail' => [['name' => 'Admin', 'email' => 'admin'], 'email', 'atrium::account.profile.validation.email_invalid'],
            'e-mail one too long' => [['name' => 'Admin', 'email' => str_repeat('a', 244).'@example.com'], 'email', null],
            'e-mail of another administrator' => [['name' => 'Admin', 'email' => 'other@example.com'], 'email', 'atrium::account.profile.validation.email_taken'],
        ];
    }

    /**
     * @param  array<string,mixed>  $form
     */
    #[DataProvider('invalidProfiles')]
    public function test_an_invalid_profile_is_refused_on_its_field(array $form, string $field, ?string $message): void
    {
        $admin = $this->admin();
        AdminUser::query()->create(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'secret-password', 'is_active' => true]);

        $response = $this->actingAs($admin, 'admin')->from('/admin/profile')->put('/admin/profile', $form)->assertRedirect('/admin/profile');

        $response->assertSessionHasErrors($message === null ? $field : [$field => __($message)]);
        $this->assertSame('Admin', $admin->fresh()->name);
        $this->assertSame('admin@example.com', $admin->fresh()->email);
    }

    public function test_the_password_is_changed_and_the_old_one_stops_working(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->from('/admin/profile')
            ->put('/admin/profile/password', [
                'current_password' => 'secret-password',
                'password' => 'a-new-longer-password',
                'password_confirmation' => 'a-new-longer-password',
            ])
            ->assertRedirect('/admin/profile')
            ->assertSessionHas('success', __('atrium::account.profile.password_updated'));

        $hash = $admin->fresh()->password;
        $this->assertTrue(Hash::check('a-new-longer-password', $hash));
        $this->assertFalse(Hash::check('secret-password', $hash));

        $this->post('/admin/logout');
        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'a-new-longer-password'])->assertRedirect('/admin');
    }

    /**
     * @return array<string,array{0:array<string,mixed>,1:string,2:string|null}>
     */
    public static function invalidPasswords(): array
    {
        return [
            'no current password' => [['password' => 'new-password-1', 'password_confirmation' => 'new-password-1'], 'current_password', 'atrium::account.profile.validation.current_password_required'],
            'wrong current password' => [['current_password' => 'nope', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'], 'current_password', 'atrium::account.profile.validation.current_password_wrong'],
            'no new password' => [['current_password' => 'secret-password'], 'password', 'atrium::account.profile.validation.password_required'],
            'not confirmed' => [['current_password' => 'secret-password', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-2'], 'password', 'atrium::account.profile.validation.password_mismatch'],
            'too short' => [['current_password' => 'secret-password', 'password' => 'short', 'password_confirmation' => 'short'], 'password', null],
        ];
    }

    /**
     * @param  array<string,mixed>  $form
     */
    #[DataProvider('invalidPasswords')]
    public function test_an_invalid_password_change_is_refused(array $form, string $field, ?string $message): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->from('/admin/profile')
            ->put('/admin/profile/password', $form)
            ->assertRedirect('/admin/profile')
            ->assertSessionHasErrors($message === null ? $field : [$field => __($message)]);

        $this->assertTrue(Hash::check('secret-password', $admin->fresh()->password));
    }
}
