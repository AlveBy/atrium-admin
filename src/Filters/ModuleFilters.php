<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Filters;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Models\AdminUser;

/**
 * The filters of a module's list for one administrator: the module's own
 * (getAdministrationFilters()) and those of other modules
 * (`atrium.module.filters`), without the ones whose permission they lack.
 * The list, the API and MCP read them here, so they agree.
 *
 * A name alone (`'module'`) is a filter of the address the list keeps but
 * does not draw; a Filter is drawn above the list.
 */
final class ModuleFilters
{
    /**
     * By name.
     *
     * @param  class-string  $model
     * @return array<string,string|Filter>
     */
    public function for(string $model, mixed $user): array
    {
        $filters = [];

        foreach (Hook::apply('atrium.module.filters', $model::getAdministrationFilters(), $model) as $filter) {
            if ($filter instanceof Filter && ! $this->allows($user, $filter->permissionIn($model::getAdminRoutePrefix()))) {
                continue;
            }

            $filters[$filter instanceof Filter ? $filter->getName() : $filter] = $filter;
        }

        return $filters;
    }

    /**
     * The drawn ones, as the panel and MCP describe them.
     *
     * @param  class-string  $model
     * @return array<int,array<string,mixed>>
     */
    public function describe(string $model, mixed $user): array
    {
        $drawn = array_filter($this->for($model, $user), static fn (string|Filter $filter): bool => $filter instanceof Filter);

        return array_values(array_map(static fn (Filter $filter): array => $filter->toArray(), $drawn));
    }

    /**
     * Whether a user has a permission: one that is not an administrator
     * (a console command) has every one, as with a module's buttons.
     */
    public function allows(mixed $user, ?string $permission): bool
    {
        return $permission === null || ! $user instanceof AdminUser || $user->hasAdminPermission($permission);
    }
}
