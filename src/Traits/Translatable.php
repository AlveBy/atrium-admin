<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Traits;

use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Support\Like;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Translatable\HasTranslations;

/**
 * Columns listed in the model's `$translatable` hold one value per content
 * language, as JSON: {"en": "Hello", "de": "Hallo"}. Works on MySQL,
 * PostgreSQL and SQLite.
 *
 *     use Translatable;
 *
 *     protected array $translatable = ['name', 'code', 'detail_text'];
 *
 * Reading `$model->name` gives the current locale's text, and the default
 * content language's text where a translation is missing. The admin form
 * shows a language switch for these fields by itself.
 */
trait Translatable
{
    use HasTranslations;

    public function getFallbackLocale(): string
    {
        return app(ContentLanguages::class)->default();
    }

    /**
     * Records whose column contains the text in any of the languages,
     * ignoring case. Ours rather than the package's: spatie/laravel-translatable
     * has it only from 6.15, which needs PHP 8.4.
     *
     * @param  array<int,string>|null  $locales  the site's content languages by default
     */
    protected function scopeWhereTranslationLike(Builder $query, string $column, string $search, ?array $locales = null): void
    {
        $locales ??= app(ContentLanguages::class)->codes();

        $query->where(function (Builder $query) use ($column, $locales, $search): void {
            foreach ($locales as $locale) {
                $query->orWhere(function (Builder $query) use ($column, $locale, $search): void {
                    $selector = "{$column}->{$locale}";

                    Like::contains($query->whereNotNull($selector), $selector, $search);
                });
            }
        });
    }
}
