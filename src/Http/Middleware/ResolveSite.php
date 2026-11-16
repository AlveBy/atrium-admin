<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Middleware;

use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\Sites;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides which site a request is about.
 *
 * - The admin: the site picked in the header (kept in the session), out of
 *   the sites the administrator may work with.
 * - The API: ?site=code, if given; without it the client sees every site.
 * - Everything else: the domain and the folder, as EnterSiteFolder found
 *   them before routing.
 */
final class ResolveSite
{
    public const SESSION_KEY = 'atrium.site';

    public function __construct(private readonly Sites $sites) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Under Octane, or several requests in one test, the service outlives
        // the request: nothing may carry over from the previous one.
        $this->sites->forgetCurrent();

        if ($this->isApi($request)) {
            $code = $request->query('site');

            if (is_string($code) && $code !== '') {
                $site = $this->sites->findByCode($code);
                abort_if($site === null, 404, "No site [{$code}].");
                $this->sites->setCurrent($site);
            }
        } elseif ($this->isAdmin($request)) {
            $this->sites->setCurrent($this->adminSite($request));
        } else {
            $site = $this->sites->find($request->attributes->get(EnterSiteFolder::SITE));
            $this->sites->setCurrent($site ?? $this->sites->forHost($request->getHost()));
        }

        return $next($request);
    }

    private function adminSite(Request $request): Site
    {
        $user = auth()->guard(config('atrium.auth.guard', 'admin'))->user();
        $allowed = $user instanceof AdminUser ? $user->allowedSites() : $this->sites->all();
        $selected = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        return $allowed->first(fn ($site): bool => (string) $site->id === (string) $selected)
            ?? $allowed->first()
            ?? $this->sites->default();
    }

    private function isAdmin(Request $request): bool
    {
        $prefix = trim((string) config('atrium.prefix', 'admin'), '/');

        return $request->is($prefix, $prefix.'/*');
    }

    private function isApi(Request $request): bool
    {
        return $request->is(trim((string) config('atrium.api.prefix', 'api/v1'), '/').'/*');
    }
}
