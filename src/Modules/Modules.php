<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Modules;

use Atrium\Admin\Resources\ResourceRegistry;
use Atrium\Admin\Support\ConfigWriter;
use Atrium\Admin\Support\PublicUrl;
use Composer\InstalledVersions;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Seeder;

/**
 * The module packages: required with Composer (`"type": "atrium-module"`),
 * installed with `atrium:module:install`.
 *
 * Required is not yet installed. An installed module is listed in
 * `atrium.modules`; only then are its models in the menu, the role form and
 * the API, its migrations in `php artisan migrate` and its pages on the
 * site. A model a module lists stays out of the panel while that module is
 * not installed, even when its service provider pushes it to
 * `atrium.resources`.
 */
final class Modules
{
    public const TYPE = 'atrium-module';

    /** @var array<string,Module>|null name => module */
    private ?array $modules = null;

    /** @var array<string,string>|null model class => module name */
    private ?array $owners = null;

    public function __construct(private readonly Application $app) {}

    /**
     * Every module Composer has installed, by name.
     *
     * @return array<string,Module>
     */
    public function all(): array
    {
        if ($this->modules !== null) {
            return $this->modules;
        }

        $this->modules = [];

        foreach (array_unique(InstalledVersions::getInstalledPackagesByType(self::TYPE)) as $name) {
            $path = InstalledVersions::getInstallPath($name);
            $module = $path === null ? null : Module::fromPath($path);

            if ($module !== null) {
                $this->modules[$module->name] = $module;
            }
        }

        ksort($this->modules);

        return $this->modules;
    }

    /**
     * Make a module known without Composer: tests, or a module kept in the
     * application itself.
     */
    public function add(Module $module): void
    {
        $this->all();
        $this->modules[$module->name] = $module;
        $this->owners = null;
    }

    public function find(string $name): ?Module
    {
        return $this->all()[strtolower($name)] ?? null;
    }

    public function isInstalled(string $name): bool
    {
        return in_array(strtolower($name), $this->installedNames(), true);
    }

    /**
     * The installed modules, in the order of `atrium.modules` (the menu's).
     *
     * @return array<string,Module>
     */
    public function installed(): array
    {
        $installed = [];

        foreach ($this->installedNames() as $name) {
            if ($module = $this->find($name)) {
                $installed[$name] = $module;
            }
        }

        return $installed;
    }

    /**
     * The module that lists this model, installed or not.
     */
    public function ownerOf(string $model): ?Module
    {
        if ($this->owners === null) {
            $this->owners = [];

            foreach ($this->all() as $module) {
                foreach ($module->resources as $resource) {
                    $this->owners[ltrim($resource, '\\')] = $module->name;
                }
            }
        }

        $owner = $this->owners[ltrim($model, '\\')] ?? null;

        return $owner === null ? null : $this->find($owner);
    }

    /**
     * Whether a model can be used: its class is there and, when a module
     * brings it, that module is installed. Models of the application itself
     * are always available.
     */
    public function available(string $model): bool
    {
        if (! class_exists($model)) {
            return false;
        }

        $owner = $this->ownerOf($model);

        return $owner === null || $this->isInstalled($owner->name);
    }

    /**
     * Mark the module installed: in config/atrium.php for the next requests,
     * and in this one. False when the config file could not be edited.
     */
    public function install(Module $module): bool
    {
        if (! $this->isInstalled($module->name) && ! ConfigWriter::addModule($module->name)) {
            return false;
        }

        $this->setInstalled([...$this->installedNames(), $module->name]);
        $this->wire($module);

        return true;
    }

    public function remove(string $name): bool
    {
        if (! ConfigWriter::removeModule($name)) {
            return false;
        }

        $this->setInstalled(array_diff($this->installedNames(), [strtolower($name)]));

        return true;
    }

    /**
     * Hook an installed module in: its migrations and its pages on the site.
     * A rule the application sets for the same model wins.
     */
    public function wire(Module $module): void
    {
        $config = $this->app['config'];
        $config->set('atrium.public_urls', (array) $config->get('atrium.public_urls', []) + $module->publicUrls);

        foreach ($module->migrations as $path) {
            $this->app->afterResolving('migrator', static fn ($migrator) => $migrator->path($path));

            if ($this->app->resolved('migrator')) {
                $this->app->make('migrator')->path($path);
            }
        }

        PublicUrl::forgetPaths();
    }

    /**
     * Fill a module with its demo content. False when it has content already
     * (its seeder says so by returning false) or has no demo.
     */
    public function seedDemo(Module $module): bool
    {
        if ($module->demo === null) {
            return false;
        }

        $seeder = $this->app->make($module->demo);

        if (! $seeder instanceof Seeder) {
            return false;
        }

        $seeded = $seeder->setContainer($this->app)->__invoke() !== false;

        // New sections and records: the addresses of the site are built again.
        PublicUrl::forgetPaths();

        return $seeded;
    }

    /**
     * Modules a module suggests come before it, so a product's demo content
     * finds the brands it links to.
     *
     * @param  array<string,Module>  $modules
     * @return array<string,Module>
     */
    public function dependencyOrder(array $modules): array
    {
        $sorted = [];

        $visit = function (Module $module, array $path) use (&$visit, &$sorted, $modules): void {
            if (isset($sorted[$module->name]) || in_array($module->name, $path, true)) {
                return;
            }

            foreach ($module->suggests as $name) {
                if (isset($modules[$name])) {
                    $visit($modules[$name], [...$path, $module->name]);
                }
            }

            $sorted[$module->name] = $module;
        };

        foreach ($modules as $module) {
            $visit($module, []);
        }

        return $sorted;
    }

    /**
     * @return array<int,string>
     */
    private function installedNames(): array
    {
        return array_values(array_map('strtolower', array_map('strval', (array) $this->app['config']->get('atrium.modules', []))));
    }

    /**
     * @param  array<int,string>  $names
     */
    private function setInstalled(array $names): void
    {
        $this->app['config']->set('atrium.modules', array_values(array_unique($names)));

        if ($this->app->resolved(ResourceRegistry::class)) {
            $this->app->make(ResourceRegistry::class)->flush();
        }
    }
}
