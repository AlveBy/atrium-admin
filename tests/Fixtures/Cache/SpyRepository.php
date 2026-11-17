<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\Cache;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use RuntimeException;

/**
 * A cache repository on the array store that writes down every call: the
 * keys, the time to live, the order. It can also play the stores that
 * answer differently from the array one (Memcached says false for a miss
 * and for an increment of nothing) and a store that is down.
 */
final class SpyRepository extends Repository
{
    /** @var array<int,array{0:string,1:array<int,mixed>}> */
    public array $calls = [];

    /** Say false where the array store says null or creates the key, like Memcached. */
    public bool $memcached = false;

    /** The name of a method that throws, to play a cache that is down. */
    public ?string $failing = null;

    /** A value another process writes just before every add(): it wins the race. */
    public ?int $racer = null;

    public function __construct()
    {
        parent::__construct(new ArrayStore);
    }

    /**
     * The arguments of every call of a method, in the order they were made.
     *
     * @return array<int,array<int,mixed>>
     */
    public function callsTo(string $method): array
    {
        return array_values(array_map(static fn (array $call): array => $call[1], array_filter($this->calls, static fn (array $call): bool => $call[0] === $method)));
    }

    public function many(array $keys)
    {
        $this->note('many', [$keys]);
        $values = parent::many($keys);

        if ($this->memcached) {
            $values = array_map(static fn (mixed $value): mixed => $value ?? false, $values);
        }

        return $values;
    }

    public function get($key, $default = null): mixed
    {
        $this->note('get', [$key]);

        return parent::get($key, $default);
    }

    public function put($key, $value, $ttl = null)
    {
        $this->note('put', [$key, $value, $ttl]);

        return parent::put($key, $value, $ttl);
    }

    public function putMany(array $values, $ttl = null)
    {
        $this->note('putMany', [$values, $ttl]);

        return parent::putMany($values, $ttl);
    }

    public function add($key, $value, $ttl = null)
    {
        $this->note('add', [$key, $value, $ttl]);

        if ($this->racer !== null) {
            parent::put($key, $this->racer, 100);
        }

        return parent::add($key, $value, $ttl);
    }

    public function increment($key, $value = 1)
    {
        $this->note('increment', [$key, $value]);

        if ($this->memcached && parent::get($key) === null) {
            return false;
        }

        return parent::increment($key, $value);
    }

    public function forever($key, $value)
    {
        $this->note('forever', [$key, $value]);

        return parent::forever($key, $value);
    }

    public function forget($key)
    {
        $this->note('forget', [$key]);

        return parent::forget($key);
    }

    public function tags($names)
    {
        $this->note('tags', [$names]);

        return parent::tags($names);
    }

    /**
     * @param  array<int,mixed>  $arguments
     */
    private function note(string $method, array $arguments): void
    {
        $this->calls[] = [$method, $arguments];

        if ($this->failing === $method) {
            throw new RuntimeException('The cache is down.');
        }
    }
}
