<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Atrium\Admin\Contracts\HasSlug;
use Atrium\Admin\Traits\HasAdminFiles;
use Atrium\Admin\Traits\HasAdminSeo;
use Atrium\Admin\Traits\HasAdminSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A record with a slug, SEO and files: an article that may sit under another.
 */
class Handbook extends Model implements HasSlug
{
    use HasAdminFiles, HasAdminSeo, HasAdminSlug;

    protected $table = 'articles';

    protected $guarded = [];

    /**
     * @return BelongsTo<self,$this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function getParentPath(): string
    {
        return $this->parent?->getFullPath() ?? '';
    }

    public function transliterated(string $text): string
    {
        return $this->transliterate($text);
    }
}
