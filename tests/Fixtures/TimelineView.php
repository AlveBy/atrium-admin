<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Atrium\Admin\Views\View;

/**
 * A view another module brings: a type of its own, built on a date.
 */
final class TimelineView extends View
{
    public function type(): string
    {
        return 'timeline';
    }

    public function writes(): array
    {
        return [$this->field];
    }

    public function needs(): array
    {
        return [$this->field => ['date']];
    }
}
