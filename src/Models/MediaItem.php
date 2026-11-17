<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Models;

use Atrium\Admin\Support\Like;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Media library file.
 *
 * `url` is what ends up in a model field, and also the reuse key: the same
 * file is in the library exactly once.
 */
class MediaItem extends Model
{
    protected $table = 'media_library_items';

    protected $fillable = [
        'bitrix_id',
        'collection_id',
        'name',
        'description',
        'keywords',
        'url',
        'disk',
        'path',
        'extension',
        'mime_type',
        'size',
        'is_image',
    ];

    protected $casts = [
        'bitrix_id' => 'integer',
        'collection_id' => 'integer',
        'size' => 'integer',
        'is_image' => 'boolean',
    ];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(MediaCollection::class, 'collection_id');
    }

    public function scopeImages(Builder $query): Builder
    {
        return $query->where('is_image', true);
    }

    public function scopeDocuments(Builder $query): Builder
    {
        return $query->where('is_image', false);
    }

    /**
     * Search by name and keywords.
     *
     * File names migrated from Bitrix are hashes, so the search goes by the
     * item title ("beach.jpg") and keywords, not by the name on disk.
     */
    protected function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term): void {
            foreach (['name', 'keywords', 'description'] as $column) {
                Like::contains($q, $column, $term, 'or');
            }
        });
    }

    /**
     * Physically delete the file if Storage manages it.
     *
     * Files migrated from Bitrix live right in public/ and may be referenced by
     * content we do not control — they stay on disk and are only removed from
     * the library.
     */
    public function deleteFile(): void
    {
        if ($this->disk === null || $this->path === null) {
            return;
        }

        Storage::disk($this->disk)->delete($this->path);
    }
}
