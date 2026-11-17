<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Cache;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Throwable;

/**
 * Opt a model into the record cache (with the Cacheable interface):
 *
 *     class Product extends Model implements Administrable, Cacheable
 *     {
 *         use CachesRecords;
 *     }
 *
 *     Product::cachedMany([12, 7, 31]);        // cards in this order, one cache read
 *     Product::cached(12, 'de');               // one card or null
 *     Product::query()->where(...)->cachedPaginate(20);
 *
 * A card carries the relations named by cacheRelations(). By default these
 * come from the admin form: a belongs-to-many field's relation, the relation
 * behind a belongs-to or section field (`brand_id` → `brand()`), and the
 * SEO meta. A relation to the model's own class (a parent section, related
 * products) is left out: saving one of those records does not rebuild the
 * cards that would show it, and it would copy whole records into each
 * card. Override it to choose yourself.
 *
 * @mixin Model
 */
trait CachesRecords
{
    /**
     * @return array<int,string>
     */
    public function cacheRelations(): array
    {
        $names = [];
        // The trait may sit on a model that is not in the admin.
        /** @phpstan-ignore function.alreadyNarrowedType */
        $fields = method_exists(static::class, 'getAdministrationFields') ? static::getAdministrationFields() : [];

        foreach ($fields as $field) {
            $name = match ($field->getType()) {
                'belongsToMany' => $field->getName(),
                'belongsTo', 'treeSelect' => str_ends_with($field->getColumn(), '_id') ? Str::camel(Str::beforeLast($field->getColumn(), '_id')) : null,
                default => null,
            };

            if ($name !== null && $this->isCacheRelation($name)) {
                $names[] = $name;
            }
        }

        if ($this->isCacheRelation('seoMeta')) {
            $names[] = 'seoMeta';
        }

        return array_values(array_unique($names));
    }

    /**
     * @return array<int,Card>
     */
    public static function cachedMany(iterable $ids, ?string $locale = null): array
    {
        return app(RecordCache::class)->cards(static::class, $ids, $locale);
    }

    public static function cached(int|string $id, ?string $locale = null): ?Card
    {
        return app(RecordCache::class)->cards(static::class, [$id], $locale)[0] ?? null;
    }

    private function isCacheRelation(string $name): bool
    {
        if (! method_exists($this, $name)) {
            return false;
        }

        try {
            $relation = $this->{$name}();

            return $relation instanceof Relation && $relation->getRelated()::class !== static::class;
        } catch (Throwable) {
            return false;
        }
    }
}
