<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Middleware;

use Atrium\Admin\Cache\RecordCache;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * One line per page in local development: how many queries the page ran
 * and how many records came from the cache. Sent as the X-Atrium-Debug
 * header and, on an HTML page, as a small bar at the bottom.
 *
 * Only with APP_ENV=local, APP_DEBUG=true and atrium.cache.debug on
 * (ATRIUM_CACHE_DEBUG, on by default): never on a live site.
 */
final class CacheDebug
{
    public const HEADER = 'X-Atrium-Debug';

    private static bool $listening = false;

    private static int $queries = 0;

    private static float $queryMs = 0.0;

    public function __construct(private readonly RecordCache $cache) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::enabled()) {
            return $next($request);
        }

        self::listen();
        self::$queries = 0;
        self::$queryMs = 0.0;
        $start = microtime(true);

        $response = $next($request);

        $stats = $this->cache->requestStats();
        $page = $response->headers->get(CachePages::HEADER, 'off');
        $line = sprintf(
            'queries=%d (%.1f ms); cache hits=%d misses=%d; page cache=%s; %.1f ms',
            self::$queries,
            self::$queryMs,
            $stats['hits'],
            $stats['misses'],
            strtolower($page),
            (microtime(true) - $start) * 1000,
        );

        $response->headers->set(self::HEADER, $line);
        $content = $response->getContent();

        if (is_string($content) && str_contains((string) $response->headers->get('Content-Type'), 'text/html') && ($at = strripos($content, '</body>')) !== false) {
            $bar = '<div data-atrium-debug style="position:fixed;left:0;bottom:0;z-index:2147483647;padding:2px 8px;font:12px/1.6 ui-monospace,monospace;background:#111;color:#eee;opacity:.85;pointer-events:none">Atrium: '.e($line).'</div>';
            $response->setContent(substr($content, 0, $at).$bar.substr($content, $at));
        }

        return $response;
    }

    public static function enabled(): bool
    {
        return config('atrium.cache.debug', true)
            && config('app.debug', false)
            && app()->environment('local');
    }

    private static function listen(): void
    {
        if (self::$listening) {
            return;
        }

        self::$listening = true;

        DB::listen(static function ($query): void {
            self::$queries++;
            self::$queryMs += $query->time;
        });
    }
}
