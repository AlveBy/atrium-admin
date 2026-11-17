<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Removing addresses from the CDN's cache: Cloudflare or BunnyCDN, with the
 * token the owner put in .env (atrium.cdn.purge).
 *
 * Changed files need no purge: their address changes with ?v=. A deleted file
 * does, or the CDN keeps serving it until its cache expires.
 */
final class CdnPurge
{
    /** Cloudflare takes this many addresses per request on every plan. */
    private const CLOUDFLARE_BATCH = 30;

    public static function configured(): bool
    {
        return in_array(config('atrium.cdn.purge.driver'), ['cloudflare', 'bunny'], true);
    }

    /**
     * @param  array<array-key,string>  $urls
     */
    public static function urls(array $urls): void
    {
        $urls = array_unique(array_filter($urls));

        if ($urls === []) {
            return;
        }

        match (config('atrium.cdn.purge.driver')) {
            'cloudflare' => self::cloudflare($urls),
            'bunny' => self::bunny($urls),
            default => throw new RuntimeException('No CDN purge is set up: ATRIUM_CDN_PURGE is cloudflare or bunny.'),
        };
    }

    public static function everything(): void
    {
        match (config('atrium.cdn.purge.driver')) {
            'cloudflare' => self::cloudflare(null),
            'bunny' => self::send(Http::withHeaders(['AccessKey' => self::setting('bunny.key')])
                ->post('https://api.bunny.net/pullzone/'.rawurlencode(self::setting('bunny.pull_zone')).'/purgeCache'), 'BunnyCDN'),
            default => throw new RuntimeException('No CDN purge is set up: ATRIUM_CDN_PURGE is cloudflare or bunny.'),
        };
    }

    /**
     * The addresses a stored file is served at through the CDN: as it is,
     * with its ?v=, and its webp copy. Call before the file is deleted.
     *
     * @return array<int,string>
     */
    public static function addressesOf(string $value): array
    {
        $path = preg_match('#^(https?:)?//#i', $value) ? null : (str_starts_with($value, '/') ? $value : '/storage/'.$value);

        if ($path === null) {
            return [$value];
        }

        $base = Cdn::base();

        if ($base === null || ! Cdn::serves($path)) {
            return [];
        }

        return array_values(array_unique([$base.$path, Cdn::asset($path), $base.$path.'.webp', Cdn::asset($path.'.webp')]));
    }

    /**
     * @param  array<array-key,string>|null  $urls  null: everything
     */
    private static function cloudflare(?array $urls): void
    {
        $request = fn (array $body) => Http::withToken(self::setting('cloudflare.token'))
            ->acceptJson()
            ->post('https://api.cloudflare.com/client/v4/zones/'.rawurlencode(self::setting('cloudflare.zone')).'/purge_cache', $body);

        if ($urls === null) {
            self::send($request(['purge_everything' => true]), 'Cloudflare');

            return;
        }

        foreach (array_chunk($urls, self::CLOUDFLARE_BATCH) as $batch) {
            self::send($request(['files' => $batch]), 'Cloudflare');
        }
    }

    /**
     * @param  array<array-key,string>  $urls
     */
    private static function bunny(array $urls): void
    {
        foreach ($urls as $url) {
            self::send(Http::withHeaders(['AccessKey' => self::setting('bunny.key')])
                ->post('https://api.bunny.net/purge?url='.rawurlencode($url)), 'BunnyCDN');
        }
    }

    private static function send(Response $response, string $cdn): void
    {
        if (! $response->successful() || $response->json('success') === false) {
            throw new RuntimeException("{$cdn} refused the purge: HTTP {$response->status()} ".mb_substr($response->body(), 0, 300));
        }
    }

    private static function setting(string $key): string
    {
        $value = config("atrium.cdn.purge.{$key}");

        if (! is_string($value) || $value === '') {
            throw new RuntimeException("atrium.cdn.purge.{$key} is empty: the CDN's id and API token go in .env.");
        }

        return $value;
    }
}
