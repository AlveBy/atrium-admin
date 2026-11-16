<?php

declare(strict_types=1);

namespace Atrium\Admin\Auth;

use Atrium\Admin\Mail\TwoFactorCodeMail;
use Atrium\Admin\Models\AdminUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Код подтверждения входа в админку.
 *
 * Выдаём шестизначное число: его можно продиктовать по телефону и набрать с
 * бумажки, а перебрать за отведённые минуты — нет, потому что попытки
 * ограничены отдельно (TwoFactorController).
 *
 * В базе лежит хеш, поэтому сверять можно только присланное значение, а
 * прочитать код из базы нельзя даже нам.
 */
final class TwoFactorCode
{
    /**
     * Что показать, если письмо с кодом не ушло.
     *
     * Почта отказывает целиком: у Яндекса, например, доступ почтовым
     * программам выключается в настройках ящика, и SMTP отвечает 535 на
     * верный пароль. Код при этом уже выдан, но толку от него нет — попасть
     * внутрь без письма нельзя, поэтому честно говорим причину, а не
     * роняем вход пятисоткой.
     */
    public const MAIL_FAILED_MESSAGE = 'Код подтверждения не удалось отправить: почта недоступна. Проверьте настройки отправки почты.';

    /** Столько живёт код. */
    public static function ttlMinutes(): int
    {
        return (int) config('atrium.auth.two_factor.ttl', 10);
    }

    /** Раньше этого срока второе письмо не отправляем. */
    public static function resendAfterSeconds(): int
    {
        return (int) config('atrium.auth.two_factor.resend_after', 60);
    }

    public static function enabled(): bool
    {
        return (bool) config('atrium.auth.two_factor.enabled', true);
    }

    /**
     * Выдать новый код и отправить его письмом.
     *
     * @return bool false, если письмо просили слишком часто
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

    /** Сколько секунд ждать до следующего письма (0 — можно сейчас). */
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

    /** Код подходит и ещё не просрочен. */
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

    /** Код одноразовый: после удачного входа стираем. */
    public static function forget(AdminUser $user): void
    {
        $user->forceFill([
            'two_factor_code' => null,
            'two_factor_sent_at' => null,
            'two_factor_expires_at' => null,
        ])->save();
    }

    /**
     * Шестизначный код с ведущими нулями — random_int, а не rand():
     * предсказуемый код здесь равен отсутствию второго фактора.
     */
    private static function generate(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
