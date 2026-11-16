<?php

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

        // Try just the last segment as slug
        $segments = explode('/', $path);
        $slug = end($segments);

        $redirect = SlugHistory::findRedirect($slug);

        if ($redirect && $redirect !== $path) {
            // Replace last segment with new slug
            array_pop($segments);
            $segments[] = $redirect;
            $newPath = implode('/', $segments);

            return redirect('/'.$newPath, 301);
        }

        return $response;
    }
}
