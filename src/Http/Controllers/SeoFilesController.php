<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Support\Sitemap;
use Atrium\Admin\Support\Sites;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * robots.txt and sitemap.xml of the site the visitor came to. A site in a
 * folder has its sitemap there (/site2/sitemap.xml); robots.txt is one per
 * domain, at its root, and lists the sitemaps of every site on it.
 */
final class SeoFilesController extends Controller
{
    public function robots(Request $request, Sites $sites): Response
    {
        abort_if($sites->current()->folder !== null, 404);

        $text = $sites->current()->robotsTxt($sites->folderSites($request->getHost()));

        return response($text, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(Sitemap $sitemap): Response
    {
        return $this->xml($sitemap->index());
    }

    public function sitemapPart(Sitemap $sitemap, int $part): Response
    {
        abort_if($part < 1 || $part > $sitemap->parts() || $sitemap->parts() < 2, 404);

        return $this->xml($sitemap->part($part));
    }

    private function xml(string $xml): Response
    {
        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
