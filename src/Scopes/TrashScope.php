<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Scopes;

use Atrium\Admin\Contracts\Administrable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Query\Builder as Query;

/**
 * Hides the records in the trash (Atrium\Admin\History\Trash): in the panel,
 * the API and on the site alike. withoutGlobalScope(TrashScope::class) for
 * them too.
 */
final class TrashScope implements Scope
{
    /**
     * The module's key is asked for only when a query is built: a module may
     * read it from a record of its own, which it cannot make while it boots.
     *
     * @param  class-string<Administrable>  $module
     */
    public function __construct(private readonly string $module) {}

    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereNotExists(fn (Query $trash) => $trash
            ->selectRaw('1')
            ->from('admin_trash')
            ->where('admin_trash.module', $this->module::getAdminRoutePrefix())
            ->whereColumn('admin_trash.record_id', $model->getQualifiedKeyName()));
    }
}
