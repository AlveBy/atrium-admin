<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Views;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Http\Controllers\AdminController;
use Atrium\Admin\Models\AdminUser;
use InvalidArgumentException;

/**
 * The views of a module's list besides the table — its own
 * (getAdministrationViews()) and other modules' (`atrium.module.views`) —
 * and the one an administrator looks at: the one asked for in the address,
 * else the one they used last time in this module.
 */
final class ModuleViews
{
    /** The list as a table: what every module has. */
    public const TABLE = 'table';

    /**
     * By type; of two views of one type the first one wins. A view built
     * on a field the module does not have is a mistake of the module, and
     * says so.
     *
     * @param  class-string  $model
     * @return array<string,View>
     */
    public function for(string $model): array
    {
        $views = [];

        foreach (Hook::apply('atrium.module.views', $model::getAdministrationViews(), $model) as $view) {
            if ($view instanceof View && ! isset($views[$view->type()])) {
                $views[$view->type()] = $view;
            }
        }

        if ($views !== []) {
            $this->check($model, $views);
        }

        return $views;
    }

    /**
     * The view to draw, or null for the table. Asking for one the module
     * has remembers it for the administrator; asking for one it has not is
     * no ask.
     *
     * @param  array<string,View>  $views
     */
    public function choose(array $views, mixed $asked, mixed $user, string $module): ?View
    {
        $type = is_string($asked) && ($asked === self::TABLE || isset($views[$asked])) ? $asked : null;

        if ($user instanceof AdminUser) {
            $remembered = (array) $user->list_views;

            if ($type !== null) {
                $user->forceFill(['list_views' => [...$remembered, $module => $type]])->save();
            }

            $type ??= $remembered[$module] ?? null;
        }

        return $type === null ? null : ($views[$type] ?? null);
    }

    /**
     * What the switcher offers: the table first, then the module's views.
     *
     * @param  array<string,View>  $views
     * @return array<int,string>
     */
    public function types(array $views): array
    {
        return [self::TABLE, ...array_keys($views)];
    }

    /**
     * @param  class-string  $model
     * @param  array<string,View>  $views
     */
    private function check(string $model, array $views): void
    {
        $types = [];

        foreach (AdminController::fieldsOf($model) as $field) {
            $types[$field->getName()] = $field->getType();
        }

        foreach ($views as $view) {
            foreach ($view->needs() as $name => $allowed) {
                if (! in_array($types[$name] ?? null, $allowed, true)) {
                    throw new InvalidArgumentException(sprintf(
                        'The %s view of %s is built on the field "%s", which has to be a field of the form of type %s.',
                        $view->type(),
                        $model,
                        $name,
                        implode(' or ', $allowed),
                    ));
                }
            }
        }
    }
}
