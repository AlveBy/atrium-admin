<?php

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers\Auth;

use Atrium\Admin\Auth\TwoFactorCode;
use Atrium\Admin\Http\Controllers\Controller;
use Atrium\Admin\Models\AdminUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Второй шаг входа в админку — код с почты.
 *
 * Между паролем и панелью: пароль уже сошёлся, но авторизации ещё нет — в
 * сессии лежит только идентификатор ожидающего. Пока код не сойдётся, доступа
 * в админку нет, и обновление страницы или переход по адресу ничего не меняют.
 */
class TwoFactorController extends Controller
{
    /** Столько попыток на код, потом — минута ожидания. */
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $this->pending($request);

        if ($user === null) {
            return redirect()->route('admin.login');
        }

        return Inertia::render('Admin/Auth/TwoFactor', [
            'branding' => config('atrium.branding'),
            'email' => $this->maskEmail((string) $user->email),
            'retryAfter' => TwoFactorCode::retryAfter($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->pending($request);

        if ($user === null) {
            return redirect()->route('admin.login');
        }

        $request->validate(
            ['code' => ['required', 'string']],
            ['code.required' => 'Введите код из письма.'],
        );

        $this->ensureIsNotRateLimited($request, $user);

        $code = preg_replace('/\D/', '', (string) $request->input('code')) ?? '';

        if (! TwoFactorCode::matches($user, $code)) {
            RateLimiter::hit($this->throttleKey($request, $user), self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'code' => 'Код не подошёл или устарел. Проверьте письмо или запросите новый.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request, $user));
        TwoFactorCode::forget($user);

        $remember = (bool) ($request->session()->get('admin.two_factor.remember', false));
        $request->session()->forget('admin.two_factor');

        Auth::guard($this->guard())->login($user, $remember);

        $request->session()->regenerate();

        return redirect()->intended(
            route(config('atrium.auth.redirect_after_login', 'admin.dashboard'))
        );
    }

    /** Письмо не дошло — отправить ещё раз. */
    public function resend(Request $request): RedirectResponse
    {
        $user = $this->pending($request);

        if ($user === null) {
            return redirect()->route('admin.login');
        }

        try {
            if (! TwoFactorCode::send($user)) {
                throw ValidationException::withMessages([
                    'code' => 'Письмо уже отправлено. Новое можно запросить через '
                        .TwoFactorCode::retryAfter($user).' с.',
                ]);
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Не удалось повторно отправить код входа: '.$e->getMessage());

            throw ValidationException::withMessages([
                'code' => TwoFactorCode::MAIL_FAILED_MESSAGE,
            ]);
        }

        return back()->with('success', 'Код отправлен ещё раз.');
    }

    /** Уйти со второго шага, не входя. */
    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget('admin.two_factor');

        return redirect()->route('admin.login');
    }

    /** Тот, кто уже ввёл пароль, но ещё не ввёл код. */
    private function pending(Request $request): ?AdminUser
    {
        $id = $request->session()->get('admin.two_factor.id');

        if ($id === null) {
            return null;
        }

        $user = Auth::createUserProvider(config("auth.guards.{$this->guard()}.provider"))?->retrieveById($id);

        // Пока человек ходил за письмом, учётку могли отключить.
        if (! $user instanceof AdminUser || ! $user->is_active) {
            $request->session()->forget('admin.two_factor');

            return null;
        }

        return $user;
    }

    private function ensureIsNotRateLimited(Request $request, AdminUser $user): void
    {
        $key = $this->throttleKey($request, $user);

        if (! RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return;
        }

        throw ValidationException::withMessages([
            'code' => 'Слишком много попыток. Попробуйте через '
                .RateLimiter::availableIn($key).' с.',
        ]);
    }

    private function throttleKey(Request $request, AdminUser $user): string
    {
        return 'admin-2fa|'.$user->getAuthIdentifier().'|'.$request->ip();
    }

    private function guard(): string
    {
        return config('atrium.auth.guard', 'admin');
    }

    /** «ol****@mail.ru» — чтобы было видно, куда смотреть, но не кому. */
    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        $visible = Str::substr($name, 0, min(2, Str::length($name)));

        return $visible.str_repeat('*', max(1, Str::length($name) - Str::length($visible)))
            .($domain === '' ? '' : '@'.$domain);
    }
}
