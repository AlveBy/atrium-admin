<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Cache;

use Illuminate\Contracts\Cache\Repository;

/**
 * Version counters: the part of a cache key that makes a whole group of
 * entries stale at once without deleting any of them.
 *
 * Every key the record cache builds carries the versions of what it depends
 * on (the model's list, the site, the classes its relations point to).
 * Bumping a version changes the keys, so the old entries are never read
 * again and expire by themselves. This works on every Laravel store, the
 * file and database ones included, which have no tags.
 *
 * Versions are read once per request in one multi-get and kept in memory
 * until something bumps them or the request ends.
 *
 * A missing counter starts at a random number, not at 1: after an eviction
 * a counter that restarted at 1 could meet entries written under the old 1.
 */
final class Versions
{
    private const PREFIX = 'atrium:v:';

    /** Counters live a month; one that expires restarts at a random number. */
    private const TTL = 2592000;

    /** @var array<string,string> */
    private array $memo = [];

    public function __construct(private readonly Repository $store) {}

    /**
     * @param  array<int,string>  $names
     * @return array<string,string> name => version
     */
    public function get(array $names): array
    {
        $names = array_values(array_unique($names));
        $missing = array_values(array_filter($names, fn (string $name): bool => ! isset($this->memo[$name])));

        if ($missing !== []) {
            $keys = array_map(static fn (string $name): string => self::PREFIX.$name, $missing);
            $values = $this->store->many($keys);

            foreach ($missing as $i => $name) {
                $value = $values[$keys[$i]] ?? null;

                if ($value === null || $value === false) {
                    $value = $this->start($name);
                }

                $this->memo[$name] = (string) $value;
            }
        }

        return array_intersect_key($this->memo, array_flip($names));
    }

    /**
     * One short string for a set of versions, to put into a key.
     *
     * @param  array<int,string>  $names
     */
    public function stamp(array $names): string
    {
        sort($names);
        $versions = $this->get($names);

        return substr(hash('xxh128', implode('|', array_map(static fn (string $name): string => $name.'='.$versions[$name], $names))), 0, 16);
    }

    public function bump(string ...$names): void
    {
        foreach (array_unique($names) as $name) {
            $key = self::PREFIX.$name;

            // Redis and the array store create a missing counter at 1;
            // ours never are 1, so that means it was not there.
            $value = $this->store->increment($key);

            if ($value === false || (int) $value <= 1) {
                $this->store->put($key, random_int(1, PHP_INT_MAX >> 1), self::TTL);
            }

            unset($this->memo[$name]);
        }
    }

    /**
     * Forget what was read: the next request (under Octane, in a queue
     * worker) must see versions bumped by other processes.
     */
    public function reset(): void
    {
        $this->memo = [];
    }

    private function start(string $name): string
    {
        $key = self::PREFIX.$name;
        $this->store->add($key, random_int(1, PHP_INT_MAX >> 1), self::TTL);

        return (string) $this->store->get($key);
    }
}
