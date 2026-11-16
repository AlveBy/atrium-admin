<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Middleware;

use Atrium\Admin\Auth\Permissions;
use Atrium\Admin\Models\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets an administrator only into the sections their roles allow.
 *
 * The permission is read off the route name (`admin.articles.update` →
 * `articles.update`), so every module — generic or with a controller of its
 * own — is covered without a line of code. Hiding a menu entry or a button is
 * only a courtesy; this is the check.
 */
final class AuthorizeAdminRoute
{
    public function __construct(private readonly Permissions $permissions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user(config('atrium.auth.guard', 'admin'));

        if ($user instanceof AdminUser && ! $this->permissions->allowsRoute($user, $request->route()?->getName())) {
            abort(403, __('atrium::permissions.forbidden'));
        }

        return $next($request);
    }
}
