<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Resources\ResourceRegistry;

/**
 * One controller for every module listed in `atrium.resources`.
 *
 * The model comes from the route name, so a module needs no controller of its
 * own. A project that wants custom behaviour can still extend AdminController
 * and register its own routes.
 */
final class ResourceController extends AdminController
{
    /**
     * Resolved per call, not in the constructor: `route:list` and middleware
     * gathering build controllers with no matched route.
     *
     * @param  string  $method
     * @param  array<int|string,mixed>  $parameters
     */
    public function callAction($method, $parameters): mixed
    {
        $model = app(ResourceRegistry::class)->modelForRoute(request()->route()?->getName());

        abort_if($model === null, 404);

        $this->model = $model;

        return parent::callAction($method, $parameters);
    }
}
