<?php

declare(strict_types=1);

namespace Atrium\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SeoMeta extends Model
{
    protected $table = 'seo_meta';

    protected $fillable = [
        'seoable_type',
        'seoable_id',
        'title',
        'page_title',
        'description',
        'keywords',
        'og_title',
        'og_description',
        'og_image',
        'canonical_url',
        'robots',
        // Блоки «Настройки для картинок …» с вкладки SEO в Битриксе.
        'preview_picture_alt',
        'preview_picture_title',
        'preview_picture_file_name',
        'detail_picture_alt',
        'detail_picture_title',
        'detail_picture_file_name',
        'tags',
    ];

    /**
     * Get the owning seoable model
     */
    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get effective title (with fallback)
     */
    public function getEffectiveTitle(?string $default = null): string
    {
        return $this->title ?: $this->og_title ?: $default ?: '';
    }

    /**
     * Get effective description (with fallback)
     */
    public function getEffectiveDescription(?string $default = null): string
    {
        return $this->description ?: $this->og_description ?: $default ?: '';
    }

    /**
     * Get Open Graph title
     */
    public function getOgTitleAttribute($value): ?string
    {
        return $value ?: $this->title;
    }

    /**
     * Get Open Graph description
     */
    public function getOgDescriptionAttribute($value): ?string
    {
        return $value ?: $this->description;
    }

    /**
     * Get robots meta content
     */
    public function getRobotsContent(): string
    {
        return $this->robots ?: 'index, follow';
    }

    /**
     * Get Open Graph image URL
     */
    public function getOgImageUrlAttribute(): ?string
    {
        if (! $this->og_image) {
            return null;
        }

        if (str_starts_with($this->og_image, 'http')) {
            return $this->og_image;
        }

        return asset('storage/'.$this->og_image);
    }

    /**
     * Set noindex
     */
    public function noindex(): self
    {
        $this->robots = 'noindex, nofollow';

        return $this;
    }

    /**
     * Set index
     */
    public function index(): self
    {
        $this->robots = 'index, follow';

        return $this;
    }
}
