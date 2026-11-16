<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers\Auth;

use Atrium\Admin\Auth\TwoFactorCode;
use Atrium\Admin\Http\Controllers\Controller;
use Atrium\Admin\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    /**
     * Display the login view.
     */
    public function showLoginForm(): Response
    {
        return Inertia::render('Admin/Auth/Login', [
            'branding' => config('atrium.branding'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        // The password matches, but nobody gets in yet: the e-mailed code comes first.
        if (TwoFactorCode::enabled()) {
            $user = $request->validateCredentials();

            try {
                TwoFactorCode::send($user, force: true);
            } catch (\Throwable $e) {
                Log::error('Could not send the admin sign-in code: '.$e->getMessage());

                throw ValidationException::withMessages([
                    'email' => TwoFactorCode::mailFailedMessage(),
                ]);
            }

            // The session holds only the pending user's id: the actual sign-in
            // happens after the code. The session id is regenerated right here
            // so the code step cannot run on a session id planted in advance.
            $request->session()->regenerate();
            $request->session()->put('admin.two_factor', [
                'id' => $user->getAuthIdentifier(),
                'remember' => $request->boolean('remember'),
            ]);

            return redirect()->route('admin.two-factor');
        }

        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(
            route(config('atrium.auth.redirect_after_login', 'admin.dashboard'))
        );
    }

    /**
     * Destroy an authenticated session.
     */
    public function logout(Request $request): RedirectResponse
    {
        $guard = config('atrium.auth.guard', 'admin');

        Auth::guard($guard)->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route(config('atrium.auth.redirect_after_logout', 'admin.login'));
    }
}
