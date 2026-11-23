<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Console\Commands;

use Atrium\Admin\Console\Concerns\ReportsSteps;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Modules\Modules;
use Atrium\Admin\Support\PublicFolder;
use Illuminate\Console\Command;

/**
 * One command from `composer require` to a working panel.
 */
final class InstallCommand extends Command
{
    use ReportsSteps;

    protected $signature = 'atrium:install
                            {--name= : Name of the first administrator}
                            {--email= : Email of the first administrator}
                            {--password= : Password of the first administrator}
                            {--no-migrate : Do not run migrations}
                            {--demo : Install every module package Composer has and fill them with a small shop}';

    protected $description = 'Install Atrium: config, assets, migrations and the first administrator';

    public function handle(): int
    {
        $this->step('Publishing config', fn (): bool => $this->callSilently('vendor:publish', ['--tag' => 'atrium-config']) === 0);
        $this->step('Publishing assets', fn (): bool => $this->callSilently('vendor:publish', ['--tag' => 'atrium-assets', '--force' => true]) === 0);

        // API tokens need Sanctum's table; publishing is a no-op when it is already there.
        if (! glob(database_path('migrations/*_create_personal_access_tokens_table.php'))) {
            $this->step('Publishing Sanctum migration', fn (): bool => $this->callSilently('vendor:publish', ['--tag' => 'sanctum-migrations']) === 0);
        }

        if ($this->option('demo')) {
            $this->installModules();
        }

        if (! $this->option('no-migrate')) {
            $this->step('Running migrations', fn (): bool => $this->callSilently('migrate', ['--force' => true]) === 0);

            if (! AdminUser::query()->exists()) {
                $this->components->info('Create the first administrator.');

                $status = $this->call('atrium:user', [
                    '--name' => $this->option('name'),
                    '--email' => $this->option('email'),
                    '--password' => $this->option('password'),
                ]);

                if ($status !== self::SUCCESS) {
                    return $status;
                }
            }
        }

        if ($this->option('demo')) {
            if ($this->option('no-migrate')) {
                $this->components->warn('The demo needs the modules\' tables: run `php artisan migrate` and `php artisan atrium:install --demo` again.');
            } else {
                $this->seedDemo();
            }
        }

        // Files uploaded in the admin go to storage/app/public and are served
        // through this link, demo or not.
        if (! is_link(public_path('storage')) && ! file_exists(public_path('storage'))) {
            $this->step('Linking '.PublicFolder::shown('storage'), fn (): bool => $this->callSilently('storage:link') === 0);
        }

        $this->removeStockRobotsTxt();

        $this->components->info('Atrium is ready: '.url(config('atrium.prefix', 'admin')));

        return self::SUCCESS;
    }

    /**
     * The demo shop is what the module packages bring: every one Composer
     * has is installed (`atrium:module:install`), so its migrations run next.
     */
    private function installModules(): void
    {
        $modules = $this->laravel->make(Modules::class);

        if ($modules->all() === []) {
            $this->components->warn('No module packages to fill: `composer require alveby/atrium-catalog alveby/atrium-brands alveby/atrium-stores alveby/atrium-news` and run this command again.');
        }

        foreach ($modules->all() as $module) {
            if (! $modules->isInstalled($module->name)) {
                $this->step("Installing {$module->name}", fn (): bool => $modules->install($module));
            }
        }
    }

    /**
     * The modules' demo content, the modules a module suggests first: the
     * brands are there before the products that link to them.
     */
    private function seedDemo(): void
    {
        $modules = $this->laravel->make(Modules::class);
        $seeded = false;

        foreach ($modules->dependencyOrder($modules->installed()) as $module) {
            if ($module->demo !== null) {
                $this->step("Filling {$module->name}", function () use ($modules, $module, &$seeded): bool {
                    $seeded = $modules->seedDemo($module) || $seeded;

                    return true;
                });
            }
        }

        if (! $seeded) {
            $this->components->info('The demo already has content; nothing was added.');
        }
    }

    /**
     * The web server returns robots.txt of the public folder before Laravel is asked, so
     * the one a fresh Laravel ships with would hide the text set in the
     * admin. Laravel's own file goes; one somebody wrote stays, with a warning.
     */
    private function removeStockRobotsTxt(): void
    {
        $file = public_path('robots.txt');

        if (! config('atrium.robots', true) || ! is_file($file)) {
            return;
        }

        $stock = preg_replace('/\s+/', ' ', trim((string) file_get_contents($file))) === 'User-agent: * Disallow:';

        if ($stock) {
            $this->step('Removing '.PublicFolder::shown('robots.txt').' (robots.txt is set in the admin now)', fn (): bool => unlink($file));

            return;
        }

        $this->components->warn(PublicFolder::shown('robots.txt').' is served instead of the robots.txt set in the admin. Move its text to Sites → robots.txt and delete the file, or set atrium.robots to false.');
    }
}
