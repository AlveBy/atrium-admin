<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Cache;

use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\FileStore;
use Illuminate\Cache\RedisStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Cache\Repository;
use Throwable;

/**
 * The Laravel cache store the record cache writes to, one interface for
 * stores with tags and stores without.
 *
 * Correctness never depends on tags: every key carries version counters
 * (see Versions). Tags are there to free memory: on Redis and Memcached
 * every entry is also tagged with its group (`atrium:{group}`) and with
 * `atrium`, so clearing a module's cache deletes its entries at once
 * instead of leaving them to expire. File and database stores have no
 * tags; bumped versions alone make their entries unreachable.
 *
 * Reads are always one multi-get of the store (MGET on Redis), even for a
 * tagged entry: Laravel's tagged `many()` does not apply the tag namespace,
 * so the tagged keys are worked out here and read from the plain store.
 */
final class CacheStore
{
    /** @var array<string,string> group => key prefix of its tagged entries */
    private array $prefixes = [];

    public function __construct(
        private readonly Repository $repository,
        private readonly bool $tags,
    ) {}

    public static function fromConfig(): self
    {
        $repository = cache()->store(config('atrium.cache.store'));
        $strategy = (string) config('atrium.cache.strategy', 'auto');
        $taggable = $repository instanceof CacheRepository && $repository->getStore() instanceof TaggableStore;

        return new self($repository, $taggable && $strategy !== 'versions');
    }

    public function repository(): Repository
    {
        return $this->repository;
    }

    public function usesTags(): bool
    {
        return $this->tags;
    }

    public function driver(): string
    {
        if (! $this->repository instanceof CacheRepository) {
            return 'custom';
        }

        $store = $this->repository->getStore();

        return strtolower((string) preg_replace('/Store$/', '', class_basename($store)));
    }

    /**
     * How much the store holds, where it can tell: the whole store (the
     * record cache shares it with the application's own entries).
     *
     * @return array{entries:int|null,bytes:int|null}
     */
    public function size(): array
    {
        $size = ['entries' => null, 'bytes' => null];

        if (! $this->repository instanceof CacheRepository) {
            return $size;
        }

        $store = $this->repository->getStore();

        try {
            if ($store instanceof RedisStore) {
                $connection = $store->connection();
                $size['entries'] = (int) $connection->command('dbsize');
                $info = (array) $connection->command('info', ['memory']);
                $memory = $info['Memory'] ?? $info;
                $size['bytes'] = isset($memory['used_memory']) ? (int) $memory['used_memory'] : null;
            } elseif ($store instanceof FileStore) {
                $entries = 0;
                $bytes = 0;

                foreach ($store->getFilesystem()->allFiles($store->getDirectory()) as $file) {
                    $entries++;
                    $bytes += $file->getSize();
                }

                $size = ['entries' => $entries, 'bytes' => $bytes];
            } elseif ($store instanceof DatabaseStore) {
                $size['entries'] = $store->getConnection()->table((string) config('cache.stores.'.config('atrium.cache.store', config('cache.default')).'.table', 'cache'))->count();
            }
        } catch (Throwable) {
            // A number on a screen is not worth an error.
        }

        return $size;
    }

    /**
     * @param  array<int,string>  $keys
     * @return array<string,mixed> key => value, null for a miss
     */
    public function many(string $group, array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $raw = array_map(fn (string $key): string => $this->key($group, $key), $keys);
        $values = $this->repository->many($raw);
        $result = [];

        foreach ($keys as $i => $key) {
            $result[$key] = $values[$raw[$i]] ?? null;
        }

        return $result;
    }

    public function get(string $group, string $key): mixed
    {
        return $this->many($group, [$key])[$key];
    }

    /**
     * @param  array<string,mixed>  $values
     */
    public function putMany(string $group, array $values, int $ttl): void
    {
        if ($values === []) {
            return;
        }

        if ($this->tags) {
            $this->repository->tags($this->tagsOf($group))->putMany($values, $ttl);

            return;
        }

        $raw = [];

        foreach ($values as $key => $value) {
            $raw[$this->key($group, (string) $key)] = $value;
        }

        $this->repository->putMany($raw, $ttl);
    }

    public function put(string $group, string $key, mixed $value, int $ttl): void
    {
        $this->putMany($group, [$key => $value], $ttl);
    }

    /**
     * @param  array<int,string>  $keys
     */
    public function forget(string $group, array $keys): void
    {
        foreach ($keys as $key) {
            $this->repository->forget($this->key($group, $key));
        }
    }

    /**
     * Delete a group's entries where the store can; elsewhere they are
     * already unreachable through bumped versions.
     */
    public function flush(?string $group = null): void
    {
        if (! $this->tags) {
            return;
        }

        $this->repository->tags([$group === null ? 'atrium' : 'atrium:'.$group])->flush();
        $this->prefixes = [];
    }

    public function reset(): void
    {
        $this->prefixes = [];
    }

    private function key(string $group, string $key): string
    {
        if (! $this->tags) {
            return 'atrium:'.$key;
        }

        // Laravel's own tagged key: sha1(namespace of the tags).':'.$key.
        $this->prefixes[$group] ??= $this->repository->tags($this->tagsOf($group))->taggedItemKey('');

        return $this->prefixes[$group].$key;
    }

    /**
     * @return array<int,string>
     */
    private function tagsOf(string $group): array
    {
        return ['atrium', 'atrium:'.$group];
    }
}
