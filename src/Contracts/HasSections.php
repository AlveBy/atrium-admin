<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

interface HasSections
{
    /**
     * Get the section relationship
     */
    public function section(): BelongsTo;

    /**
     * Get root sections (no parent)
     */
    public static function getRootSections(): iterable;

    /**
     * Get children sections
     */
    public function children(): HasMany;

    /**
     * Get sections as tree structure
     */
    public static function getSectionsTree(): array;
}
