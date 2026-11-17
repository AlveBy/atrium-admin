<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Modules;

use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The Atrium modules published on Packagist: packages of type
 * `atrium-module` or with the keyword `atrium-module`, alveby's first.
 * Asked at most every few hours; when Packagist does not answer, the list
 * is null and nothing is cached.
 */
final class Catalogue
{
    public const SEARCH = 'https://packagist.org/search.json';

    public const VENDOR = 'alveby/';

    private const CACHE_KEY = 'atrium.modules.catalogue';

    private const CACHE_SECONDS = 6 * 3600;

    public function __construct(private readonly Http $http) {}

    /**
     * @return array<int,array{name:string,description:string,url:string,downloads:int}>|null
     */
    public function packages(): ?array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        $found = [];

        foreach ([['type' => Modules::TYPE], ['tags' => Modules::TYPE]] as $query) {
            $results = $this->search($query);

            if ($results === null) {
                return null;
            }

            foreach ($results as $package) {
                $name = strtolower((string) ($package['name'] ?? ''));

                if ($name === '' || ! empty($package['abandoned'])) {
                    continue;
                }

                $found[$name] = [
                    'name' => $name,
                    'description' => (string) ($package['description'] ?? ''),
                    'url' => (string) ($package['url'] ?? "https://packagist.org/packages/{$name}"),
                    'downloads' => (int) ($package['downloads'] ?? 0),
                ];
            }
        }

        $packages = array_values($found);

        usort($packages, static fn (array $a, array $b): int => [! str_starts_with($a['name'], self::VENDOR), -$a['downloads'], $a['name']]
            <=> [! str_starts_with($b['name'], self::VENDOR), -$b['downloads'], $b['name']]);

        Cache::put(self::CACHE_KEY, $packages, self::CACHE_SECONDS);

        return $packages;
    }

    /**
     * Every page of one search, or null when Packagist does not answer.
     *
     * @param  array<string,string>  $query
     * @return array<int,array<string,mixed>>|null
     */
    private function search(array $query): ?array
    {
        $results = [];
        $url = self::SEARCH.'?'.http_build_query([...$query, 'per_page' => 100]);

        // A few pages at most: there are not thousands of modules.
        for ($page = 0; $page < 5 && $url !== null; $page++) {
            try {
                $response = $this->http->timeout(5)->acceptJson()->get($url);
            } catch (Throwable) {
                return null;
            }

            if (! $response->successful()) {
                return null;
            }

            array_push($results, ...array_values(array_filter((array) $response->json('results', []), 'is_array')));
            $next = $response->json('next');
            $url = is_string($next) && $next !== '' ? $next : null;
        }

        return $results;
    }
}
