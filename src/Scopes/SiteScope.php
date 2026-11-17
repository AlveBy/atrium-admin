<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Scopes;

use Atrium\Admin\Support\Sites;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Only the current site's records — once a request has said which site it
 * is about. withoutGlobalScope(SiteScope::class) for all of them.
 */
final class SiteScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $sites = app(Sites::class);

        if ($sites->isResolved()) {
            $builder->where($model->qualifyColumn('site_id'), $sites->current()->id);
        } elseif ($sites->allowedIds() !== null) {
            $builder->whereIn($model->qualifyColumn('site_id'), $sites->allowedIds());
        }
    }
}
