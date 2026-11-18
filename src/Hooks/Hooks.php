<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Hooks;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * The points where modules meet: actions and filters, by name.
 *
 * An action says that something happened (`atrium.order.paid`): every
 * listener is called, nothing comes back. Actions are Laravel events named
 * by a string, so a listener may be a closure, a class with handle() or a
 * queued listener (ShouldQueue), and Event::listen() works the same.
 *
 * A filter lets modules change a value (`atrium.search.sources`): each
 * callback gets what the one before returned, lower priority first.
 *
 * Firing a hook nobody listens to costs one array lookup.
 */
final class Hooks
{
    /** @var array<string,array<int,array<int,callable|string>>> by name, then priority */
    private array $filters = [];

    public function __construct(
        private readonly Dispatcher $events,
        private readonly Container $container,
    ) {}

    /**
     * Listen to an action.
     *
     * @param  callable|class-string|string  $listener  a closure, a class with handle(), or "Class@method"
     */
    public function on(string $name, callable|string $listener): void
    {
        $this->events->listen($name, $listener);
    }

    /**
     * Fire an action: every listener gets the arguments.
     */
    public function fire(string $name, mixed ...$args): void
    {
        if ($this->events->hasListeners($name)) {
            $this->events->dispatch($name, array_values($args));
        }
    }

    /**
     * Add a filter. It gets the value and the hook's other arguments and
     * returns the value, changed or not. Lower priority runs first.
     *
     * @param  callable|class-string  $callback  a closure or an invokable class
     */
    public function filter(string $name, callable|string $callback, int $priority = 10): void
    {
        $this->filters[$name][$priority][] = $callback;
    }

    /**
     * Run a value through the filters of a name.
     */
    public function apply(string $name, mixed $value, mixed ...$args): mixed
    {
        if (! isset($this->filters[$name])) {
            return $value;
        }

        foreach ($this->sort($name) as $callback) {
            $value = (is_string($callback) && ! is_callable($callback) ? $this->container->make($callback) : $callback)($value, ...array_values($args));
        }

        return $value;
    }

    /**
     * Whether anything listens to an action or filters a value of this name.
     */
    public function has(string $name): bool
    {
        return isset($this->filters[$name]) || $this->events->hasListeners($name);
    }

    /**
     * @return array<int,callable|string>
     */
    private function sort(string $name): array
    {
        $byPriority = $this->filters[$name];
        ksort($byPriority);

        return array_merge(...$byPriority);
    }
}
