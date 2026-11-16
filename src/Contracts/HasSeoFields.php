<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphOne;

interface HasSeoFields
{
    /**
     * Get SEO meta relationship
     */
    public function seoMeta(): MorphOne;

    /**
     * Get SEO title (with template support)
     */
    public function getSeoTitle(): string;

    /**
     * Get SEO description (with template support)
     */
    public function getSeoDescription(): string;

    /**
     * Get SEO keywords
     */
    public function getSeoKeywords(): ?string;

    /**
     * Get Open Graph image URL
     */
    public function getOgImage(): ?string;
}
