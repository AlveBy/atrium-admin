<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Console\Commands;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Modules\Modules;

/**
 * The second step after `composer require`: the module's tables, its entry in
 * the menu and its permissions in the role form. Composer itself is not run.
 */
final class ModuleInstallCommand extends ModuleCommand
{
    protected $signature = 'atrium:module:install
                            {package : Composer name of the module, e.g. alveby/atrium-brands}
                            {--demo : Fill it with demo content as well}';

    protected $description = 'Install a module package required with Composer: migrations, menu, permissions';

    public function handle(Modules $modules): int
    {
        $name = $this->packageName();
        $module = $modules->find($name);

        if ($module === null) {
            $this->components->error("{$name} is not an Atrium module Composer has installed. Run `composer require {$name}` first.");
            $this->listAvailable($modules);

            return self::FAILURE;
        }

        if (! is_file(config_path('atrium.php'))) {
            $this->callSilently('vendor:publish', ['--tag' => 'atrium-config']);
        }

        $already = $modules->isInstalled($name);

        if (! $modules->install($module)) {
            $this->components->error("config/atrium.php could not be edited: add '{$name}' to its 'modules' list and run this command again.");

            return self::FAILURE;
        }

        if ($module->migrations !== []) {
            $this->components->task('Running its migrations', fn (): bool => $this->callSilently('migrate', [
                '--path' => $module->migrations,
                '--realpath' => true,
                '--force' => true,
            ]) === 0);
        }

        if ($this->option('demo') && $module->demo !== null) {
            $this->components->task('Filling it with demo content', fn (): bool => $modules->seedDemo($module) || true);
        }

        $this->refreshCaches();

        Hook::fire('atrium.module.installed', $module);

        $this->components->info($already
            ? "{$name} was installed already; its tables are up to date."
            : "{$name} is installed. In the menu and the role form: ".implode(', ', $this->moduleKeys($module)).'.');

        return self::SUCCESS;
    }
}
