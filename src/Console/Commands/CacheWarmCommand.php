<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Console\Commands;

use Atrium\Admin\Api\ApiResources;
use Atrium\Admin\Cache\RecordCache;
use Atrium\Admin\Contracts\Cacheable;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Support\Sites;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Throwable;

/**
 * Fill the cache before visitors come: after a deploy or a cleared cache.
 *
 * Opens the home page and the first page of every list of every site in
 * every language, the way a visitor would (so menus, lists, cards and, when
 * on, whole pages are kept). With --records it also builds the card of
 * every record of every cached module.
 */
class CacheWarmCommand extends Command
{
    protected $signature = 'atrium:cache:warm
        {--records : Also build the card of every record of every cached module}
        {--site=* : Only these sites (code)}';

    protected $description = 'Fill the record cache (and the page cache, when on) for every site and language';

    public function handle(RecordCache $cache, Sites $sites, ContentLanguages $languages, Kernel $kernel, ApiResources $resources): int
    {
        if (! config('atrium.cache.enabled', true)) {
            $this->warn('The record cache is off (ATRIUM_CACHE_ENABLED=false): nothing to warm.');

            return self::SUCCESS;
        }

        // Warming must not leave a session file behind per page.
        config(['session.driver' => 'array']);

        $only = array_filter((array) $this->option('site'));
        $pages = 0;
        $cards = 0;

        foreach ($sites->all() as $site) {
            if (! $site->active || ($only !== [] && ! in_array($site->code, $only, true))) {
                continue;
            }

            $sites->setCurrent($site);
            $languages->forgetActive();
            $codes = $languages->codes();
            $base = $this->base($site);

            foreach ($codes as $code) {
                foreach ($cache->warmPaths($code) as $path) {
                    $status = $this->open($kernel, $base.$path);
                    $pages++;
                    $this->line(sprintf('  %s %s', $status, $base.$path), verbosity: $status === 200 ? 'v' : 'normal');
                }
            }

            if ($this->option('records')) {
                $cards += $this->buildCards($cache, $resources, $codes);
            }
        }

        $sites->forgetCurrent();
        $this->info($this->option('records')
            ? sprintf('Opened %d pages and built %d cards.', $pages, $cards)
            : sprintf('Opened %d pages.', $pages));

        return self::SUCCESS;
    }

    private function open(Kernel $kernel, string $url): int
    {
        try {
            $request = Request::create($url, 'GET');
            $response = $kernel->handle($request);
            $kernel->terminate($request, $response);

            return $response->getStatusCode();
        } catch (Throwable $e) {
            report($e);

            return 500;
        }
    }

    /**
     * @param  array<int,string>  $codes
     */
    private function buildCards(RecordCache $cache, ApiResources $resources, array $codes): int
    {
        $built = 0;

        // Modules of the panel, and the models the theme gives addresses to.
        $classes = [...array_values($resources->modules()), ...array_keys((array) config('atrium.public_urls', []))];

        foreach (array_unique($classes) as $class) {
            if (! is_string($class) || ! class_exists($class)) {
                continue;
            }

            if (! is_subclass_of($class, Cacheable::class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            $class::query()->select((new $class)->getQualifiedKeyName())->chunkById(500, function ($records) use ($cache, $class, $codes, &$built): void {
                $ids = $records->modelKeys();

                foreach ($codes as $code) {
                    $built += count($cache->cards($class, $ids, $code));
                }

                $cache->reset();
            });
        }

        return $built;
    }

    /**
     * Where the site is: its first domain (the app's URL when it has none)
     * and its folder.
     */
    private function base(Site $site): string
    {
        $url = rtrim((string) config('app.url', 'http://localhost'), '/');
        $scheme = (string) (parse_url($url, PHP_URL_SCHEME) ?: 'http');
        $domain = ((array) $site->domains)[0] ?? null;
        $root = is_string($domain) && $domain !== '' ? $scheme.'://'.$domain : $url;

        return rtrim($root, '/').($site->folder ? '/'.trim((string) $site->folder, '/') : '');
    }
}
