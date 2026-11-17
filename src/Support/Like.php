<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * "Contains these words" the same way on MySQL, PostgreSQL and SQLite.
 *
 * A plain `like` is case-sensitive on PostgreSQL, and `%` or `_` typed in
 * the search box would be wildcards. Both sides go through lower(), and the
 * typed text is escaped with `!`: a backslash would need writing differently
 * for MySQL than for the others.
 */
final class Like
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>|QueryBuilder  $query
     */
    public static function contains(Builder|QueryBuilder $query, string $column, string $search, string $boolean = 'and'): void
    {
        $query->whereRaw(
            'lower('.$query->getGrammar()->wrap($column).") like lower(?) escape '!'",
            ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%'],
            $boolean,
        );
    }
}
