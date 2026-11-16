<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Services;

use Atrium\Admin\Contracts\HasSlug;
use Atrium\Admin\Models\Slug;
use Atrium\Admin\Models\SlugHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SlugService
{
    /**
     * Transliteration map for Russian to Latin
     */
    protected array $transliteration = [
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

    /**
     * Generate slug from text
     */
    public function generate(string $text): string
    {
        $slug = $this->transliterate($text);

        return Str::slug($slug);
    }

    /**
     * Generate unique slug for model
     */
    public function generateUnique(string $text, string $modelClass, ?int $excludeId = null): string
    {
        $slug = $this->generate($text);

        return $this->ensureUnique($slug, $modelClass, $excludeId);
    }

    /**
     * Ensure slug is unique within model type
     */
    public function ensureUnique(string $slug, string $modelClass, ?int $excludeId = null, int $counter = 0): string
    {
        $testSlug = $counter > 0 ? "{$slug}-{$counter}" : $slug;

        $query = $modelClass::where('code', $testSlug);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            return $this->ensureUnique($slug, $modelClass, $excludeId, $counter + 1);
        }

        return $testSlug;
    }

    /**
     * Transliterate Russian text to Latin
     */
    public function transliterate(string $text): string
    {
        return strtr($text, $this->transliteration);
    }

    /**
     * Find model by slug with caching
     */
    public function findBySlug(string $slug, string $modelClass): ?Model
    {
        $cacheKey = $this->getCacheKey($slug, $modelClass);

        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $modelClass::find($cached['id']);
        }

        $model = $modelClass::where('code', $slug)->first();

        if ($model) {
            $this->cacheSlug($slug, $modelClass, $model->id);
        }

        return $model;
    }

    /**
     * Find model by full path with caching
     */
    public function findByPath(string $path, ?string $modelClass = null): ?Model
    {
        $cacheKey = $this->getPathCacheKey($path);

        $cached = Cache::get($cacheKey);
        if ($cached) {
            $cachedModelClass = $cached['model'];
            if (! $modelClass || $cachedModelClass === $modelClass) {
                return $cachedModelClass::find($cached['id']);
            }
        }

        $query = Slug::where('full_path', $path);
        if ($modelClass) {
            $query->where('sluggable_type', $modelClass);
        }

        $slugModel = $query->first();

        if ($slugModel && $slugModel->sluggable) {
            $this->cachePathSlug($path, get_class($slugModel->sluggable), $slugModel->sluggable_id);

            return $slugModel->sluggable;
        }

        return null;
    }

    /**
     * Check for redirect from old slug
     */
    public function findRedirect(string $slug, ?string $modelClass = null): ?string
    {
        return SlugHistory::findRedirect($slug, $modelClass);
    }

    /**
     * Check for redirect from old path
     */
    public function findRedirectPath(string $path, ?string $modelClass = null): ?string
    {
        return SlugHistory::findRedirectPath($path, $modelClass);
    }

    /**
     * Create or update slug record for model
     */
    public function syncSlug(Model $model): void
    {
        if (! $model instanceof HasSlug || empty($model->getAttribute('code'))) {
            return;
        }

        $model->slugModel()->updateOrCreate(
            [
                'sluggable_type' => get_class($model),
                'sluggable_id' => $model->getKey(),
            ],
            [
                'slug' => $model->getAttribute('code'),
                'full_path' => $model->getFullPath(),
            ]
        );

        $this->cacheSlug($model->getAttribute('code'), get_class($model), $model->getKey());
        $this->cachePathSlug($model->getFullPath(), get_class($model), $model->getKey());
    }

    /**
     * Save old slug to history
     */
    public function saveToHistory(Model $model, string $oldSlug, ?string $oldPath = null): void
    {
        SlugHistory::updateOrCreate(
            [
                'slug' => $oldSlug,
                'sluggable_type' => get_class($model),
            ],
            [
                'sluggable_id' => $model->getKey(),
                'full_path' => $oldPath ?? $oldSlug,
            ]
        );
    }

    /**
     * Clear cache for model
     */
    public function clearCache(Model $model): void
    {
        if (! $model instanceof HasSlug) {
            return;
        }

        $cacheKey = $this->getCacheKey($model->getAttribute('code'), get_class($model));
        $pathCacheKey = $this->getPathCacheKey($model->getFullPath());

        Cache::forget($cacheKey);
        Cache::forget($pathCacheKey);
    }

    /**
     * Regenerate all slugs for model class
     */
    public function regenerateAll(string $modelClass): int
    {
        $count = 0;

        $modelClass::chunk(100, function ($models) use (&$count) {
            foreach ($models as $model) {
                if ($model instanceof Model && $model instanceof HasSlug) {
                    $this->syncSlug($model);
                    $count++;
                }
            }
        });

        return $count;
    }

    /**
     * Clean up old history entries
     */
    public function cleanupHistory(int $daysOld = 365): int
    {
        return SlugHistory::cleanup($daysOld);
    }

    /**
     * Cache slug lookup
     */
    protected function cacheSlug(string $slug, string $modelClass, int $id): void
    {
        $cacheKey = $this->getCacheKey($slug, $modelClass);
        $ttl = config('atrium.slug.cache_ttl', 86400);

        Cache::put($cacheKey, [
            'model' => $modelClass,
            'id' => $id,
        ], $ttl);
    }

    /**
     * Cache path slug lookup
     */
    protected function cachePathSlug(string $path, string $modelClass, int $id): void
    {
        $cacheKey = $this->getPathCacheKey($path);
        $ttl = config('atrium.slug.cache_ttl', 86400);

        Cache::put($cacheKey, [
            'model' => $modelClass,
            'id' => $id,
        ], $ttl);
    }

    /**
     * Get cache key for slug
     */
    protected function getCacheKey(string $slug, string $modelClass): string
    {
        $prefix = config('atrium.slug.cache_prefix', 'slug');
        $type = class_basename($modelClass);

        return "{$prefix}:{$type}:{$slug}";
    }

    /**
     * Get cache key for path
     */
    protected function getPathCacheKey(string $path): string
    {
        $prefix = config('atrium.slug.cache_prefix', 'slug');

        return "{$prefix}:path:{$path}";
    }
}
