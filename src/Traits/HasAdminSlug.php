<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Traits;

use Atrium\Admin\Models\Slug;
use Atrium\Admin\Models\SlugHistory;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

trait HasAdminSlug
{
    /**
     * Get slug relationship
     */
    public function slugModel(): MorphOne
    {
        return $this->morphOne(Slug::class, 'sluggable');
    }

    /**
     * Boot the trait
     */
    public static function bootHasAdminSlug(): void
    {
        static::creating(function ($model) {
            // Generate slug if not provided
            if (empty($model->code) && $model->getSlugSourceField()) {
                $model->code = $model->generateSlug();
            }
        });

        static::created(function ($model) {
            // Create slug record
            $model->createSlugRecord();
        });

        static::updating(function ($model) {
            // If code changed, save old slug to history
            if ($model->isDirty('code') && $model->getOriginal('code')) {
                $model->saveSlugToHistory($model->getOriginal('code'));
            }
        });

        static::updated(function ($model) {
            // Update slug record
            $model->updateSlugRecord();
        });

        static::deleting(function ($model) {
            // Delete slug and history
            $model->slugModel()->delete();
            SlugHistory::where('sluggable_type', static::class)
                ->where('sluggable_id', $model->id)
                ->delete();

            // Clear cache
            $model->clearSlugCache();
        });
    }

    /**
     * Get the URL slug
     */
    public function getSlug(): string
    {
        return $this->code ?? '';
    }

    /**
     * Get full URL path
     */
    public function getFullPath(): string
    {
        $parts = [];

        // If has parent (like section), include parent path
        if (method_exists($this, 'getParentPath')) {
            $parentPath = $this->getParentPath();
            if ($parentPath) {
                $parts[] = $parentPath;
            }
        }

        $parts[] = $this->getSlug();

        return implode('/', array_filter($parts));
    }

    /**
     * Get the source field for auto-generating slug
     */
    public function getSlugSourceField(): string
    {
        return 'name';
    }

    /**
     * Generate slug from source field
     */
    public function generateSlug(): string
    {
        $sourceField = $this->getSlugSourceField();
        $source = $this->$sourceField ?? '';

        // Transliterate and slugify
        $slug = $this->transliterate($source);
        $slug = Str::slug($slug);

        // Ensure uniqueness
        $slug = $this->ensureUniqueSlug($slug);

        return $slug;
    }

    /**
     * Ensure slug is unique
     */
    protected function ensureUniqueSlug(string $slug, int $counter = 0): string
    {
        $testSlug = $counter > 0 ? "{$slug}-{$counter}" : $slug;

        $exists = static::where('code', $testSlug)
            ->where('id', '!=', $this->id ?? 0)
            ->exists();

        if ($exists) {
            return $this->ensureUniqueSlug($slug, $counter + 1);
        }

        return $testSlug;
    }

    /**
     * Update slug and create redirect from old slug
     */
    public function updateSlug(string $newSlug): void
    {
        $oldSlug = $this->code;

        if ($oldSlug === $newSlug) {
            return;
        }

        // Ensure new slug is unique
        $newSlug = $this->ensureUniqueSlug($newSlug);

        // Save old slug to history
        if ($oldSlug) {
            $this->saveSlugToHistory($oldSlug);
        }

        // Update slug
        $this->code = $newSlug;
        $this->saveQuietly();

        // Update slug record
        $this->updateSlugRecord();
    }

    /**
     * Create slug record
     */
    protected function createSlugRecord(): void
    {
        if (! $this->code) {
            return;
        }

        $this->slugModel()->create([
            'slug' => $this->code,
            'full_path' => $this->getFullPath(),
        ]);

        $this->cacheSlug();
    }

    /**
     * Update slug record
     */
    protected function updateSlugRecord(): void
    {
        $this->slugModel()->updateOrCreate(
            ['sluggable_type' => static::class, 'sluggable_id' => $this->id],
            [
                'slug' => $this->code,
                'full_path' => $this->getFullPath(),
            ]
        );

        $this->cacheSlug();
    }

    /**
     * Save slug to history for redirects
     */
    protected function saveSlugToHistory(string $oldSlug): void
    {
        SlugHistory::updateOrCreate(
            [
                'slug' => $oldSlug,
                'sluggable_type' => static::class,
            ],
            [
                'sluggable_id' => $this->id,
                'full_path' => $this->getFullPath(),
            ]
        );
    }

    /**
     * Cache slug for fast lookup
     */
    protected function cacheSlug(): void
    {
        $cacheKey = $this->getSlugCacheKey();
        $ttl = config('atrium.slug.cache_ttl', 86400);

        Cache::put($cacheKey, [
            'model' => static::class,
            'id' => $this->id,
        ], $ttl);
    }

    /**
     * Clear slug cache
     */
    protected function clearSlugCache(): void
    {
        Cache::forget($this->getSlugCacheKey());
    }

    /**
     * Get slug cache key
     */
    protected function getSlugCacheKey(): string
    {
        $prefix = config('atrium.slug.cache_prefix', 'slug');

        return "{$prefix}:{$this->getFullPath()}";
    }

    /**
     * Find model by slug (with caching)
     */
    public static function findBySlug(string $slug): ?static
    {
        $prefix = config('atrium.slug.cache_prefix', 'slug');
        $cacheKey = "{$prefix}:{$slug}";

        // Try cache first
        $cached = Cache::get($cacheKey);
        if ($cached && $cached['model'] === static::class) {
            return static::query()->whereKey($cached['id'])->first();
        }

        // Query database
        $model = static::query()->where('code', $slug)->first();

        if ($model) {
            Cache::put($cacheKey, [
                'model' => static::class,
                'id' => $model->id,
            ], config('atrium.slug.cache_ttl', 86400));
        }

        return $model;
    }

    /**
     * Find model by full path
     */
    public static function findByFullPath(string $path): ?static
    {
        $slug = Slug::where('full_path', $path)
            ->where('sluggable_type', static::class)
            ->first();

        return $slug?->sluggable instanceof static ? $slug->sluggable : null;
    }

    /**
     * Check for redirect from old slug
     */
    public static function findRedirect(string $slug): ?string
    {
        $history = SlugHistory::where('slug', $slug)
            ->where('sluggable_type', static::class)
            ->first();

        if ($history?->sluggable instanceof static) {
            return $history->sluggable->getFullPath();
        }

        return null;
    }

    /**
     * Transliterate Russian to Latin
     */
    protected function transliterate(string $text): string
    {
        $transliteration = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D',
            'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I',
            'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N',
            'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
            'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'Ts', 'Ч' => 'Ch',
            'Ш' => 'Sh', 'Щ' => 'Sch', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '',
            'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
        ];

        return strtr($text, $transliteration);
    }
}
