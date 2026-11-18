<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Middleware;

use Atrium\Admin\Models\PageTiming;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Records generation time of public pages.
 *
 * The write happens in terminate(), after the response has gone to the
 * client — the visitor does not pay for the measurement.
 *
 * Only what a visitor sees is counted: GET, successful, HTML, not the admin
 * panel and not XHR. Otherwise form ajax handlers and static files would get
 * into the median and "site speed" would stop meaning site speed.
 */
class RecordPageTiming
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! config('atrium.dashboard.timings.enabled', true)) {
            return;
        }

        if (! $this->shouldRecord($request, $response)) {
            return;
        }

        $rate = config('atrium.dashboard.timings.sample_rate', 1.0);

        if ($rate < 1.0 && mt_rand() / mt_getrandmax() > $rate) {
            return;
        }

        // A measurement is not worth breaking the page over: a failed insert
        // (no table, database down) must go unnoticed.
        try {
            PageTiming::create([
                'path' => mb_substr('/'.ltrim($request->path(), '/'), 0, 255),
                'duration_ms' => $this->durationMs($request),
            ]);

            $this->prune();
        } catch (Throwable) {
            // Silently: the response is already sent, nothing worth logging here.
        }
    }

    private function shouldRecord(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || $request->ajax() || $request->hasHeader('X-Inertia')) {
            return false;
        }

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return false;
        }

        $adminPrefix = trim((string) config('atrium.prefix', 'admin'), '/');

        return $adminPrefix === '' || ! $request->is($adminPrefix, $adminPrefix.'/*');
    }

    private function durationMs(Request $request): int
    {
        $now = now()->getPreciseTimestamp(6) / 1000000;
        $ms = (int) round(($now - $request->server('REQUEST_TIME_FLOAT', $now)) * 1000);

        // Upper bound, so a single timeout does not stretch the scale to infinity.
        return max(0, min($ms, 600000));
    }

    /**
     * Deletes measurements older than the report window.
     *
     * Once per hundred writes, so as not to run DELETE on every hit and not to
     * need a cron for it: the gadget must work on a bare install.
     */
    private function prune(): void
    {
        if (mt_rand(1, 100) !== 1) {
            return;
        }

        $days = max(1, (int) config('atrium.dashboard.timings.keep_days', 30));

        PageTiming::where('created_at', '<', now()->subDays($days))->delete();
    }
}
