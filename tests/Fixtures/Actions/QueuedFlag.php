<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\Actions;

use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The same, on the queue.
 */
final class QueuedFlag extends Flag implements ShouldQueue {}
