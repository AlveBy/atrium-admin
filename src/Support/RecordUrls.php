<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Support;

use Atrium\Admin\Contracts\Sitemapable;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * The public address of any record in a content language, for the packages
 * that link to records of other modules: menus, search, related records.
 *
 * A module that knows its pages answers itself (Sitemapable::sitemapUrl()).
 * Other packages add what they draw: the theme answers for every model it
 * has pages for. Last comes the address the slug field shows.
 *
 *     app(RecordUrls::class)->extend(function (Model $record, string $language): string|false|null {
 *         return $record instanceof Article ? ($this->urlOf($record, $language) ?? false) : null;
 *     });
 *
 * A resolver returns the address, false when the record is its own but has
 * no page in that language, or null when the record is not its business.
 */
final class RecordUrls
{
    /** @var array<int,Closure(Model,string):(string|false|null)> */
    private array $resolvers = [];

    /**
     * @param  Closure(Model,string):(string|false|null)  $resolver
     */
    public function extend(Closure $resolver): void
    {
        $this->resolvers[] = $resolver;
    }

    /**
     * The absolute address of the record's page, or null when it has none in
     * this language (the current one by default).
     */
    public function url(Model $record, ?string $language = null): ?string
    {
        $language ??= app()->getLocale();

        if ($record instanceof Sitemapable) {
            $url = $record->sitemapUrl($language);

            return $url === '' ? null : $url;
        }

        foreach ($this->resolvers as $resolver) {
            $url = $resolver($record, $language);

            if ($url === false) {
                return null;
            }

            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return PublicUrl::forRecord($record);
    }

    /**
     * Whether records of this model may have pages at all: a module that
     * lists its pages, or one with an address rule.
     */
    public function hasPages(string $model): bool
    {
        return is_subclass_of($model, Sitemapable::class) || PublicUrl::ruleFor($model) !== null;
    }
}
