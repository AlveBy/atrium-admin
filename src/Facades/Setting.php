<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Facades;

use Atrium\Admin\Models\Site;
use Atrium\Admin\Modules\Settings;
use Illuminate\Support\Facades\Facade;

/**
 * The modules' settings: `Setting::get('shop.currency')`.
 *
 * @method static mixed get(string $key, mixed $default = null, Site|int|null $site = null)
 * @method static array<string,mixed> all(string $module, Site|int|null $site = null)
 * @method static void save(string $module, array<string,mixed> $values, Site|int|null $site = null)
 *
 * @see Settings
 */
final class Setting extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Settings::class;
    }
}
