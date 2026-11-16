<?php

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
        // Пароль сходится — но внутрь пока не пускаем: сначала код с почты.
        if (TwoFactorCode::enabled()) {
            $user = $request->validateCredentials();

            try {
                TwoFactorCode::send($user, force: true);
            } catch (\Throwable $e) {
                Log::error('Не удалось отправить код входа в админку: '.$e->getMessage());

                throw ValidationException::withMessages([
                    'email' => TwoFactorCode::MAIL_FAILED_MESSAGE,
                ]);
            }

            // В сессии — только идентификатор ожидающего: сама авторизация
            // произойдёт после кода. Идентификатор сессии меняем здесь же,
            // чтобы шаг с кодом не проходил по чужому, заранее подсунутому.
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
