<?php

declare(strict_types=1);

namespace Atrium\Admin\Http\Middleware;

use Atrium\Admin\Models\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets into the API only with a token issued in the admin panel.
 *
 * Not `auth:sanctum`: it tries the `web` guard session first, so a request
 * from a browser with the site open would pass as a site user rather than as
 * an administrator. Here it is a Bearer token only, an administrator's only,
 * and only while that administrator is active.
 *
 * The token owner becomes the admin guard's user: everything that records
 * "who changed it" sees them exactly as with a manual edit.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // API responses are always JSON, validation errors included: without
        // this Laravel answers a failed validation with a redirect back.
        $request->headers->set('Accept', 'application/json');

        $token = PersonalAccessToken::findToken((string) $request->bearerToken());
        $user = $token?->tokenable;
        $model = config('atrium.auth.model');

        if ($token === null
            || ! $user instanceof AdminUser
            || ! $user instanceof $model
            || ! $user->is_active
            || ($token->expires_at !== null && $token->expires_at->isPast())) {
            return response()->json(['message' => 'A valid API token is required: Authorization: Bearer <token> header.'], 401);
        }

        $token->forceFill(['last_used_at' => now()])->save();

        $user->withAccessToken($token);
        auth()->guard(config('atrium.auth.guard', 'admin'))->setUser($user);
        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }
}
