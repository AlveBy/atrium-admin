<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Contracts;

/**
 * A model whose records the record cache keeps as cards. Use the
 * CachesRecords trait: it implements all of this.
 */
interface Cacheable
{
    /**
     * The relations a card carries, eager-loaded in one query each when
     * cards are built.
     *
     * @return array<int,string>
     */
    public function cacheRelations(): array;
}
