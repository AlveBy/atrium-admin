<?php

declare(strict_types=1);

namespace Atrium\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAdminAuthenticated
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = config('atrium.auth.guard', 'admin');

        if (auth()->guard($guard)->check()) {
            return redirect()->route(config('atrium.auth.redirect_after_login', 'admin.dashboard'));
        }

        return $next($request);
    }
}
