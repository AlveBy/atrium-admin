<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Middleware;

use Atrium\Admin\Cache\CacheStore;
use Atrium\Admin\Cache\RecordCache;
use Atrium\Admin\Cache\Versions;
use Atrium\Admin\Support\Sites;
use Closure;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The whole page for anonymous visitors (off by default:
 * ATRIUM_PAGE_CACHE=true turns it on).
 *
 * A page is kept by its address (scheme, host, path, query: the language is
 * in the path) and its site, with the versions of everything it showed: the
 * record cache's lists and cards, the sites and languages, and the class of
 * every model the request read. Saving any of those makes the page build
 * again on the next visit, so it is never staler than what an admin saved;
 * the TTL (atrium.cache.ttl.pages) only bounds what Atrium cannot see,
 * such as raw queries.
 *
 * Never kept: anything but GET and HEAD, the admin panel, a visitor signed
 * in to the panel (or the site), Inertia and XHR requests, a request
 * carrying flashed messages or old input, a response that sets cookies of
 * its own, that is not a 200 HTML page, or that says no-store (or sends
 * X-Atrium-No-Cache).
 *
 * The form token: the page is kept with a placeholder instead of the
 * visitor's token and gets the current visitor's token back when served.
 *
 * Headers for a CDN: ETag and Last-Modified (304 for a matching
 * If-None-Match), and Cache-Control `public, s-maxage` for a page with no
 * form token; one with a token is `private`, since a shared copy would
 * carry somebody else's.
 */
final class CachePages
{
    public const TOKEN = '__ATRIUM_CSRF_TOKEN__';

    public const HEADER = 'X-Atrium-Cache';

    public function __construct(
        private readonly CacheStore $store,
        private readonly Versions $versions,
        private readonly RecordCache $cache,
        private readonly Sites $sites,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->applies($request)) {
            return $next($request);
        }

        $key = $this->key($request);
        $entry = $this->safely(fn (): mixed => $this->store->get('pages', $key));

        if (is_array($entry) && $this->fresh($entry)) {
            return $this->serve($request, $entry, 'HIT');
        }

        $response = $next($request);

        if (! $this->storable($response)) {
            return $response;
        }

        $entry = $this->entry($request, $response);
        $this->safely(fn () => $this->store->put('pages', $key, $entry, max(1, (int) config('atrium.cache.ttl.pages', 600))));

        return $this->serve($request, $entry, 'MISS', $response);
    }

    private function applies(Request $request): bool
    {
        if (! config('atrium.cache.pages.enabled', false) || ! config('atrium.cache.enabled', true)) {
            return false;
        }

        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return false;
        }

        if ($request->ajax() || $request->hasHeader('X-Inertia') || $request->hasHeader('X-Livewire')) {
            return false;
        }

        $admin = trim((string) config('atrium.prefix', 'admin'), '/');

        if ($admin !== '' && $request->is($admin, $admin.'/*')) {
            return false;
        }

        foreach ((array) config('atrium.cache.pages.except', []) as $pattern) {
            if ($request->is(ltrim((string) $pattern, '/'))) {
                return false;
            }
        }

        // An admin sees the edit bar and unpublished things; a signed-in
        // visitor sees their own name.
        foreach (array_unique([(string) config('atrium.auth.guard', 'admin'), (string) config('auth.defaults.guard', 'web')]) as $guard) {
            try {
                if (auth()->guard($guard)->check()) {
                    return false;
                }
            } catch (Throwable) {
                // A guard that is not configured signs nobody in.
            }
        }

        if ($request->hasSession()) {
            $session = $request->session();

            if ($session->has('errors') || (array) $session->get('_flash.old', []) !== [] || $session->hasOldInput()) {
                return false;
            }
        }

        return true;
    }

    private function key(Request $request): string
    {
        $query = $request->query();
        ksort($query);

        return 'page:'.$this->site().':'.hash('xxh128', $request->getSchemeAndHttpHost().'/'.trim($request->path(), '/').'?'.http_build_query($query));
    }

    /**
     * @param  array<string,mixed>  $entry
     */
    private function fresh(array $entry): bool
    {
        $versions = (array) ($entry['versions'] ?? []);

        if ($versions === []) {
            return false;
        }

        $now = $this->safely(fn (): array => $this->versions->get(array_keys($versions)));

        return $now !== null && $now == $versions;
    }

    private function storable(Response $response): bool
    {
        if ($response->getStatusCode() !== 200 || $response->headers->has('Content-Disposition')) {
            return false;
        }

        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return false;
        }

        // A page that must not be kept says so: Cache-Control no-store, or
        // the X-Atrium-No-Cache header.
        if ($response->headers->has('X-Atrium-No-Cache') || $response->headers->hasCacheControlDirective('no-store')) {
            return false;
        }

        // A page that holds something made for this visitor alone
        // (dontKeepPage()).
        if (! $this->cache->pageKeepable()) {
            return false;
        }

        // Cookies of the page's own (the session and form-token cookies are
        // added later, by the middleware around this one).
        if ($response->headers->getCookies() !== [] || Cookie::getQueuedCookies() !== []) {
            return false;
        }

        return is_string($response->getContent()) && $response->getContent() !== '';
    }

    /**
     * @return array<string,mixed>
     */
    private function entry(Request $request, Response $response): array
    {
        $body = (string) $response->getContent();
        $token = $request->hasSession() ? (string) $request->session()->token() : '';
        $hasToken = $token !== '' && str_contains($body, $token);

        if ($hasToken) {
            $body = str_replace($token, self::TOKEN, $body);
        }

        $names = ['all', 'site:'.$this->site(), ...$this->cache->readNames()];

        foreach ((array) config('atrium.cache.pages.depends_on', []) as $class) {
            $names[] = 'list:'.$class;
        }

        $names = array_values(array_unique($names));
        sort($names);

        return [
            'body' => $body,
            'type' => (string) $response->headers->get('Content-Type'),
            'token' => $hasToken,
            'etag' => sha1($body),
            'time' => time(),
            'versions' => $this->versions->get($names),
        ];
    }

    /**
     * @param  array<string,mixed>  $entry
     */
    private function serve(Request $request, array $entry, string $state, ?Response $original = null): Response
    {
        $etag = '"'.$entry['etag'].'"';
        $response = $original ?? new Response('', 200, ['Content-Type' => $entry['type']]);

        if ($entry['token'] && $request->hasSession()) {
            $response->setContent(str_replace(self::TOKEN, (string) $request->session()->token(), (string) $entry['body']));
        } elseif ($original === null) {
            $response->setContent((string) $entry['body']);
        }

        $response->headers->set('ETag', $etag);
        $response->setLastModified((new DateTimeImmutable)->setTimestamp((int) $entry['time']));
        $response->headers->set(self::HEADER, $state);

        if ($entry['token']) {
            $response->headers->set('Cache-Control', 'private, no-cache');
        } else {
            $shared = max(0, (int) config('atrium.cache.pages.shared_max_age', 60));
            $response->headers->set('Cache-Control', 'public, max-age=0, s-maxage='.$shared);
        }

        if (in_array($etag, $request->getETags(), true) || in_array('W/'.$etag, $request->getETags(), true)) {
            $response->setNotModified();
        }

        return $response;
    }

    private function site(): int
    {
        return $this->sites->isResolved() ? (int) $this->sites->current()->getKey() : 0;
    }

    /**
     * @template T
     *
     * @param  Closure():T  $callback
     * @return T|null
     */
    private function safely(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
