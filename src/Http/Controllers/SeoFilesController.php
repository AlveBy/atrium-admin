<?php

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Support\Sitemap;
use Atrium\Admin\Support\Sites;
use Illuminate\Http\Response;

/**
 * robots.txt and sitemap.xml of the site the visitor came to.
 */
final class SeoFilesController extends Controller
{
    public function robots(Sites $sites): Response
    {
        return response($sites->current()->robotsTxt(), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
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
