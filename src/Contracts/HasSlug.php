<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphOne;

interface HasSlug
{
    /**
     * Get slug relationship
     */
    public function slugModel(): MorphOne;

    /**
     * Get the URL slug
     */
    public function getSlug(): string;

    /**
     * Get full URL path (including parent slugs if applicable)
     */
    public function getFullPath(): string;

    /**
     * Get the source field for auto-generating slug
     */
    public function getSlugSourceField(): string;

    /**
     * Generate slug from source field
     */
    public function generateSlug(): string;

    /**
     * Update slug and create redirect from old slug
     */
    public function updateSlug(string $newSlug): void;
}
