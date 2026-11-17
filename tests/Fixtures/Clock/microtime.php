<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Middleware;

/**
 * The clock of the debug line, in the namespace of the middleware that reads
 * it: PHP calls this one instead of its own microtime() there. It tells the
 * time as it is until a test queues the instants it wants to be read.
 */
final class Clock
{
    /** @var list<float> */
    public static array $instants = [];
}

function microtime(bool $as_float = false): string|float
{
    if ($as_float && Clock::$instants !== []) {
        return array_shift(Clock::$instants);
    }

    return \microtime($as_float);
}
