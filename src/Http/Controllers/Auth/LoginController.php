<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers\Auth;

use Atrium\Admin\Auth\SignIn;
use Atrium\Admin\Http\Controllers\Controller;
use Atrium\Admin\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class LoginController extends Controller
{
    /**
     * Display the login view.
     */
    public function showLoginForm(): Response
    {
        return Inertia::render('Admin/Auth/Login', [
            // Buttons of the sign-in modules that are switched on: social
            // accounts, a passkey, a code by e-mail.
            'methods' => app(SignIn::class)->methods((string) config('atrium.auth.guard', 'admin')),
            // A public demo (docs/demo.md) tells everybody how to get in.
            'demo' => config('atrium.demo.enabled') ? [
                'email' => config()->string('atrium.demo.email'),
                'password' => config()->string('atrium.demo.password'),
            ] : null,
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function login(LoginRequest $request): HttpResponse
    {
        // The password matches. Whether a second step follows is up to the
        // sign-in modules (alveby/atrium-2fa): SignIn asks them.
        return app(SignIn::class)->start(
            $request,
            $request->validateCredentials(),
            (string) config('atrium.auth.guard', 'admin'),
            'password',
            $request->boolean('remember'),
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
