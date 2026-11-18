<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Console\Concerns;

use Illuminate\Console\View\TaskResult;

/**
 * A step of a command with its result written next to it. The framework
 * marks a step as failed only for its own result value: a bool, even false,
 * is printed as DONE.
 */
trait ReportsSteps
{
    /**
     * @param  callable():bool  $step
     */
    protected function step(string $title, callable $step): void
    {
        $this->components->task($title, fn (): int => $step() ? TaskResult::Success->value : TaskResult::Failure->value);
    }
}
