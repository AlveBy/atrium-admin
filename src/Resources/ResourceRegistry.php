<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Resources;

use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Modules\Modules;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The modules of the admin panel.
 *
 * A module is an Eloquent model that implements Administrable and is listed in
 * `config('atrium.resources')`. Nothing else is needed: routes, pages, menu
 * entries and the API are derived from that list.
 *
 * The key of a module is the model's getAdminRoutePrefix() ("articles"), so its
 * routes are named `admin.articles.index`, `admin.articles.edit`, …
 *
 * The panel's own modules (sites, content languages) come first and need no config,
 * then the installed module packages (Atrium\Admin\Modules\Modules) and the
 * application's own. A model of a module package that is not installed is
 * left out.
 */
final class ResourceRegistry
{
    /** @var array<int,class-string<Model&Administrable>> */
    private const BUILT_IN = [Site::class, Language::class];

    /** @var array<string,class-string<Model&Administrable>>|null */
    private ?array $resources = null;

    /**
     * @return array<string,class-string<Model&Administrable>> key => model class
     */
    public function all(): array
    {
        if ($this->resources !== null) {
            return $this->resources;
        }

        $resources = [];

        $modules = app(Modules::class);
        $packaged = array_merge(...array_values(array_map(static fn ($module): array => $module->resources, $modules->installed())));

        foreach ([...self::BUILT_IN, ...$packaged, ...(array) config('atrium.resources', [])] as $model) {
            if (! is_string($model) || ! class_exists($model)) {
                throw new InvalidArgumentException("Atrium resource [{$model}] is not a class.");
            }

            if (! $modules->available($model)) {
                continue;
            }

            if (! is_subclass_of($model, Model::class) || ! is_subclass_of($model, Administrable::class)) {
                throw new InvalidArgumentException("Atrium resource [{$model}] must be an Eloquent model that implements ".Administrable::class.'.');
            }

            $resources[$model::getAdminRoutePrefix()] = $model;
        }

        return $this->resources = $resources;
    }

    /**
     * @return class-string<Model&Administrable>|null
     */
    public function model(string $key): ?string
    {
        return $this->all()[$key] ?? null;
    }

    public function keyOf(string $model): ?string
    {
        $key = array_search($model, $this->all(), true);

        return $key === false ? null : $key;
    }

    /**
     * The module a route belongs to: `admin.articles.edit` → Article::class.
     *
     * @return class-string<Model&Administrable>|null
     */
    public function modelForRoute(?string $routeName): ?string
    {
        if ($routeName === null || ! preg_match('/^admin\.([a-z0-9_]+)\.[a-z]+$/', $routeName, $match)) {
            return null;
        }

        return $this->model($match[1]);
    }

    /**
     * Forget the cached list — for tests that change the config at runtime.
     */
    public function flush(): void
    {
        $this->resources = null;
    }
}
