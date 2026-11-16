<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Middleware;

use Atrium\Admin\Support\ContentLanguages;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the content language of a public page from its address.
 *
 * /de/… is German if German is an active language and not the default one
 * (the default language lives without a prefix, so /en/… for it would be a
 * second address of the same page). Anything else under a prefix is a 404.
 * The prefix is removed from the route parameters, so controllers inside
 * Route::localized() take the same arguments in every language.
 */
final class SetContentLocale
{
    public function __construct(private readonly ContentLanguages $languages) {}

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $code = $route?->parameter('locale');

        if (is_string($code)) {
            abort_unless($this->languages->has($code) && $code !== $this->languages->default(), 404);
            $route->forgetParameter('locale');
        } else {
            $code = $this->languages->default();
        }

        app()->setLocale($code);

        return $next($request);
    }
}
