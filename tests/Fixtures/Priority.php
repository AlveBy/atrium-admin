<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

/**
 * An int-backed enum without labels, for the select field.
 */
enum Priority: int
{
    case Low = 1;
    case High = 2;
}
