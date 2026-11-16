<?php

declare(strict_types=1);

namespace Atrium\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAuthenticate
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = config('atrium.auth.guard', 'admin');

        if (! auth()->guard($guard)->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest(route(config('atrium.auth.login_route', 'admin.login')));
        }

        // Check if user is active
        $user = auth()->guard($guard)->user();
        if (! $user->is_active) {
            auth()->guard($guard)->logout();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your account is deactivated.'], 403);
            }

            return redirect()
                ->route(config('atrium.auth.login_route', 'admin.login'))
                ->withErrors(['email' => 'Ваш аккаунт деактивирован.']);
        }

        return $next($request);
    }
}
