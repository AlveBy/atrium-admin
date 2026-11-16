<?php

declare(strict_types=1);

namespace Atrium\Admin\Http\Middleware;

use Atrium\Admin\Models\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Пускает в API только по токену, выпущенному в админке.
 *
 * Не `auth:sanctum`: тот сначала пробует сессию гарда `web`, и запрос
 * с открытой вкладкой сайта прошёл бы как пользователь сайта, а не как
 * администратор. Здесь — только Bearer-токен, только администратора и только
 * пока тот активен.
 *
 * Владелец токена становится пользователем гарда админки: всё, что пишет
 * «кто изменил», видит его так же, как при правке руками.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // Ответы API — всегда JSON, в том числе ошибки проверки: без этого
        // Laravel отвечает на неудачную валидацию редиректом назад.
        $request->headers->set('Accept', 'application/json');

        $token = PersonalAccessToken::findToken((string) $request->bearerToken());
        $user = $token?->tokenable;
        $model = config('atrium.auth.model');

        if ($token === null
            || ! $user instanceof AdminUser
            || ! $user instanceof $model
            || ! $user->is_active
            || ($token->expires_at !== null && $token->expires_at->isPast())) {
            return response()->json(['message' => 'Нужен действующий API-токен: заголовок Authorization: Bearer <токен>.'], 401);
        }

        $token->forceFill(['last_used_at' => now()])->save();

        $user->withAccessToken($token);
        auth()->guard(config('atrium.auth.guard', 'admin'))->setUser($user);
        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }
}
