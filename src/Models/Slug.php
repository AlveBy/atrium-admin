<?php

declare(strict_types=1);

namespace Atrium\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Slug extends Model
{
    protected $table = 'slugs';

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
     * Find model by slug
     */
    public static function findBySlug(string $slug, ?string $type = null): ?self
    {
        $query = static::where('slug', $slug);

        if ($type) {
            $query->where('sluggable_type', $type);
        }

        return $query->first();
    }

    /**
     * Find model by full path
     */
    public static function findByPath(string $path, ?string $type = null): ?self
    {
        $query = static::where('full_path', $path);

        if ($type) {
            $query->where('sluggable_type', $type);
        }

        return $query->first();
    }

    /**
     * Resolve model from slug
     */
    public static function resolve(string $slug, ?string $type = null): ?Model
    {
        $slugModel = static::findBySlug($slug, $type);

        return $slugModel?->sluggable;
    }

    /**
     * Resolve model from full path
     */
    public static function resolvePath(string $path, ?string $type = null): ?Model
    {
        $slugModel = static::findByPath($path, $type);

        return $slugModel?->sluggable;
    }
}
