<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Auth\TwoFactorCode;
use Atrium\Admin\Mail\TwoFactorCodeMail;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * The second sign-in step: the password is right, the e-mailed code lets in.
 */
final class TwoFactorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('atrium.auth.two_factor.enabled', true);
        config()->set('atrium.auth.two_factor.ttl', 10);
        config()->set('atrium.auth.two_factor.resend_after', 60);
        $this->travelTo('2026-11-17 12:00:00');
    }

    public function test_the_right_password_sends_a_code_instead_of_signing_in(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'secret-password', 'remember' => true])
            ->assertRedirect('/admin/two-factor')
            ->assertSessionHas('admin.two_factor', ['id' => $admin->id, 'remember' => true]);

        $this->assertGuest('admin');

        $code = $this->sentCode();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $admin->refresh();
        $this->assertTrue(Hash::check($code, $admin->two_factor_code));
        $this->assertSame('2026-11-17 12:00:00', $admin->two_factor_sent_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-17 12:10:00', $admin->two_factor_expires_at->format('Y-m-d H:i:s'));
    }

    public function test_a_wrong_password_sends_no_code(): void
    {
        Mail::fake();
        $this->admin();

        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'nope'])
            ->assertSessionHasErrors(['email' => __('atrium::auth.failed')])
            ->assertSessionMissing('admin.two_factor');

        Mail::assertNothingSent();
    }

    public function test_a_code_is_sent_again_on_a_new_sign_in_even_right_after_the_last_one(): void
    {
        Mail::fake();
        $this->admin();

        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'secret-password']);
        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'secret-password']);

        Mail::assertSent(TwoFactorCodeMail::class, 2);
    }

    public function test_mail_that_fails_on_sign_in_is_named_instead_of_a_500(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('535 Authentication failed'));
        $this->admin();

        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'secret-password'])
            ->assertSessionHasErrors(['email' => TwoFactorCode::mailFailedMessage()])
            ->assertSessionMissing('admin.two_factor');

        $this->assertGuest('admin');
    }

    public function test_the_code_page_without_a_pending_sign_in_goes_back_to_the_password(): void
    {
        $this->get('/admin/two-factor')->assertRedirect('/admin/login');
        $this->post('/admin/two-factor', ['code' => '123456'])->assertRedirect('/admin/login');
        $this->post('/admin/two-factor/resend')->assertRedirect('/admin/login');
    }

    /**
     * @return array<string,array{0:string,1:string}>
     */
    public static function emails(): array
    {
        return [
            'ordinary' => ['admin@example.com', 'ad***@example.com'],
            'one letter before the @' => ['a@example.com', 'a*@example.com'],
            'two letters before the @' => ['ab@example.com', 'ab*@example.com'],
            'three letters before the @' => ['abc@example.com', 'ab*@example.com'],
            'Cyrillic' => ['юрий@пример.бел', 'юр**@пример.бел'],
            'Chinese' => ['管理员@例子.中国', '管理*@例子.中国'],
            'no @ at all' => ['localadmin', 'lo********'],
        ];
    }

    #[DataProvider('emails')]
    public function test_the_code_page_shows_where_the_code_went_without_giving_the_mailbox_away(string $email, string $masked): void
    {
        $admin = $this->admin();
        $admin->forceFill(['email' => $email, 'two_factor_sent_at' => now()->subSeconds(15)])->save();

        $this->pending($admin)
            ->get('/admin/two-factor')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Auth/TwoFactor')
                ->where('email', $masked)
                ->where('retryAfter', 45));
    }

    public function test_the_right_code_signs_in_once(): void
    {
        $admin = $this->withCode('123456');

        $this->pending($admin, remember: false)
            ->post('/admin/two-factor', ['code' => '123456'])
            ->assertRedirect('/admin')
            ->assertSessionMissing('admin.two_factor');

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertNull($admin->fresh()->remember_token);

        $admin->refresh();
        $this->assertNull($admin->two_factor_code);
        $this->assertNull($admin->two_factor_sent_at);
        $this->assertNull($admin->two_factor_expires_at);
        $this->assertFalse(TwoFactorCode::matches($admin, '123456'));
    }

    public function test_remember_me_from_the_password_step_is_kept(): void
    {
        $admin = $this->withCode('123456');

        $this->pending($admin, remember: true)->post('/admin/two-factor', ['code' => '123456']);

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertNotNull($admin->fresh()->remember_token);
    }

    public function test_the_code_brings_back_to_the_page_asked_for_before_the_sign_in(): void
    {
        $admin = $this->withCode('123456');

        $this->pending($admin)
            ->withSession(['url.intended' => 'http://localhost/admin/profile'])
            ->post('/admin/two-factor', ['code' => '123456'])
            ->assertRedirect('http://localhost/admin/profile');
    }

    /**
     * @return array<string,array{0:mixed,1:bool}>
     */
    public static function codes(): array
    {
        return [
            'exact' => ['123456', true],
            'with spaces, as copied from the e-mail' => ['123 456', true],
            'with dashes' => ['12-34-56', true],
            'one digit wrong' => ['123457', false],
            'one digit short' => ['12345', false],
            'one digit too many' => ['1234567', false],
            'letters only' => ['abcdef', false],
            'empty' => ['', false],
            'only spaces' => ['   ', false],
            'not a string' => [['123456'], false],
            'Arabic-Indic digits' => ['١٢٣٤٥٦', false],
        ];
    }

    #[DataProvider('codes')]
    public function test_the_code_is_checked(mixed $code, bool $accepted): void
    {
        $admin = $this->withCode('123456');

        $response = $this->pending($admin)->from('/admin/two-factor')->post('/admin/two-factor', ['code' => $code]);

        if ($accepted) {
            $response->assertRedirect('/admin')->assertSessionHasNoErrors();
            $this->assertAuthenticatedAs($admin, 'admin');
        } else {
            $response->assertRedirect('/admin/two-factor')->assertSessionHasErrors('code');
            $this->assertGuest('admin');
            $this->assertNotNull($admin->fresh()->two_factor_code);
        }
    }

    public function test_a_missing_code_is_asked_for_by_name(): void
    {
        $this->pending($this->withCode('123456'))
            ->post('/admin/two-factor', [])
            ->assertSessionHasErrors(['code' => __('atrium::auth.two_factor.code_required')]);
    }

    public function test_an_expired_code_is_refused(): void
    {
        $admin = $this->withCode('123456');
        $this->travelTo('2026-11-17 12:10:01');

        $this->pending($admin)
            ->post('/admin/two-factor', ['code' => '123456'])
            ->assertSessionHasErrors(['code' => __('atrium::auth.two_factor.code_invalid')]);

        $this->assertGuest('admin');
    }

    public function test_a_code_is_still_good_in_its_last_second(): void
    {
        $admin = $this->withCode('123456');
        $this->travelTo('2026-11-17 12:09:59');

        $this->pending($admin)->post('/admin/two-factor', ['code' => '123456'])->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_a_user_with_no_code_issued_cannot_pass_with_any_code(): void
    {
        $admin = $this->admin();

        $this->assertFalse(TwoFactorCode::matches($admin, ''));

        $admin->forceFill(['two_factor_code' => Hash::make('123456')])->save();
        $this->assertFalse(TwoFactorCode::matches($admin->fresh(), '123456'), 'A code without an expiry never matches.');

        $this->pending($admin)->post('/admin/two-factor', ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertGuest('admin');
    }

    public function test_five_wrong_codes_lock_the_step_for_a_minute_even_for_the_right_one(): void
    {
        $admin = $this->withCode('123456');

        for ($i = 0; $i < 5; $i++) {
            $this->pending($admin)
                ->post('/admin/two-factor', ['code' => '000000'])
                ->assertSessionHasErrors(['code' => __('atrium::auth.two_factor.code_invalid')]);
        }

        $this->pending($admin)
            ->post('/admin/two-factor', ['code' => '123456'])
            ->assertSessionHasErrors(['code' => __('atrium::auth.two_factor.throttle', ['seconds' => 60])]);
        $this->assertGuest('admin');

        $this->travel(61)->seconds();
        $this->withCode('123456', $admin);

        $this->pending($admin)->post('/admin/two-factor', ['code' => '123456'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_four_wrong_codes_still_leave_a_try_and_a_success_resets_the_count(): void
    {
        $admin = $this->withCode('123456');

        for ($i = 0; $i < 4; $i++) {
            $this->pending($admin)->post('/admin/two-factor', ['code' => '000000']);
        }

        $this->pending($admin)->post('/admin/two-factor', ['code' => '123456'])->assertSessionHasNoErrors();

        $this->assertFalse(RateLimiter::tooManyAttempts('admin-2fa|'.$admin->id.'|127.0.0.1', 1));
    }

    public function test_an_account_switched_off_while_waiting_for_the_code_is_let_go(): void
    {
        $admin = $this->withCode('123456');
        $admin->update(['is_active' => false]);

        $this->pending($admin)
            ->post('/admin/two-factor', ['code' => '123456'])
            ->assertRedirect('/admin/login')
            ->assertSessionMissing('admin.two_factor');

        $this->assertGuest('admin');
    }

    public function test_a_pending_id_of_nobody_is_let_go(): void
    {
        $this->withSession(['admin.two_factor' => ['id' => 999, 'remember' => false]])
            ->get('/admin/two-factor')
            ->assertRedirect('/admin/login')
            ->assertSessionMissing('admin.two_factor');
    }

    public function test_a_new_code_is_not_sent_before_the_wait_is_over(): void
    {
        Mail::fake();
        $admin = $this->withCode('123456');
        $this->travel(59)->seconds();

        $this->pending($admin)
            ->post('/admin/two-factor/resend')
            ->assertSessionHasErrors(['code' => __('atrium::auth.two_factor.resend_too_soon', ['seconds' => 1])]);

        Mail::assertNothingSent();
        $this->assertTrue(TwoFactorCode::matches($admin->fresh(), '123456'));
    }

    public function test_a_new_code_is_sent_once_the_wait_is_over_and_the_old_one_stops_working(): void
    {
        Mail::fake();
        $admin = $this->withCode('123456');
        $this->travel(60)->seconds();

        $this->pending($admin)
            ->from('/admin/two-factor')
            ->post('/admin/two-factor/resend')
            ->assertRedirect('/admin/two-factor')
            ->assertSessionHas('success', __('atrium::auth.two_factor.resent'));

        $code = $this->sentCode();
        $admin->refresh();
        $this->assertTrue(TwoFactorCode::matches($admin, $code));
        $this->assertSame($code === '123456', TwoFactorCode::matches($admin, '123456'));
        $this->assertSame(60, TwoFactorCode::retryAfter($admin));
    }

    public function test_mail_that_fails_on_resend_is_named(): void
    {
        $admin = $this->withCode('123456');
        $this->travel(60)->seconds();
        Mail::shouldReceive('to')->andThrow(new RuntimeException('535 Authentication failed'));

        $this->pending($admin)
            ->post('/admin/two-factor/resend')
            ->assertSessionHasErrors(['code' => TwoFactorCode::mailFailedMessage()])
            ->assertSessionHas('admin.two_factor');
    }

    public function test_leaving_the_code_step_forgets_the_password_step(): void
    {
        $admin = $this->withCode('123456');

        $this->pending($admin)
            ->post('/admin/two-factor/cancel')
            ->assertRedirect('/admin/login')
            ->assertSessionMissing('admin.two_factor');

        $this->get('/admin/two-factor')->assertRedirect('/admin/login');
        $this->assertGuest('admin');
    }

    public function test_the_wait_before_another_e_mail_counts_down(): void
    {
        $admin = $this->admin();
        $this->assertSame(0, TwoFactorCode::retryAfter($admin));
        $this->assertTrue(TwoFactorCode::canResend($admin));

        $admin->forceFill(['two_factor_sent_at' => now()])->save();
        $this->assertSame(60, TwoFactorCode::retryAfter($admin));
        $this->assertFalse(TwoFactorCode::canResend($admin));

        $this->travel(25)->seconds();
        $this->assertSame(35, TwoFactorCode::retryAfter($admin));

        $this->travel(600)->seconds();
        $this->assertSame(0, TwoFactorCode::retryAfter($admin));
        $this->assertTrue(TwoFactorCode::canResend($admin));
    }

    public function test_the_timings_come_from_the_config(): void
    {
        config()->set('atrium.auth.two_factor.ttl', 3);
        config()->set('atrium.auth.two_factor.resend_after', 30);
        Mail::fake();
        $admin = $this->admin();

        $this->assertTrue(TwoFactorCode::send($admin));

        $this->assertSame('2026-11-17 12:03:00', $admin->fresh()->two_factor_expires_at->format('Y-m-d H:i:s'));
        $this->assertSame(30, TwoFactorCode::retryAfter($admin->fresh()));
        $this->assertFalse(TwoFactorCode::send($admin->fresh()), 'Too soon without force.');
        $this->assertTrue(TwoFactorCode::send($admin->fresh(), force: true));
        Mail::assertSent(TwoFactorCodeMail::class, fn (TwoFactorCodeMail $mail) => $mail->ttlMinutes === 3);
    }

    public function test_the_defaults_are_on_ten_minutes_and_one_minute(): void
    {
        config()->set('atrium.auth.two_factor', []);

        $this->assertTrue(TwoFactorCode::enabled());
        $this->assertSame(10, TwoFactorCode::ttlMinutes());
        $this->assertSame(60, TwoFactorCode::resendAfterSeconds());

        config()->set('atrium.auth.two_factor.enabled', false);
        $this->assertFalse(TwoFactorCode::enabled());
    }

    public function test_the_e_mail_carries_the_code_and_how_long_it_lives_in_every_language(): void
    {
        foreach ((array) config('atrium.locales') as $locale) {
            app()->setLocale($locale);
            $mail = new TwoFactorCodeMail('042137', 10);

            $mail->assertHasSubject(__('atrium::auth.mail.subject', ['code' => '042137']));
            $mail->assertSeeInHtml('042137');
            $mail->assertSeeInHtml(e(trans_choice('atrium::auth.mail.valid_for', 10)), false);
            $mail->assertSeeInHtml(e(__('atrium::auth.mail.heading')), false);
            $this->assertStringContainsString('042137', __('atrium::auth.mail.subject', ['code' => '042137']), $locale);
        }
    }

    public function test_the_e_mail_goes_to_the_administrator(): void
    {
        Mail::fake();
        $admin = $this->admin();

        TwoFactorCode::send($admin);

        Mail::assertSent(TwoFactorCodeMail::class, fn (TwoFactorCodeMail $mail) => $mail->hasTo('admin@example.com') && $mail->ttlMinutes === 10);
    }

    private function withCode(string $code, ?AdminUser $admin = null): AdminUser
    {
        $admin ??= $this->admin();
        $admin->forceFill([
            'two_factor_code' => Hash::make($code),
            'two_factor_sent_at' => now(),
            'two_factor_expires_at' => now()->addMinutes(10),
        ])->save();

        return $admin;
    }

    private function pending(AdminUser $admin, bool $remember = false): static
    {
        return $this->withSession(['admin.two_factor' => ['id' => $admin->id, 'remember' => $remember]]);
    }

    private function sentCode(): string
    {
        $code = null;

        Mail::assertSent(TwoFactorCodeMail::class, function (TwoFactorCodeMail $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });

        return (string) $code;
    }
}
