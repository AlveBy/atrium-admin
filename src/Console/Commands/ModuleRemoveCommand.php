<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Console\Commands;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Modules\Modules;
use Atrium\Admin\Modules\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Takes a module out of the panel: menu, roles and, if asked, its tables.
 * The package stays required until `composer remove`.
 */
final class ModuleRemoveCommand extends ModuleCommand
{
    protected $signature = 'atrium:module:remove
                            {package : Composer name of the module, e.g. alveby/atrium-brands}
                            {--keep-data : Keep its tables and records without asking}
                            {--delete-data : Drop its tables without asking}';

    protected $description = 'Remove an installed module package from the panel, keeping or dropping its data';

    public function handle(Modules $modules): int
    {
        $name = $this->packageName();

        if (! $modules->isInstalled($name) && ! $modules->isDisabled($name)) {
            $this->components->info("{$name} is not installed.");

            return self::SUCCESS;
        }

        // Gone from Composer already: only the config entry is left.
        $module = $modules->find($name);
        $keys = $module === null ? [] : $this->moduleKeys($module);
        $own = $module === null ? [] : $module->permissions();
        $hasData = $module !== null && ($module->migrations !== [] || $module->settings !== null);

        $keep = match (true) {
            ! $hasData, (bool) $this->option('keep-data') => true,
            (bool) $this->option('delete-data') => false,
            default => $this->confirm("Keep the data of {$name} (its tables and records)? If not, they are dropped", true),
        };

        // Its submodules go first, with the same answer about the data.
        foreach ($module === null ? [] : $modules->childrenOf($module) as $child) {
            if ($modules->isInstalled($child->name) || $modules->isDisabled($child->name)) {
                $this->call('atrium:module:remove', ['package' => $child->name, $keep ? '--keep-data' : '--delete-data' => true]);
            }
        }

        if (! $keep && $module->migrations !== []) {
            $this->components->task('Dropping its tables', fn (): bool => $this->callSilently('migrate:reset', [
                '--path' => $module->migrations,
                '--realpath' => true,
                '--force' => true,
            ]) === 0);
        }

        if (! $keep && Schema::hasTable(Settings::TABLE)) {
            DB::table(Settings::TABLE)->where('module', $module->key())->delete();
            $this->laravel->make(Settings::class)->flush();
        }

        if (($keys !== [] || $own !== []) && Schema::hasTable('admin_roles')) {
            $this->components->task('Taking it out of the roles', function () use ($keys, $own): void {
                foreach (AdminRole::query()->get() as $role) {
                    $permissions = array_values(array_filter(
                        (array) $role->permissions,
                        static fn (string $permission): bool => ! in_array(Str::before($permission, '.'), $keys, true) && ! in_array($permission, $own, true),
                    ));

                    if ($permissions !== $role->permissions) {
                        $role->update(['permissions' => $permissions]);
                    }
                }
            });
        }

        if (! $modules->remove($name)) {
            $this->components->error("config/atrium.php could not be edited: take '{$name}' out of its 'modules' list.");

            return self::FAILURE;
        }

        $this->refreshCaches();

        Hook::fire('atrium.module.removed', $name, ! $keep);

        $this->components->info(($keep && $hasData ? "{$name} is removed; its data is kept." : "{$name} is removed.")
            .($module !== null ? " Run `composer remove {$name}` if the package is no longer needed." : ''));

        return self::SUCCESS;
    }
}
