<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Console\Commands;

use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Modules\Module;
use Atrium\Admin\Modules\Modules;
use Illuminate\Console\Command;

/**
 * What atrium:module:install and atrium:module:remove share.
 */
abstract class ModuleCommand extends Command
{
    protected function packageName(): string
    {
        return strtolower(trim((string) $this->argument('package')));
    }

    /**
     * Keys of the module's models in the panel: `brands`, `products`.
     *
     * @return array<int,string>
     */
    protected function moduleKeys(Module $module): array
    {
        $keys = [];

        foreach ($module->resources as $model) {
            if (class_exists($model) && is_subclass_of($model, Administrable::class)) {
                $keys[] = $model::getAdminRoutePrefix();
            }
        }

        return $keys;
    }

    protected function listAvailable(Modules $modules): void
    {
        $available = array_keys(array_filter($modules->all(), static fn (Module $module): bool => ! $modules->isInstalled($module->name)));

        if ($available !== []) {
            $this->components->bulletList($available);
        }
    }

    /**
     * Cached config and routes would not know about the change: build them
     * again, as they were.
     */
    protected function refreshCaches(): void
    {
        if ($this->laravel->configurationIsCached()) {
            $this->callSilently('config:cache');
        }

        if ($this->laravel->routesAreCached()) {
            $this->callSilently('route:cache');
        }
    }
}
