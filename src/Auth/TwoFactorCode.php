<?php

declare(strict_types=1);

namespace Atrium\Admin\Auth;

use Atrium\Admin\Mail\TwoFactorCodeMail;
use Atrium\Admin\Models\AdminUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Sign-in confirmation code for the admin panel.
 *
 * A six-digit number: easy to read out over the phone or type from a note,
 * yet impossible to brute-force within its lifetime, because attempts are
 * limited separately (TwoFactorController).
 *
 * Only a hash is stored, so a submitted value can be checked, but nobody can
 * read the code from the database, not even us.
 */
final class TwoFactorCode
{
    /**
     * What to show when the e-mail with the code could not be sent.
     *
     * Mail can fail as a whole: Yandex, for example, lets the mailbox owner
     * switch off access for mail clients, and SMTP then answers 535 to a
     * correct password. The code is already issued but useless, since there
     * is no way in without the e-mail, so we state the reason honestly
     * instead of failing the sign-in with a 500.
     */
    public static function mailFailedMessage(): string
    {
        return __('atrium::auth.two_factor.mail_failed');
    }

    /** How long a code lives. */
    public static function ttlMinutes(): int
    {
        return (int) config('atrium.auth.two_factor.ttl', 10);
    }

    /** No second e-mail before this much time has passed. */
    public static function resendAfterSeconds(): int
    {
        return (int) config('atrium.auth.two_factor.resend_after', 60);
    }

    public static function enabled(): bool
    {
        return (bool) config('atrium.auth.two_factor.enabled', true);
    }

    /**
     * Issue a new code and e-mail it.
     *
     * @return bool false if an e-mail was requested too often
     */
    public static function send(AdminUser $user, bool $force = false): bool
    {
        if (! $force && ! self::canResend($user)) {
            return false;
        }

        $code = self::generate();

        $user->forceFill([
            'two_factor_code' => Hash::make($code),
            'two_factor_sent_at' => Carbon::now(),
            'two_factor_expires_at' => Carbon::now()->addMinutes(self::ttlMinutes()),
        ])->save();

        Mail::to($user->email)->send(new TwoFactorCodeMail($code, self::ttlMinutes()));

        return true;
    }

    /** Seconds to wait until the next e-mail (0 means right now). */
    public static function retryAfter(AdminUser $user): int
    {
        $sentAt = $user->two_factor_sent_at;

        if ($sentAt === null) {
            return 0;
        }

        $passed = Carbon::now()->diffInSeconds(Carbon::parse($sentAt), true);

        return (int) max(0, self::resendAfterSeconds() - $passed);
    }

    public static function canResend(AdminUser $user): bool
    {
        return self::retryAfter($user) === 0;
    }

    /** The code matches and has not expired yet. */
    public static function matches(AdminUser $user, string $code): bool
    {
        if (empty($user->two_factor_code) || $user->two_factor_expires_at === null) {
            return false;
        }

        if (Carbon::parse($user->two_factor_expires_at)->isPast()) {
            return false;
        }

        return Hash::check($code, $user->two_factor_code);
    }

    /** The code is single-use: wiped after a successful sign-in. */
    public static function forget(AdminUser $user): void
    {
        $user->forceFill([
            'two_factor_code' => null,
            'two_factor_sent_at' => null,
            'two_factor_expires_at' => null,
        ])->save();
    }

    /**
     * Six digits with leading zeros. random_int(), not rand(): a predictable
     * code here is the same as no second factor at all.
     */
    private static function generate(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
