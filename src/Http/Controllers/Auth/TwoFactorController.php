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
 * Second sign-in step: the code from the e-mail.
 *
 * Sits between the password and the panel: the password has matched, but the
 * user is not signed in yet, the session only holds the pending user's id.
 * Until the code matches there is no access, and reloading the page or typing
 * a URL changes nothing.
 */
class TwoFactorController extends Controller
{
    /** This many attempts per code, then a minute of waiting. */
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
            ['code.required' => __('atrium::auth.two_factor.code_required')],
        );

        $this->ensureIsNotRateLimited($request, $user);

        $code = preg_replace('/\D/', '', (string) $request->input('code')) ?? '';

        if (! TwoFactorCode::matches($user, $code)) {
            RateLimiter::hit($this->throttleKey($request, $user), self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'code' => __('atrium::auth.two_factor.code_invalid'),
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

    /** The e-mail did not arrive: send it again. */
    public function resend(Request $request): RedirectResponse
    {
        $user = $this->pending($request);

        if ($user === null) {
            return redirect()->route('admin.login');
        }

        try {
            if (! TwoFactorCode::send($user)) {
                throw ValidationException::withMessages([
                    'code' => __('atrium::auth.two_factor.resend_too_soon', [
                        'seconds' => TwoFactorCode::retryAfter($user),
                    ]),
                ]);
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Could not resend the admin sign-in code: '.$e->getMessage());

            throw ValidationException::withMessages([
                'code' => TwoFactorCode::mailFailedMessage(),
            ]);
        }

        return back()->with('success', __('atrium::auth.two_factor.resent'));
    }

    /** Leave the second step without signing in. */
    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget('admin.two_factor');

        return redirect()->route('admin.login');
    }

    /** The user who has entered the password but not the code yet. */
    private function pending(Request $request): ?AdminUser
    {
        $id = $request->session()->get('admin.two_factor.id');

        if ($id === null) {
            return null;
        }

        $user = Auth::createUserProvider(config("auth.guards.{$this->guard()}.provider"))?->retrieveById($id);

        // The account may have been deactivated while the person went for the e-mail.
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
            'code' => __('atrium::auth.two_factor.throttle', [
                'seconds' => RateLimiter::availableIn($key),
            ]),
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

    /** "jo****@example.com": shows where to look, but not whose mailbox it is. */
    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        $visible = Str::substr($name, 0, min(2, Str::length($name)));

        return $visible.str_repeat('*', max(1, Str::length($name) - Str::length($visible)))
            .($domain === '' ? '' : '@'.$domain);
    }
}
