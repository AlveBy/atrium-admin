<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Middleware;

use Atrium\Admin\Models\SlugHistory;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SlugRedirectMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only handle 404 responses
        if ($response->getStatusCode() !== 404) {
            return $response;
        }

        // Check if auto-redirect is enabled
        if (! config('atrium.slug.auto_redirect', true)) {
            return $response;
        }

        // Get the path from the request
        $path = trim($request->path(), '/');

        if (empty($path)) {
            return $response;
        }

        // Check for redirect in slug history
        $redirect = SlugHistory::findRedirectPath($path);

        if ($redirect && $redirect !== $path) {
            return redirect('/'.$redirect, 301);
        }

        // Try just the last segment as slug: it is replaced by the current
        // code, the last segment of the record's path, and the rest stays.
        $segments = explode('/', $path);
        $redirect = SlugHistory::findRedirect(array_pop($segments));

        if (($redirect ?? '') !== '') {
            $segments[] = basename($redirect);
            $newPath = implode('/', $segments);

            if ($newPath !== $path) {
                return redirect($newPath, 301);
            }
        }

        return $response;
    }
}
