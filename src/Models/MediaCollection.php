<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Media library collection — a folder the images are grouped in.
 */
class MediaCollection extends Model
{
    protected $table = 'media_library_collections';

    protected $fillable = [
        'bitrix_id',
        'parent_id',
        'name',
        'description',
        'keywords',
        'sort',
    ];

    protected $casts = [
        'bitrix_id' => 'integer',
        'parent_id' => 'integer',
        'sort' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(MediaItem::class, 'collection_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
