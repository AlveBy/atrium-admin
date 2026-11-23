<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Support;

/**
 * Adds a module's entry to a list in the config, once.
 *
 * A service provider that calls config()->push() on every boot adds its
 * entry twice after `php artisan config:cache`: the cached file already holds
 * it from the boot that wrote the cache. Then the search box shows up twice
 * in the header and a gadget twice on the dashboard.
 *
 * @example
 * ConfigList::add('atrium.dashboard.gadgets', ErrorsGadget::class);
 */
final class ConfigList
{
    public static function add(string $key, string $value): void
    {
        $list = (array) config($key, []);

        if (! in_array($value, $list, true)) {
            config([$key => [...$list, $value]]);
        }
    }
}
