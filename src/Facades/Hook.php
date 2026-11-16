<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Facades;

use Atrium\Admin\Hooks\Hooks;
use Illuminate\Support\Facades\Facade;

/**
 * Actions and filters: `Hook::on('atrium.order.paid', ...)`,
 * `Hook::apply('atrium.search.sources', $sources)`.
 *
 * @method static void on(string $name, callable|string $listener)
 * @method static void fire(string $name, mixed ...$args)
 * @method static void filter(string $name, callable|string $callback, int $priority = 10)
 * @method static mixed apply(string $name, mixed $value, mixed ...$args)
 * @method static bool has(string $name)
 *
 * @see Hooks
 */
final class Hook extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Hooks::class;
    }
}
