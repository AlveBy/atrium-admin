<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Middleware;

use Atrium\Admin\Support\Sites;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a site that sits in a folder of its domain (example.com/site2/) as
 * if the application were installed in that folder.
 *
 * A global middleware, before routing: the folder moves from the request's
 * path to its base URL. Routes then match /de/news as usual, and every
 * address Laravel builds — url(), route(), redirects, pagination, the
 * sitemap — starts with /site2 by itself. Assets stay where they are: the
 * web server serves them from the root.
 *
 * The admin and the API are not inside any folder: /site2/admin is not the
 * admin, it is a missing page of site2.
 */
final class EnterSiteFolder
{
    /** The site the address belongs to, by id. */
    public const SITE = 'atrium.site_id';

    /** The folder the request was moved into, if any. */
    public const FOLDER = 'atrium.folder';

    public function __construct(private readonly Sites $sites) {}

    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();
        $site = $this->sites->forRequest($request->getHost(), $path);

        if ($site->folder === null) {
            $request->attributes->set(self::SITE, $site->id);

            return $next($request);
        }

        $rest = substr($path, strlen($site->folder) + 1) ?: '/';

        if ($this->isPanel($rest)) {
            return $next($request);
        }

        $request->attributes->set(self::SITE, $site->id);
        $request->attributes->set(self::FOLDER, $site->folder);

        /** @var UrlGenerator $url */
        $url = app('url');
        $assets = (fn (): ?string => $this->assetRoot)->call($url);
        $url->useAssetOrigin($assets ?? $request->root());

        // Nothing of the folder outlives the request: the next one, under
        // Octane or in a test, starts from the root again.
        $outside = clone $request;
        $this->move($request, '/'.$site->folder, $rest);
        $url->setRequest($request);

        try {
            return $next($request);
        } finally {
            $url->useAssetOrigin($assets);
            $url->setRequest($outside);
        }
    }

    /**
     * Symfony keeps the base URL and the path of a request in properties of
     * its own and offers no setter. The path is worked out (with the base URL)
     * by the caller, the base path here, then the folder moves from one to
     * the other.
     */
    private function move(Request $request, string $folder, string $rest): void
    {
        $request->getBasePath();

        (function (string $folder, string $rest): void {
            $this->baseUrl .= $folder;
            $this->basePath .= $folder;
            $this->pathInfo = $rest;
        })->call($request, $folder, $rest);
    }

    private function isPanel(string $rest): bool
    {
        $rest = trim($rest, '/').'/';

        foreach ([config('atrium.prefix', 'admin'), config('atrium.api.prefix', 'api/v1'), config('atrium.mcp.path', 'mcp')] as $prefix) {
            $prefix = trim($prefix, '/');

            if ($prefix !== '' && str_starts_with($rest, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
