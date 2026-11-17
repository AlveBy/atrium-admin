<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Cache;

/**
 * What a list query answered: the ids of one page in order and how many
 * records match in all.
 */
final class CachedIds
{
    /**
     * @param  array<int,int|string>  $ids
     */
    public function __construct(
        public readonly array $ids,
        public readonly int $total,
    ) {}
}
