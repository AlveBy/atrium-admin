<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Http\Requests\Auth\LoginRequest;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;

final class LoginTest extends TestCase
{
    public function test_an_admin_signs_in_with_the_right_password(): void
    {
        $admin = $this->admin();

        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'secret-password'])
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_a_wrong_password_is_refused(): void
    {
        $this->admin();

        $this->from('/admin/login')
            ->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'nope'])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }

    public function test_a_deactivated_admin_is_refused(): void
    {
        $this->admin()->update(['is_active' => false]);

        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'secret-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }

    public function test_the_sign_in_page_is_shown_to_guests_with_the_branding(): void
    {
        config()->set('atrium.branding', ['name' => 'Garden <b>Shop</b>']);

        $this->get('/admin/login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Auth/Login')
                ->where('branding.name', 'Garden <b>Shop</b>'));
    }

    public function test_a_signed_in_admin_is_sent_from_the_sign_in_page_to_the_dashboard(): void
    {
        $this->actingAs($this->admin(), 'admin')->get('/admin/login')->assertRedirect('/admin');
    }

    public function test_the_sign_in_brings_back_to_the_page_asked_for(): void
    {
        $this->admin();

        $this->get('/admin/profile')->assertRedirect('/admin/login');

        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'secret-password'])
            ->assertRedirect('http://localhost/admin/profile');
    }

    public function test_remember_me_is_kept_only_when_asked_for(): void
    {
        $admin = $this->admin();

        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'secret-password']);
        $this->assertNull($admin->fresh()->remember_token);

        $this->post('/admin/logout');
        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'secret-password', 'remember' => true]);
        $this->assertNotNull($admin->fresh()->remember_token);
    }

    /**
     * @return array<string,array{0:array<string,mixed>,1:string}>
     */
    public static function invalidForms(): array
    {
        return [
            'no email' => [['password' => 'secret-password'], 'email'],
            'empty email' => [['email' => '', 'password' => 'secret-password'], 'email'],
            'not an email' => [['email' => 'admin', 'password' => 'secret-password'], 'email'],
            'email as an array' => [['email' => ['admin@example.com'], 'password' => 'secret-password'], 'email'],
            'no password' => [['email' => 'admin@example.com'], 'password'],
            'empty password' => [['email' => 'admin@example.com', 'password' => ''], 'password'],
            'password as an array' => [['email' => 'admin@example.com', 'password' => ['secret-password']], 'password'],
            'remember is not a boolean' => [['email' => 'admin@example.com', 'password' => 'secret-password', 'remember' => 'forever'], 'remember'],
            'unknown email' => [['email' => 'nobody@example.com', 'password' => 'secret-password'], 'email'],
            'Cyrillic email of nobody' => [['email' => 'админ@пример.бел', 'password' => 'secret-password'], 'email'],
            'SQL in the email' => [['email' => "admin@example.com' OR '1'='1", 'password' => 'x'], 'email'],
        ];
    }

    /**
     * @param  array<string,mixed>  $form
     */
    #[DataProvider('invalidForms')]
    public function test_an_invalid_sign_in_form_is_refused_on_its_field(array $form, string $field): void
    {
        $this->admin();

        $this->from('/admin/login')
            ->post('/admin/login', $form)
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors($field);

        $this->assertGuest('admin');
    }

    public function test_the_validation_messages_are_the_panel_s_own(): void
    {
        $this->post('/admin/login', [])->assertSessionHasErrors([
            'email' => __('atrium::auth.validation.email_required'),
            'password' => __('atrium::auth.validation.password_required'),
        ]);

        $this->post('/admin/login', ['email' => 'admin', 'password' => 'x'])
            ->assertSessionHasErrors(['email' => __('atrium::auth.validation.email_invalid')]);
    }

    public function test_five_wrong_passwords_lock_the_sign_in_even_for_the_right_one(): void
    {
        $this->admin();
        $this->travelTo('2026-11-17 12:00:00');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'nope'])
                ->assertSessionHasErrors(['email' => __('atrium::auth.failed')]);
        }

        $this->post('/admin/login', ['email' => 'Admin@Example.com', 'password' => 'secret-password'])
            ->assertSessionHasErrors(['email' => __('atrium::auth.throttle', ['seconds' => 60])]);
        $this->assertGuest('admin');

        $this->travel(61)->seconds();

        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'secret-password'])->assertRedirect('/admin');
        $this->assertAuthenticated('admin');
    }

    public function test_a_right_password_resets_the_count_of_wrong_ones(): void
    {
        $this->admin();

        for ($i = 0; $i < 4; $i++) {
            $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'nope']);
        }

        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'secret-password'])->assertRedirect('/admin');
        $this->post('/admin/logout');

        for ($i = 0; $i < 4; $i++) {
            $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'nope'])
                ->assertSessionHasErrors(['email' => __('atrium::auth.failed')]);
        }
    }

    public function test_the_deactivated_message_names_the_reason(): void
    {
        $this->admin()->update(['is_active' => false]);

        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'secret-password'])
            ->assertSessionHasErrors(['email' => __('atrium::auth.deactivated')]);
    }

    public function test_signing_out_ends_the_session(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->withSession(['marker' => 'kept'])
            ->post('/admin/logout')
            ->assertRedirect('/admin/login')
            ->assertSessionMissing('marker');

        $this->assertGuest('admin');
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_signing_out_leaves_the_session_a_new_csrf_token(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->withSession(['_token' => 'old-token'])
            ->post('/admin/logout')
            ->assertRedirect('/admin/login');

        $token = $this->app['session.store']->token();

        $this->assertNotEmpty($token);
        $this->assertNotSame('old-token', $token);
    }

    public function test_guests_cannot_sign_out(): void
    {
        $this->post('/admin/logout')->assertRedirect('/admin/login');
    }

    public function test_an_api_client_without_a_session_gets_401_not_a_redirect(): void
    {
        $this->getJson('/admin/posts')->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_an_admin_switched_off_mid_session_is_signed_out(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin');
        $admin->update(['is_active' => false]);

        $this->get('/admin/posts')
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors(['email' => __('atrium::auth.deactivated')]);
        $this->assertGuest('admin');
    }

    public function test_an_admin_switched_off_mid_session_gets_403_in_json(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin');
        $admin->update(['is_active' => false]);

        $this->getJson('/admin/posts')->assertForbidden()->assertJsonPath('message', 'Your account is deactivated.');
        $this->assertGuest('admin');
    }

    public function test_the_throttle_key_is_the_lower_case_email_and_the_address_and_it_locks_after_five_hits(): void
    {
        $request = LoginRequest::create('/admin/login', 'POST', ['email' => 'Admin@Example.com'], server: ['REMOTE_ADDR' => '10.1.2.3']);

        $this->assertSame('admin@example.com|10.1.2.3', $request->throttleKey());

        $request->ensureIsNotRateLimited();
        for ($i = 0; $i < 4; $i++) {
            RateLimiter::hit($request->throttleKey());
        }
        $request->ensureIsNotRateLimited();

        RateLimiter::hit($request->throttleKey());
        try {
            $request->ensureIsNotRateLimited();
            $this->fail('Five hits did not lock the key.');
        } catch (ValidationException $e) {
            $this->assertSame(['email' => [__('atrium::auth.throttle', ['seconds' => 60])]], $e->errors());
        }
    }

    public function test_a_guard_whose_provider_does_not_exist_refuses_everyone_on_the_email_field(): void
    {
        config(['auth.guards.admin.provider' => 'nowhere']);
        $request = LoginRequest::create('/admin/login', 'POST', ['email' => 'admin@example.com', 'password' => 'x']);

        try {
            $request->validateCredentials();
            $this->fail('A missing provider let someone in.');
        } catch (ValidationException $e) {
            $this->assertSame(['email' => [__('atrium::auth.failed')]], $e->errors());
        }
    }
}
