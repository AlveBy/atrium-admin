<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Support;

use Atrium\Admin\Tests\Fixtures\OpcacheSpy;

/**
 * Stands in for opcache_invalidate() inside the Support namespace: PHP looks
 * a function up here before the global one, so a test hears the call.
 */
function opcache_invalidate(string $file, bool $force = false): bool
{
    OpcacheSpy::$calls[] = [$file, $force];

    return true;
}

namespace Atrium\Admin\Tests\Fixtures;

final class OpcacheSpy
{
    /** @var list<array{string,bool}> */
    public static array $calls = [];
}
