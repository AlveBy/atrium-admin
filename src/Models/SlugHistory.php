<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SlugHistory extends Model
{
    protected $table = 'slug_history';

    protected $fillable = [
        'slug',
        'full_path',
        'sluggable_type',
        'sluggable_id',
    ];

    /**
     * Get the owning sluggable model
     */
    public function sluggable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Find redirect target for old slug
     */
    public static function findRedirect(string $slug, ?string $type = null): ?string
    {
        $query = static::where('slug', $slug);

        if ($type) {
            $query->where('sluggable_type', $type);
        }

        $history = $query->first();

        if (! $history || ! $history->sluggable) {
            return null;
        }

        // Return current path of the model
        if (method_exists($history->sluggable, 'getFullPath')) {
            return $history->sluggable->getFullPath();
        }

        return $history->sluggable->code ?? null;
    }

    /**
     * Find redirect target for old full path
     */
    public static function findRedirectPath(string $path, ?string $type = null): ?string
    {
        $query = static::where('full_path', $path);

        if ($type) {
            $query->where('sluggable_type', $type);
        }

        $history = $query->first();

        if (! $history || ! $history->sluggable) {
            return null;
        }

        if (method_exists($history->sluggable, 'getFullPath')) {
            return $history->sluggable->getFullPath();
        }

        return $history->sluggable->code ?? null;
    }

    /**
     * Clean up old history entries
     */
    public static function cleanup(int $daysOld = 365): int
    {
        return static::where('created_at', '<', now()->subDays($daysOld))->delete();
    }
}
